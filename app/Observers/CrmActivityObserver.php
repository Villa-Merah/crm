<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Cabang;
use App\Models\FollowupRiwayat;
use App\Models\Lead;
use App\Models\Pembayaran;
use App\Models\TargetKinerjaUser;
use Illuminate\Database\Eloquent\Model;

class CrmActivityObserver
{
    private const SENSITIVE = ['password', 'remember_token', 'email', 'no_wa', 'no_wa_normalized', 'phone_e164', 'no_telepon', 'support_email', 'support_phone', 'alamat', 'catatan', 'fasilitas_program', 'body', 'raw_payload'];

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');

        if ($model instanceof Lead && $model->wasChanged('kelas_sekolah')) {
            $this->recordLeadClassChange($model);
        }
    }

    private function recordLeadClassChange(Lead $lead): void
    {
        $oldClass = $lead->getRawOriginal('kelas_sekolah');
        $newClass = $lead->kelas_sekolah;

        ActivityLog::create([
            'user_id' => auth()->id(),
            'cabang_id' => $this->resolveBranchId($lead),
            'subject_type' => $lead->getMorphClass(),
            'subject_id' => $lead->getKey(),
            'event' => 'class_changed',
            'description' => 'Kelas sekolah lead diubah dari '.($oldClass ?: 'belum diisi').' menjadi '.($newClass ?: 'belum diisi'),
            'properties' => [
                'field' => 'kelas_sekolah',
                'old' => $oldClass,
                'new' => $newClass,
            ],
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : str(request()->userAgent())->limit(500)->toString(),
            'created_at' => now(),
        ]);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(Model $model, string $event): void
    {
        $fields = $event === 'updated' ? array_keys($model->getChanges()) : array_keys($model->getAttributes());
        $fields = array_values(array_diff($fields, [...self::SENSITIVE, 'created_at', 'updated_at']));
        if ($event === 'updated' && $fields === []) {
            return;
        }

        $labels = [
            'Lead' => 'Lead', 'FollowupRiwayat' => 'Follow-up', 'Pendaftaran' => 'Pendaftaran',
            'Pembayaran' => 'Pembayaran', 'ProgramBimbel' => 'Program bimbel',
            'TargetKinerjaCabang' => 'Target cabang', 'TargetKinerjaUser' => 'Target user',
            'Cabang' => 'Cabang', 'Sekolah' => 'Sekolah', 'User' => 'Pengguna',
            'UserWhatsAppAccount' => 'Koneksi WhatsApp',
        ];
        $name = class_basename($model);
        $verbs = ['created' => 'dibuat', 'updated' => 'diperbarui', 'deleted' => 'dihapus'];

        ActivityLog::create([
            'user_id' => auth()->id(),
            'cabang_id' => $this->resolveBranchId($model),
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'event' => $event,
            'description' => ($labels[$name] ?? $name).' '.$verbs[$event],
            'properties' => ['fields' => $fields],
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : str(request()->userAgent())->limit(500)->toString(),
            'created_at' => now(),
        ]);
    }

    private function resolveBranchId(Model $model): ?int
    {
        if ($model instanceof Cabang) {
            return null;
        }
        if ($model->getAttribute('cabang_id')) {
            return (int) $model->getAttribute('cabang_id');
        }
        if ($model instanceof FollowupRiwayat) {
            return $model->lead()->value('cabang_id');
        }
        if ($model instanceof Pembayaran) {
            return $model->pendaftaran()->value('cabang_id');
        }
        if ($model instanceof TargetKinerjaUser) {
            return $model->targetCabang()->value('cabang_id');
        }

        return null;
    }
}
