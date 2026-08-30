<?php

namespace App\Models;

use App\Enums\LeadEntryChannel;
use App\Enums\UserRole;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Lead extends Model
{
    protected $primaryKey = 'lead_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'kanal_masuk' => LeadEntryChannel::class,
            'whatsapp_first_seen_at' => 'datetime',
            'whatsapp_last_seen_at' => 'datetime',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id', 'cabang_id');
    }

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id', 'sekolah_id');
    }

    public function minatProgram()
    {
        return $this->belongsTo(ProgramBimbel::class, 'minat_program_id', 'program_bimbel_id');
    }

    public function diinputOleh()
    {
        return $this->belongsTo(User::class, 'diinput_oleh_user_id');
    }

    public function diserahkanKe()
    {
        return $this->belongsTo(User::class, 'diserahkan_ke_user_id');
    }

    public function followups()
    {
        return $this->hasMany(FollowupRiwayat::class, 'lead_id', 'lead_id');
    }

    public function pendaftaran()
    {
        return $this->hasOne(Pendaftaran::class, 'lead_id', 'lead_id');
    }

    public function whatsappConversations()
    {
        return $this->hasMany(WhatsAppConversation::class, 'lead_id', 'lead_id');
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'lead_id', 'lead_id');
    }

    protected static function booted(): void
    {
        static::saving(function (Lead $lead): void {
            if ($lead->isDirty('no_wa') || blank($lead->no_wa_normalized)) {
                $lead->no_wa_normalized = app(PhoneNormalizer::class)->normalize($lead->no_wa);
            }

            if ($lead->no_wa_normalized && ($lead->isDirty('no_wa') || $lead->isDirty('no_wa_normalized'))) {
                $duplicateExists = static::query()
                    ->where('no_wa_normalized', $lead->no_wa_normalized)
                    ->when($lead->exists, fn (Builder $query): Builder => $query->whereKeyNot($lead->getKey()))
                    ->exists();

                if ($duplicateExists) {
                    throw ValidationException::withMessages([
                        'no_wa' => 'Nomor WhatsApp sudah digunakan oleh lead lain. Periksa data lead yang sudah ada.',
                    ]);
                }
            }

            if ($lead->isDirty('kota_asal')) {
                $lead->kota_asal = filled($lead->kota_asal)
                    ? Str::squish((string) $lead->kota_asal)
                    : null;
            }

            if ($lead->isDirty('kelas_sekolah')) {
                $lead->kelas_sekolah = filled($lead->kelas_sekolah)
                    ? Str::squish((string) $lead->kelas_sekolah)
                    : null;
            }
        });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalBranchAccess()) {
            return $query;
        }

        $query->where('cabang_id', $user->cabang_id);

        return $user->hasRole(UserRole::Staff)
            ? $query->where(fn (Builder $q) => $q->where('diserahkan_ke_user_id', $user->id)->orWhere('diinput_oleh_user_id', $user->id))
            : $query;
    }
}
