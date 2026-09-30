<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\Cabang;
use App\Models\CrmSetting;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Laporan extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Laporan';

    protected static string|\UnitEnum|null $navigationGroup = 'SIVMI CRM';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Laporan';

    protected string $view = 'filament.pages.laporan';

    #[Url]
    public ?int $cabang = null;

    #[Url]
    public int $tahun;

    #[Url]
    public int $bulan;

    #[Url]
    public ?int $pengguna = null;

    public ?string $reportError = null;

    public function mount(): void
    {
        $this->tahun = $this->tahun ?? now()->year;
        $this->bulan = $this->bulan ?? now()->month;
        $this->enforceViewerScope();
    }

    public function updatedCabang(): void
    {
        if ($this->pengguna && ! User::query()->whereKey($this->pengguna)->when($this->cabang, fn ($query) => $query->where('cabang_id', $this->cabang))->exists()) {
            $this->pengguna = null;
        }
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless($this->canExport(), 403);
        $this->enforceViewerScope();
        $data = $this->queryReportData();
        $filename = sprintf('laporan-sivmi-%d-%02d.csv', $this->tahun, $this->bulan);
        $organizationName = (string) CrmSetting::getValue('organization_name', 'SIVMI');

        return response()->streamDownload(function () use ($data, $organizationName): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Laporan '.$organizationName, $data['periodLabel']], ';');
            fputcsv($stream, ['Leads', $data['stats']['leads']], ';');
            fputcsv($stream, ['Pendaftaran', $data['stats']['registrations']], ';');
            fputcsv($stream, ['Konversi', number_format($data['stats']['conversion'], 1, ',', '.').'%'], ';');
            fputcsv($stream, ['Omzet terverifikasi', $data['stats']['revenue']], ';');
            fputcsv($stream, [], ';');
            fputcsv($stream, ['Cabang', 'Leads', 'Pendaftaran', 'Konversi (%)', 'Omzet', 'Target omzet', 'Capaian omzet (%)', 'Target siswa', 'Capaian siswa (%)'], ';');
            foreach ($data['branchesReport'] as $row) {
                fputcsv($stream, [$row['name'], $row['leads'], $row['registrations'], $row['conversion'], $row['revenue'], $row['target_revenue'], $row['revenue_progress'], $row['target_students'], $row['student_progress']], ';');
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function canExport(): bool
    {
        $viewer = auth()->user();

        return ! $viewer->hasRole(UserRole::Staff) || CrmSetting::getBoolean('staff_report_export');
    }

    public function getViewData(): array
    {
        try {
            $this->reportError = null;
            $this->enforceViewerScope();

            return $this->queryReportData();
        } catch (Throwable $exception) {
            report($exception);
            $this->reportError = 'Laporan belum dapat dimuat. Silakan coba kembali beberapa saat lagi.';

            return $this->emptyReportData();
        }
    }

    private function enforceViewerScope(): void
    {
        $viewer = auth()->user();
        if (! $viewer->hasGlobalBranchAccess()) {
            $this->cabang = $viewer->cabang_id;
        }
        if ($viewer->hasRole(UserRole::Staff)) {
            $this->pengguna = $viewer->id;
        }
    }

    private function periodRange(): array
    {
        $start = now()->setDate($this->tahun, $this->bulan, 1)->startOfMonth();

        return [$start->toDateString(), $start->copy()->addMonth()->toDateString(), $start];
    }

    private function scope(Builder $query, string $date, string $branch, ?string $user = null): Builder
    {
        [$start, $end] = $this->periodRange();
        $query->where($date, '>=', $start)->where($date, '<', $end);
        if ($this->cabang) {
            $query->where($branch, $this->cabang);
        }
        if ($user && $this->pengguna) {
            $query->where(fn (Builder $builder) => $builder->where($user, $this->pengguna)
                ->when(str_ends_with($user, 'diserahkan_ke_user_id'), fn (Builder $owner) => $owner->orWhere(str_replace('diserahkan_ke_user_id', 'diinput_oleh_user_id', $user), $this->pengguna)));
        }

        return $query;
    }

    private function queryReportData(): array
    {
        $viewer = auth()->user();
        [, , $period] = $this->periodRange();
        $leads = $this->scope(DB::table('leads'), 'tanggal_masuk', 'cabang_id', 'diserahkan_ke_user_id');
        $registrations = $this->scope(DB::table('pendaftaran')->join('leads', 'leads.lead_id', '=', 'pendaftaran.lead_id'), 'pendaftaran.tanggal_daftar', 'pendaftaran.cabang_id', 'leads.diserahkan_ke_user_id');
        $payments = $this->scope(DB::table('pembayaran')->join('pendaftaran', 'pendaftaran.pendaftaran_id', '=', 'pembayaran.pendaftaran_id')->join('leads', 'leads.lead_id', '=', 'pendaftaran.lead_id')->whereIn('pembayaran.status_pembayaran', ['Lunas', 'Terverifikasi']), 'pembayaran.tanggal_pembayaran', 'pendaftaran.cabang_id', 'leads.diserahkan_ke_user_id');

        $leadCount = (clone $leads)->count();
        $registrationCount = (clone $registrations)->count();
        $revenue = (float) (clone $payments)->sum('pembayaran.nominal');
        $stats = [
            'leads' => $leadCount,
            'registrations' => $registrationCount,
            'conversion' => $leadCount > 0 ? round($registrationCount / $leadCount * 100, 1) : 0,
            'revenue' => $revenue,
            'average_ticket' => $registrationCount > 0 ? $revenue / $registrationCount : 0,
        ];

        $dayLead = DB::getDriverName() === 'sqlite' ? "CAST(strftime('%d', tanggal_masuk) AS INTEGER)" : 'DAY(tanggal_masuk)';
        $dayRegistration = DB::getDriverName() === 'sqlite' ? "CAST(strftime('%d', pendaftaran.tanggal_daftar) AS INTEGER)" : 'DAY(pendaftaran.tanggal_daftar)';
        $leadTrend = (clone $leads)->selectRaw("{$dayLead} hari, COUNT(*) total")->groupByRaw($dayLead)->pluck('total', 'hari');
        $registrationTrend = (clone $registrations)->selectRaw("{$dayRegistration} hari, COUNT(*) total")->groupByRaw($dayRegistration)->pluck('total', 'hari');
        $days = collect(range(1, $period->daysInMonth));

        $branchOptions = Cabang::query()->where('aktif', true)->when(! $viewer->hasGlobalBranchAccess(), fn ($query) => $query->whereKey($viewer->cabang_id))->orderBy('nama')->get();
        $reportBranches = $branchOptions->when($this->cabang, fn (Collection $items) => $items->where('cabang_id', $this->cabang));
        $leadsByBranch = (clone $leads)->selectRaw('cabang_id, COUNT(*) total')->groupBy('cabang_id')->pluck('total', 'cabang_id');
        $registrationsByBranch = (clone $registrations)->selectRaw('pendaftaran.cabang_id, COUNT(*) total')->groupBy('pendaftaran.cabang_id')->pluck('total', 'pendaftaran.cabang_id');
        $revenueByBranch = (clone $payments)->selectRaw('pendaftaran.cabang_id, COALESCE(SUM(pembayaran.nominal), 0) total')->groupBy('pendaftaran.cabang_id')->pluck('total', 'pendaftaran.cabang_id');
        $targets = $this->targetByBranch();
        $branchesReport = $reportBranches->map(function (Cabang $branch) use ($leadsByBranch, $registrationsByBranch, $revenueByBranch, $targets): array {
            $branchLeads = (int) ($leadsByBranch[$branch->cabang_id] ?? 0);
            $branchRegistrations = (int) ($registrationsByBranch[$branch->cabang_id] ?? 0);
            $branchRevenue = (float) ($revenueByBranch[$branch->cabang_id] ?? 0);
            $target = $targets->get($branch->cabang_id);
            $targetRevenue = (float) ($target->target_revenue ?? 0);
            $targetStudents = (int) ($target->target_students ?? 0);

            return [
                'name' => $branch->nama, 'leads' => $branchLeads, 'registrations' => $branchRegistrations,
                'conversion' => $branchLeads > 0 ? round($branchRegistrations / $branchLeads * 100, 1) : 0,
                'revenue' => $branchRevenue, 'target_revenue' => $targetRevenue,
                'revenue_progress' => $targetRevenue > 0 ? round($branchRevenue / $targetRevenue * 100, 1) : 0,
                'target_students' => $targetStudents,
                'student_progress' => $targetStudents > 0 ? round($branchRegistrations / $targetStudents * 100, 1) : 0,
            ];
        })->values();

        $programRegistrations = (clone $registrations)->join('program_bimbel', 'program_bimbel.program_bimbel_id', '=', 'pendaftaran.program_bimbel_id')->selectRaw('program_bimbel.program_bimbel_id, program_bimbel.nama_program, COUNT(*) registrations')->groupBy('program_bimbel.program_bimbel_id', 'program_bimbel.nama_program')->get()->keyBy('program_bimbel_id');
        $programRevenue = (clone $payments)->join('program_bimbel', 'program_bimbel.program_bimbel_id', '=', 'pendaftaran.program_bimbel_id')->selectRaw('program_bimbel.program_bimbel_id, COALESCE(SUM(pembayaran.nominal), 0) revenue')->groupBy('program_bimbel.program_bimbel_id')->pluck('revenue', 'program_bimbel_id');
        $programsReport = $programRegistrations->map(fn ($row) => ['name' => $row->nama_program, 'registrations' => (int) $row->registrations, 'revenue' => (float) ($programRevenue[$row->program_bimbel_id] ?? 0)])->sortByDesc('registrations')->values();

        return [
            'branches' => $branchOptions,
            'users' => User::query()->when($this->cabang, fn ($query) => $query->where('cabang_id', $this->cabang))->when($viewer->hasRole(UserRole::Staff), fn ($query) => $query->whereKey($viewer->id))->whereIn('role', [UserRole::AdminCabang->value, UserRole::Staff->value])->where('aktif', true)->orderBy('name')->get(),
            'years' => range(now()->year + 1, now()->year - 4),
            'stats' => $stats,
            'periodLabel' => $period->translatedFormat('F Y'),
            'days' => $days,
            'trendLeads' => $days->map(fn ($day) => (int) ($leadTrend[$day] ?? 0)),
            'trendRegistrations' => $days->map(fn ($day) => (int) ($registrationTrend[$day] ?? 0)),
            'funnel' => (clone $leads)->select('status_lead')->selectRaw('COUNT(*) total')->groupBy('status_lead')->orderByDesc('total')->get(),
            'paymentMethods' => (clone $payments)->select('pembayaran.metode_pembayaran')->selectRaw('COUNT(*) transactions, COALESCE(SUM(pembayaran.nominal), 0) total')->groupBy('pembayaran.metode_pembayaran')->orderByDesc('total')->get(),
            'branchesReport' => $branchesReport,
            'programsReport' => $programsReport,
            'hasData' => $leadCount > 0 || $registrationCount > 0 || $revenue > 0,
        ];
    }

    private function targetByBranch(): Collection
    {
        if ($this->pengguna) {
            return DB::table('target_kinerja_user')->join('target_kinerja_cabang', 'target_kinerja_cabang.target_kinerja_cabang_id', '=', 'target_kinerja_user.target_kinerja_cabang_id')->where('target_kinerja_user.user_id', $this->pengguna)->when($this->cabang, fn ($query) => $query->where('target_kinerja_cabang.cabang_id', $this->cabang))->where(['target_kinerja_cabang.tahun' => $this->tahun, 'target_kinerja_cabang.bulan' => $this->bulan, 'target_kinerja_cabang.status' => 'Aktif'])->selectRaw('target_kinerja_cabang.cabang_id, SUM(target_kinerja_user.target_omzet) target_revenue, SUM(target_kinerja_user.target_siswa) target_students')->groupBy('target_kinerja_cabang.cabang_id')->get()->keyBy('cabang_id');
        }

        return DB::table('target_kinerja_cabang')->when($this->cabang, fn ($query) => $query->where('cabang_id', $this->cabang))->where(['tahun' => $this->tahun, 'bulan' => $this->bulan, 'status' => 'Aktif'])->selectRaw('cabang_id, SUM(target_omzet) target_revenue, SUM(target_siswa) target_students')->groupBy('cabang_id')->get()->keyBy('cabang_id');
    }

    private function emptyReportData(): array
    {
        $days = collect(range(1, now()->daysInMonth));

        return ['branches' => collect(), 'users' => collect(), 'years' => [now()->year], 'stats' => ['leads' => 0, 'registrations' => 0, 'conversion' => 0, 'revenue' => 0, 'average_ticket' => 0], 'periodLabel' => '-', 'days' => $days, 'trendLeads' => $days->map(fn () => 0), 'trendRegistrations' => $days->map(fn () => 0), 'funnel' => collect(), 'paymentMethods' => collect(), 'branchesReport' => collect(), 'programsReport' => collect(), 'hasData' => false];
    }
}
