<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\Cabang;
use App\Models\CrmSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Pages\Page;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
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

    #[Url(keep: true)]
    public ?int $bulan = null;

    #[Url]
    public ?int $pengguna = null;

    public ?string $reportError = null;

    public function mount(): void
    {
        $this->tahun = $this->tahun ?? now()->year;
        if (! request()->query->has('bulan')) {
            $this->bulan = now()->month;
        }

        if ($this->bulan !== null && ($this->bulan < 1 || $this->bulan > 12)) {
            $this->bulan = now()->month;
        }
        $this->enforceViewerScope();
    }

    public function updatedCabang(): void
    {
        if ($this->pengguna && ! User::query()->whereKey($this->pengguna)->when($this->cabang, fn ($query) => $query->where('cabang_id', $this->cabang))->exists()) {
            $this->pengguna = null;
        }
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless($this->canExport(), 403);
        $this->enforceViewerScope();
        $data = $this->queryReportData();
        $filename = $this->bulan
            ? sprintf('laporan-sivmi-%d-%02d.pdf', $this->tahun, $this->bulan)
            : sprintf('laporan-sivmi-%d-tahunan.pdf', $this->tahun);

        $content = Pdf::loadView('reports.laporan-pdf', [
            ...$data,
            'organizationName' => (string) CrmSetting::getValue('organization_name', 'SIVMI'),
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
            'branchLabel' => $this->cabang
                ? (string) ($data['branches']->firstWhere('cabang_id', $this->cabang)?->nama ?? 'Cabang terpilih')
                : 'Semua cabang',
        ])->setPaper('a4', 'landscape')->output();

        return response()->streamDownload(
            static function () use ($content): void {
                echo $content;
            },
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
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
        $start = $this->bulan
            ? now()->setDate($this->tahun, $this->bulan, 1)->startOfMonth()
            : now()->setDate($this->tahun, 1, 1)->startOfYear();

        $endExclusive = $this->bulan
            ? $start->copy()->addMonth()
            : $start->copy()->addYear();

        return [$start->toDateString(), $endExclusive->toDateString(), $start];
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

        $periodPart = $this->bulan ? '%d' : '%m';
        $periodFunction = $this->bulan ? 'DAY' : 'MONTH';
        $leadPeriod = DB::getDriverName() === 'sqlite' ? "CAST(strftime('{$periodPart}', tanggal_masuk) AS INTEGER)" : "{$periodFunction}(tanggal_masuk)";
        $registrationPeriod = DB::getDriverName() === 'sqlite' ? "CAST(strftime('{$periodPart}', pendaftaran.tanggal_daftar) AS INTEGER)" : "{$periodFunction}(pendaftaran.tanggal_daftar)";
        $leadTrend = (clone $leads)->selectRaw("{$leadPeriod} periode, COUNT(*) total")->groupByRaw($leadPeriod)->pluck('total', 'periode');
        $registrationTrend = (clone $registrations)->selectRaw("{$registrationPeriod} periode, COUNT(*) total")->groupByRaw($registrationPeriod)->pluck('total', 'periode');
        $periods = collect(range(1, $this->bulan ? $period->daysInMonth : 12));

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
        $leadsByProgram = (clone $leads)
            ->leftJoin('program_bimbel as report_programs', 'report_programs.program_bimbel_id', '=', 'leads.minat_program_id')
            ->selectRaw("COALESCE(report_programs.nama_program, 'Program belum dipilih') name")
            ->selectRaw("COALESCE(report_programs.kategori_program, 'Tidak diklasifikasikan') classification")
            ->selectRaw('COUNT(*) total')
            ->groupBy('report_programs.program_bimbel_id', 'report_programs.nama_program', 'report_programs.kategori_program')
            ->orderByDesc('total')
            ->get();
        $programClassifications = $leadsByProgram
            ->groupBy('classification')
            ->map(fn (Collection $programs, string $classification): array => [
                'name' => $classification,
                'leads' => $programs->sum('total'),
            ])
            ->sortByDesc('leads')
            ->values();
        $topSchools = (clone $leads)
            ->join('sekolah as report_schools', 'report_schools.sekolah_id', '=', 'leads.sekolah_id')
            ->select('report_schools.nama_sekolah as name')
            ->selectRaw('COUNT(*) total')
            ->groupBy('report_schools.sekolah_id', 'report_schools.nama_sekolah')
            ->orderByDesc('total')
            ->orderBy('report_schools.nama_sekolah')
            ->limit(5)
            ->get();

        $reportUsers = User::query()
            ->when($this->cabang, fn ($query) => $query->where('cabang_id', $this->cabang))
            ->when($this->pengguna, fn ($query) => $query->whereKey($this->pengguna))
            ->when($viewer->hasRole(UserRole::Staff), fn ($query) => $query->whereKey($viewer->id))
            ->whereIn('role', [UserRole::AdminCabang->value, UserRole::Staff->value])
            ->where('aktif', true)
            ->orderBy('name')
            ->get();
        $userLeadRows = (clone $leads)
            ->selectRaw('COALESCE(leads.diserahkan_ke_user_id, leads.diinput_oleh_user_id) user_id')
            ->selectRaw('COUNT(*) leads')
            ->groupByRaw('COALESCE(leads.diserahkan_ke_user_id, leads.diinput_oleh_user_id)')
            ->get()
            ->keyBy('user_id');
        $userRegistrationRows = (clone $registrations)
            ->join('users as report_users', fn (JoinClause $join) => $join->on('report_users.id', '=', DB::raw('COALESCE(leads.diserahkan_ke_user_id, leads.diinput_oleh_user_id)')))
            ->select('report_users.id as user_id')
            ->selectRaw('COUNT(*) registrations')
            ->groupBy('report_users.id')
            ->get()
            ->keyBy('user_id');
        $userRevenueRows = (clone $payments)
            ->join('users as report_users', fn (JoinClause $join) => $join->on('report_users.id', '=', DB::raw('COALESCE(leads.diserahkan_ke_user_id, leads.diinput_oleh_user_id)')))
            ->select('report_users.id as user_id')
            ->selectRaw('COALESCE(SUM(pembayaran.nominal), 0) revenue')
            ->groupBy('report_users.id')
            ->get()
            ->keyBy('user_id');
        $userTargets = DB::table('target_kinerja_user')
            ->join('target_kinerja_cabang', 'target_kinerja_cabang.target_kinerja_cabang_id', '=', 'target_kinerja_user.target_kinerja_cabang_id')
            ->whereIn('target_kinerja_user.user_id', $reportUsers->modelKeys())
            ->when($this->cabang, fn ($query) => $query->where('target_kinerja_cabang.cabang_id', $this->cabang))
            ->where('target_kinerja_cabang.tahun', $this->tahun)
            ->when($this->bulan, fn ($query) => $query->where('target_kinerja_cabang.bulan', $this->bulan))
            ->whereIn('target_kinerja_cabang.status', ['Aktif', 'Selesai'])
            ->selectRaw('target_kinerja_user.user_id, SUM(target_kinerja_user.target_omzet) target_revenue, SUM(target_kinerja_user.target_siswa) target_students')
            ->groupBy('target_kinerja_user.user_id')
            ->get()
            ->keyBy('user_id');
        $userPerformance = $reportUsers->map(function (User $user) use ($userLeadRows, $userRegistrationRows, $userRevenueRows, $userTargets): array {
            $leads = (int) ($userLeadRows->get($user->id)->leads ?? 0);
            $registrations = (int) ($userRegistrationRows->get($user->id)->registrations ?? 0);
            $revenue = (float) ($userRevenueRows->get($user->id)->revenue ?? 0);
            $target = $userTargets->get($user->id);
            $targetRevenue = (float) ($target->target_revenue ?? 0);
            $targetStudents = (int) ($target->target_students ?? 0);

            return [
                'name' => $user->name,
                'leads' => $leads,
                'registrations' => $registrations,
                'revenue' => $revenue,
                'target_revenue' => $targetRevenue,
                'target_students' => $targetStudents,
                'revenue_progress' => $targetRevenue > 0 ? round($revenue / $targetRevenue * 100, 1) : 0,
                'student_progress' => $targetStudents > 0 ? round($registrations / $targetStudents * 100, 1) : 0,
            ];
        })->sort(function (array $left, array $right): int {
            return ($right['student_progress'] <=> $left['student_progress'])
                ?: ($right['registrations'] <=> $left['registrations'])
                ?: ($right['leads'] <=> $left['leads'])
                ?: strcmp($left['name'], $right['name']);
        })->values()->map(fn (array $row, int $index): array => ['rank' => $index + 1, ...$row]);

        return [
            'branches' => $branchOptions,
            'users' => User::query()->when($this->cabang, fn ($query) => $query->where('cabang_id', $this->cabang))->when($viewer->hasRole(UserRole::Staff), fn ($query) => $query->whereKey($viewer->id))->whereIn('role', [UserRole::AdminCabang->value, UserRole::Staff->value])->where('aktif', true)->orderBy('name')->get(),
            'years' => range(now()->year + 1, now()->year - 4),
            'stats' => $stats,
            'periodLabel' => $this->bulan ? $period->translatedFormat('F Y') : 'Tahun '.$this->tahun,
            'days' => $periods,
            'trendLeads' => $periods->map(fn ($selectedPeriod) => (int) ($leadTrend[$selectedPeriod] ?? 0)),
            'trendRegistrations' => $periods->map(fn ($selectedPeriod) => (int) ($registrationTrend[$selectedPeriod] ?? 0)),
            'funnel' => (clone $leads)->select('status_lead')->selectRaw('COUNT(*) total')->groupBy('status_lead')->orderByDesc('total')->get(),
            'paymentMethods' => (clone $payments)->select('pembayaran.metode_pembayaran')->selectRaw('COUNT(*) transactions, COALESCE(SUM(pembayaran.nominal), 0) total')->groupBy('pembayaran.metode_pembayaran')->orderByDesc('total')->get(),
            'branchesReport' => $branchesReport,
            'programsReport' => $programsReport,
            'leadsByProgram' => $leadsByProgram,
            'programClassifications' => $programClassifications,
            'topSchools' => $topSchools,
            'userPerformance' => $userPerformance,
            'hasData' => $leadCount > 0 || $registrationCount > 0 || $revenue > 0,
        ];
    }

    private function targetByBranch(): Collection
    {
        if ($this->pengguna) {
            return DB::table('target_kinerja_user')->join('target_kinerja_cabang', 'target_kinerja_cabang.target_kinerja_cabang_id', '=', 'target_kinerja_user.target_kinerja_cabang_id')->where('target_kinerja_user.user_id', $this->pengguna)->when($this->cabang, fn ($query) => $query->where('target_kinerja_cabang.cabang_id', $this->cabang))->where('target_kinerja_cabang.tahun', $this->tahun)->when($this->bulan, fn ($query) => $query->where('target_kinerja_cabang.bulan', $this->bulan))->whereIn('target_kinerja_cabang.status', ['Aktif', 'Selesai'])->selectRaw('target_kinerja_cabang.cabang_id, SUM(target_kinerja_user.target_omzet) target_revenue, SUM(target_kinerja_user.target_siswa) target_students')->groupBy('target_kinerja_cabang.cabang_id')->get()->keyBy('cabang_id');
        }

        return DB::table('target_kinerja_cabang')->when($this->cabang, fn ($query) => $query->where('cabang_id', $this->cabang))->where('tahun', $this->tahun)->when($this->bulan, fn ($query) => $query->where('bulan', $this->bulan))->whereIn('status', ['Aktif', 'Selesai'])->selectRaw('cabang_id, SUM(target_omzet) target_revenue, SUM(target_siswa) target_students')->groupBy('cabang_id')->get()->keyBy('cabang_id');
    }

    private function emptyReportData(): array
    {
        $periods = collect(range(1, $this->bulan ? now()->setDate($this->tahun, $this->bulan, 1)->daysInMonth : 12));
        $periodLabel = $this->bulan
            ? now()->setDate($this->tahun, $this->bulan, 1)->translatedFormat('F Y')
            : 'Tahun '.$this->tahun;

        return ['branches' => collect(), 'users' => collect(), 'years' => [now()->year], 'stats' => ['leads' => 0, 'registrations' => 0, 'conversion' => 0, 'revenue' => 0, 'average_ticket' => 0], 'periodLabel' => $periodLabel, 'days' => $periods, 'trendLeads' => $periods->map(fn () => 0), 'trendRegistrations' => $periods->map(fn () => 0), 'funnel' => collect(), 'paymentMethods' => collect(), 'branchesReport' => collect(), 'programsReport' => collect(), 'leadsByProgram' => collect(), 'programClassifications' => collect(), 'topSchools' => collect(), 'userPerformance' => collect(), 'hasData' => false];
    }
}
