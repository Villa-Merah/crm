<?php

namespace App\Services\WhatsApp;

use App\Enums\LeadEntryChannel;
use App\Enums\LeadStatus;
use App\Enums\WhatsAppMatchStatus;
use App\Models\Lead;
use App\Models\UserWhatsAppAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class LeadMatcher
{
    /** @return array{lead: ?Lead, status: WhatsAppMatchStatus, matches: Collection<int, Lead>} */
    public function matchOrCreate(
        UserWhatsAppAccount $account,
        string $phone,
        ?string $contactName,
        CarbonInterface $receivedAt,
        bool $allowCreate,
    ): array {
        $matches = Lead::query()->where('no_wa_normalized', $phone)->orderBy('lead_id')->get();

        if ($matches->count() === 1 && $matches->first()->cabang_id === $account->cabang_id) {
            return ['lead' => $matches->first(), 'status' => WhatsAppMatchStatus::Matched, 'matches' => $matches];
        }

        if ($matches->isNotEmpty()) {
            return ['lead' => null, 'status' => WhatsAppMatchStatus::Conflict, 'matches' => $matches];
        }

        if (! $allowCreate) {
            return ['lead' => null, 'status' => WhatsAppMatchStatus::Unresolved, 'matches' => $matches];
        }

        $lead = Lead::create([
            'nama' => filled($contactName) ? str($contactName)->limit(150)->toString() : 'Lead WhatsApp '.substr($phone, -6),
            'no_wa' => $phone,
            'no_wa_normalized' => $phone,
            'sekolah_id' => null,
            'minat_program_id' => null,
            'sumber_lead' => null,
            'kanal_masuk' => LeadEntryChannel::WhatsApp->value,
            'cabang_id' => $account->cabang_id,
            'status_lead' => LeadStatus::Cold->value,
            'diinput_oleh_user_id' => $account->user_id,
            'diserahkan_ke_user_id' => $account->user_id,
            'tanggal_masuk' => $receivedAt->toDateString(),
            'whatsapp_first_seen_at' => $receivedAt,
            'whatsapp_last_seen_at' => $receivedAt,
        ]);

        return ['lead' => $lead, 'status' => WhatsAppMatchStatus::NewLead, 'matches' => collect([$lead])];
    }
}
