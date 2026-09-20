<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Cabang;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Throwable;

class AuditAktivitas extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Audit Aktivitas';

    protected static string|\UnitEnum|null $navigationGroup = 'MASTER DATA';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Audit Aktivitas';

    protected string $view = 'filament.pages.audit-aktivitas';

    #[Url]
    public ?int $cabang = null;

    #[Url]
    public ?int $pengguna = null;

    #[Url]
    public ?string $event = null;

    #[Url]
    public ?string $tanggalMulai = null;

    #[Url]
    public ?string $tanggalAkhir = null;

    #[Url]
    public string $pencarian = '';

    public ?string $auditError = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->enforceScope();
    }

    public function updated($property): void
    {
        if (in_array($property, ['cabang', 'pengguna', 'event', 'tanggalMulai', 'tanggalAkhir', 'pencarian'], true)) {
            $this->resetPage();
        }
    }

    public function getViewData(): array
    {
        try {
            $this->auditError = null;
            $this->enforceScope();
            $viewer = auth()->user();
            $activities = ActivityLog::query()->visibleTo($viewer)->with(['user:id,name', 'cabang:cabang_id,nama'])
                ->when($this->cabang, fn (Builder $query) => $query->where('cabang_id', $this->cabang))
                ->when($this->pengguna, fn (Builder $query) => $query->where('user_id', $this->pengguna))
                ->when($this->event, fn (Builder $query) => $query->where('event', $this->event))
                ->when($this->tanggalMulai, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->tanggalMulai))
                ->when($this->tanggalAkhir, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->tanggalAkhir))
                ->when($this->pencarian, fn (Builder $query) => $query->where('description', 'like', '%'.$this->pencarian.'%'))
                ->latest('created_at')->paginate(20);

            return [
                'activities' => $activities,
                'branches' => Cabang::query()->where('aktif', true)->when(! $viewer->hasGlobalBranchAccess(), fn (Builder $query) => $query->whereKey($viewer->cabang_id))->orderBy('nama')->get(),
                'users' => User::query()->when($this->cabang, fn (Builder $query) => $query->where('cabang_id', $this->cabang))->when($viewer->hasRole(UserRole::Staff), fn (Builder $query) => $query->whereKey($viewer->id))->where('aktif', true)->orderBy('name')->get(),
                'events' => ['created' => 'Dibuat', 'updated' => 'Diperbarui', 'class_changed' => 'Perubahan kelas sekolah', 'deleted' => 'Dihapus', 'login' => 'Masuk', 'logout' => 'Keluar'],
            ];
        } catch (Throwable $exception) {
            report($exception);
            $this->auditError = 'Riwayat aktivitas belum dapat dimuat. Silakan coba kembali.';

            return ['activities' => new LengthAwarePaginator([], 0, 20), 'branches' => collect(), 'users' => collect(), 'events' => []];
        }
    }

    private function enforceScope(): void
    {
        $viewer = auth()->user();
        if (! $viewer->hasGlobalBranchAccess()) {
            $this->cabang = $viewer->cabang_id;
        }
        if ($viewer->hasRole(UserRole::Staff)) {
            $this->pengguna = $viewer->id;
        }
    }
}
