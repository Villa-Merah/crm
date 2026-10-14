<?php

namespace App\Services\WhatsApp;

use App\Enums\LeadEntryChannel;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Enums\WhatsAppMatchStatus;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppConversation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsAppLeadQualificationService
{
    public function createLead(WhatsAppConversation $conversation, User $actor): Lead
    {
        $this->authorize($conversation, $actor);

        return DB::transaction(function () use ($conversation, $actor): Lead {
            $conversation = $this->lockConversation($conversation);

            if ($conversation->lead_id) {
                return $conversation->lead()->firstOrFail();
            }

            if (blank($conversation->contact_phone_e164)) {
                throw ValidationException::withMessages([
                    'conversation' => 'Nomor WhatsApp kontak tidak tersedia sehingga lead belum dapat dibuat.',
                ]);
            }

            $matches = Lead::query()
                ->where('no_wa_normalized', $conversation->contact_phone_e164)
                ->get();

            if ($matches->count() === 1 && $matches->first()->cabang_id === $conversation->account->cabang_id) {
                return $this->linkLead($conversation, $matches->first(), $actor, false);
            }

            if ($matches->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'conversation' => 'Nomor WhatsApp sudah terkait dengan lead lain. Gunakan tindakan Hubungkan lead dan periksa cabangnya.',
                ]);
            }

            $lead = Lead::create([
                'nama' => filled($conversation->contact_name)
                    ? str($conversation->contact_name)->limit(150)->toString()
                    : 'Lead WhatsApp '.substr($conversation->contact_phone_e164, -6),
                'no_wa' => $conversation->contact_phone_e164,
                'no_wa_normalized' => $conversation->contact_phone_e164,
                'sekolah_id' => null,
                'minat_program_id' => null,
                'sumber_lead_id' => null,
                'kanal_masuk' => LeadEntryChannel::WhatsApp->value,
                'cabang_id' => $conversation->account->cabang_id,
                'status_lead' => LeadStatus::Cold->value,
                'diinput_oleh_user_id' => $conversation->account->user_id,
                'diserahkan_ke_user_id' => $conversation->account->user_id,
                'tanggal_masuk' => ($conversation->first_message_at ?? now())->toDateString(),
                'whatsapp_first_seen_at' => $conversation->first_message_at,
                'whatsapp_last_seen_at' => $conversation->last_message_at,
            ]);

            $conversation->update([
                'lead_id' => $lead->getKey(),
                'match_status' => WhatsAppMatchStatus::NewLead,
            ]);
            $conversation->messages()->update(['lead_id' => $lead->getKey()]);
            $this->audit($conversation, $actor, 'whatsapp_qualified_as_lead', 'Percakapan WhatsApp dijadikan lead', [
                'lead_id' => $lead->getKey(),
            ]);

            return $lead;
        });
    }

    public function linkLead(WhatsAppConversation $conversation, Lead $lead, User $actor, bool $lock = true): Lead
    {
        $this->authorize($conversation, $actor);

        $operation = function () use ($conversation, $lead, $actor, $lock): Lead {
            $conversation = $lock ? $this->lockConversation($conversation) : $conversation;
            $lead = Lead::query()->visibleTo($actor)
                ->where('cabang_id', $conversation->account->cabang_id)
                ->findOrFail($lead->getKey());

            $conversation->update([
                'lead_id' => $lead->getKey(),
                'match_status' => WhatsAppMatchStatus::Matched,
            ]);
            $conversation->messages()->update(['lead_id' => $lead->getKey()]);
            $lead->update([
                'whatsapp_first_seen_at' => $lead->whatsapp_first_seen_at ?? $conversation->first_message_at,
                'whatsapp_last_seen_at' => $conversation->last_message_at,
            ]);
            $this->audit($conversation, $actor, 'whatsapp_linked', 'Percakapan WhatsApp dihubungkan ke lead', [
                'lead_id' => $lead->getKey(),
            ]);

            return $lead;
        };

        return $lock ? DB::transaction($operation) : $operation();
    }

    public function ignore(WhatsAppConversation $conversation, User $actor, string $reason): void
    {
        $this->authorize($conversation, $actor);

        DB::transaction(function () use ($conversation, $actor, $reason): void {
            $conversation = $this->lockConversation($conversation);
            if ($conversation->lead_id) {
                throw ValidationException::withMessages([
                    'conversation' => 'Percakapan yang sudah terhubung ke lead tidak dapat ditandai sebagai bukan lead.',
                ]);
            }

            $conversation->update(['match_status' => WhatsAppMatchStatus::Ignored]);
            $this->audit($conversation, $actor, 'whatsapp_ignored', 'Percakapan WhatsApp ditandai bukan lead', [
                'reason' => $reason,
            ]);
        });
    }

    public function reopen(WhatsAppConversation $conversation, User $actor): void
    {
        $this->authorize($conversation, $actor);

        DB::transaction(function () use ($conversation, $actor): void {
            $conversation = $this->lockConversation($conversation);
            if ($conversation->lead_id) {
                throw ValidationException::withMessages([
                    'conversation' => 'Percakapan sudah terhubung ke lead.',
                ]);
            }

            $conversation->update(['match_status' => WhatsAppMatchStatus::Unresolved]);
            $this->audit($conversation, $actor, 'whatsapp_reopened', 'Percakapan WhatsApp dikembalikan untuk ditinjau');
        });
    }

    private function authorize(WhatsAppConversation $conversation, User $actor): void
    {
        $canResolve = in_array($actor->role, [UserRole::Superadmin, UserRole::AdminCabang, UserRole::Staff], true);
        $isVisible = WhatsAppConversation::query()->visibleTo($actor)->whereKey($conversation)->exists();

        if (! $canResolve || ! $isVisible) {
            throw new AuthorizationException('Anda tidak berwenang mengklasifikasikan percakapan WhatsApp ini.');
        }
    }

    private function lockConversation(WhatsAppConversation $conversation): WhatsAppConversation
    {
        return WhatsAppConversation::query()
            ->with('account')
            ->lockForUpdate()
            ->findOrFail($conversation->getKey());
    }

    /** @param array<string, mixed> $properties */
    private function audit(
        WhatsAppConversation $conversation,
        User $actor,
        string $event,
        string $description,
        array $properties = [],
    ): void {
        ActivityLog::create([
            'user_id' => $actor->getKey(),
            'cabang_id' => $conversation->account->cabang_id,
            'subject_type' => $conversation->getMorphClass(),
            'subject_id' => $conversation->getKey(),
            'event' => $event,
            'description' => $description,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }
}
