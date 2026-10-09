<?php

namespace App\Filament\Pages;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Cabang;
use App\Models\CrmSetting;
use App\Models\User;
use App\Support\IndonesiaCityCoordinates;
use Filament\Pages\Page;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Throwable;

class Dashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'SIVMI CRM';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Dashboard';

    protected string $view = 'filament.pages.dashboard';

    #[Url]
    public ?int $cabang = null;

    #[Url]
    public int $tahun;

    #[Url(keep: true)]
    public ?int $bulan = null;

    #[Url]
    public ?int $pengguna = null;

    public ?string $dashboardError = null;

    public function mount(): void
    {
        $this->tahun = $this->tahun ?? now()->year;
        if (! request()->query->has('bulan')) {
            $this->bulan = now()->month;
        }

        if ($this->bulan !== null && ($this->bulan < 1 || $this->bulan > 12)) {
            $this->bulan = now()->month;
        }
        $user = auth()->user();
        if (! $user->hasGlobalBranchAccess()) {
            $this->cabang = $user->cabang_id;
        }
        if ($user->hasRole(UserRole::Staff)) {
            // Staff melihat ringkasan seluruh data dalam cabangnya. Filter
            // pengguna sengaja diabaikan agar parameter URL tidak dapat
            // membatasi atau mengalihkan dashboard ke pengguna tertentu.
            $this->pengguna = null;
        }
    }

    private function scope(Builder $query, string $date, ?string $branch = 'cabang_id', ?string $user = null): Builder
    {
        [$start, $endExclusive] = $this->periodRange();

        return $this->scopeRange($query, $date, $start, $endExclusive, $branch, $user);
    }

    private function scopeRange(Builder $query, string $date, string $start, string $endExclusive, ?string $branch = 'cabang_id', ?string $user = null): Builder
    {
        $query->where($date, '>=', $start)->where($date, '<', $endExclusive);
        if ($branch && $this->cabang) {
            $query->where($branch, $this->cabang);
        }
        if ($user && $this->pengguna) {
            $query->where(fn (Builder $q) => $q->where($user, $this->pengguna)
                ->when($user === 'diserahkan_ke_user_id', fn (Builder $leadQuery) => $leadQuery->orWhere('diinput_oleh_user_id', $this->pengguna)));
        }

        return $query;
    }

    private function periodRange(): array
    {
        $start = $this->bulan
            ? now()->setDate($this->tahun, $this->bulan, 1)->startOfMonth()
            : now()->setDate($this->tahun, 1, 1)->startOfYear();

        $endExclusive = $this->bulan
            ? $start->copy()->addMonth()
            : $start->copy()->addYear();

        return [$start->toDateString(), $endExclusive->toDateString()];
    }

    private function previousPeriodRange(): array
    {
        [$start] = $this->periodRange();
        $endExclusive = now()->parse($start);
        $previousStart = $this->bulan
            ? $endExclusive->copy()->subMonth()
            : $endExclusive->copy()->subYear();

        return [$previousStart->toDateString(), $endExclusive->toDateString()];
    }

    private function percentageChange(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? 0.0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    private function leadIdsForSelectedUser(): Builder
    {
        return DB::table('leads')->select('lead_id')->where(fn (Builder $query) => $query
            ->where('diserahkan_ke_user_id', $this->pengguna)
            ->orWhere('diinput_oleh_user_id', $this->pengguna));
    }

    public function getViewData(): array
    {
        try {
            $this->dashboardError = null;

            return $this->queryDashboardData();
        } catch (Throwable $exception) {
            report($exception);
            $this->dashboardError = 'Data dashboard belum dapat dimuat. Silakan coba kembali beberapa saat lagi.';

            return $this->emptyDashboardData();
        }
    }

    private function queryDashboardData(): array
    {
        $viewer = auth()->user();
        if (! $viewer->hasGlobalBranchAccess()) {
            $this->cabang = $viewer->cabang_id;
        }
        if ($viewer->hasRole(UserRole::Staff)) {
            $this->pengguna = null;
        }
        $leads = $this->scope(DB::table('leads'), 'tanggal_masuk', 'cabang_id', 'diserahkan_ke_user_id');
        $registrations = $this->scope(DB::table('pendaftaran'), 'tanggal_daftar', 'pendaftaran.cabang_id');
        if ($this->pengguna) {
            $registrations->whereIn('pendaftaran.lead_id', $this->leadIdsForSelectedUser());
        }
        $payments = DB::table('pembayaran')->join('pendaftaran', 'pendaftaran.pendaftaran_id', '=', 'pembayaran.pendaftaran_id')->whereIn('status_pembayaran', ['Lunas', 'Terverifikasi']);
        $this->scope($payments, 'tanggal_pembayaran', 'pendaftaran.cabang_id');
        if ($this->pengguna) {
            $payments->whereIn('pendaftaran.lead_id', $this->leadIdsForSelectedUser());
        }
        $followups = DB::table('followup_riwayat')->join('leads', 'leads.lead_id', '=', 'followup_riwayat.lead_id');
        $this->scope($followups, 'followup_riwayat.tanggal_followup', 'leads.cabang_id', 'followup_riwayat.user_id');
        $todayPayments = clone $payments;
        $target = $this->pengguna
            ? DB::table('target_kinerja_user')->join('target_kinerja_cabang', 'target_kinerja_cabang.target_kinerja_cabang_id', '=', 'target_kinerja_user.target_kinerja_cabang_id')->where('target_kinerja_user.user_id', $this->pengguna)->when($this->cabang, fn ($q) => $q->where('target_kinerja_cabang.cabang_id', $this->cabang))->where(['target_kinerja_cabang.tahun' => $this->tahun, 'target_kinerja_cabang.status' => 'Aktif'])->when($this->bulan, fn ($q) => $q->where('target_kinerja_cabang.bulan', $this->bulan))->selectRaw('COALESCE(SUM(target_kinerja_user.target_omzet),0) omzet, COALESCE(SUM(target_kinerja_user.target_siswa),0) siswa')->first()
            : DB::table('target_kinerja_cabang')->when($this->cabang, fn ($q) => $q->where('cabang_id', $this->cabang))->where(['tahun' => $this->tahun, 'status' => 'Aktif'])->when($this->bulan, fn ($q) => $q->where('bulan', $this->bulan))->selectRaw('COALESCE(SUM(target_omzet),0) omzet, COALESCE(SUM(target_siswa),0) siswa')->first();
        $periodPart = $this->bulan ? '%d' : '%m';
        $periodFunction = $this->bulan ? 'DAY' : 'MONTH';
        $leadPeriod = DB::getDriverName() === 'sqlite' ? "CAST(strftime('{$periodPart}', tanggal_masuk) AS INTEGER)" : "{$periodFunction}(tanggal_masuk)";
        $registrationPeriod = DB::getDriverName() === 'sqlite' ? "CAST(strftime('{$periodPart}', tanggal_daftar) AS INTEGER)" : "{$periodFunction}(tanggal_daftar)";
        $trendLeads = (clone $leads)->selectRaw("{$leadPeriod} periode, COUNT(*) total")->groupByRaw($leadPeriod)->pluck('total', 'periode');
        $trendRegs = (clone $registrations)->selectRaw("{$registrationPeriod} periode, COUNT(*) total")->groupByRaw($registrationPeriod)->pluck('total', 'periode');
        $programLeadRows = (clone $leads)
            ->leftJoin('program_bimbel', 'program_bimbel.program_bimbel_id', '=', 'leads.minat_program_id')
            ->select([
                'program_bimbel.program_bimbel_id',
                'program_bimbel.nama_program',
                'program_bimbel.kategori_program',
                'program_bimbel.tipe_program',
                'program_bimbel.metode_belajar',
                'program_bimbel.program_kelas',
                'program_bimbel.jenjang_program',
            ])
            ->selectRaw('COUNT(leads.lead_id) total')
            ->groupBy([
                'program_bimbel.program_bimbel_id',
                'program_bimbel.nama_program',
                'program_bimbel.kategori_program',
                'program_bimbel.tipe_program',
                'program_bimbel.metode_belajar',
                'program_bimbel.program_kelas',
                'program_bimbel.jenjang_program',
            ])
            ->orderByDesc('total')
            ->get();

        $programRegistrations = (clone $registrations)
            ->selectRaw('program_bimbel_id, COUNT(*) total')
            ->groupBy('program_bimbel_id')
            ->pluck('total', 'program_bimbel_id');
        $programRevenue = (clone $payments)
            ->selectRaw('pendaftaran.program_bimbel_id, COALESCE(SUM(pembayaran.nominal), 0) total')
            ->groupBy('pendaftaran.program_bimbel_id')
            ->pluck('total', 'pendaftaran.program_bimbel_id');

        $compactBreakdown = function ($items, int $maximum = 7) {
            $items = $items->sortByDesc('total')->values();

            if ($items->count() <= $maximum) {
                return $items;
            }

            return $items->take($maximum - 1)->push((object) [
                'label' => 'Lainnya',
                'total' => (int) $items->slice($maximum - 1)->sum('total'),
                'registrations' => (int) $items->slice($maximum - 1)->sum('registrations'),
                'revenue' => (float) $items->slice($maximum - 1)->sum('revenue'),
            ]);
        };

        $programLeadKpis = $programLeadRows
            ->map(function (object $row) use ($programRegistrations, $programRevenue): object {
                $row->program = $row->nama_program ?: 'Belum memilih program';
                $row->label = $row->program;
                $row->total = (int) $row->total;
                $row->registrations = (int) ($programRegistrations[$row->program_bimbel_id] ?? 0);
                $row->revenue = (float) ($programRevenue[$row->program_bimbel_id] ?? 0);
                $row->conversion = $row->total > 0 ? round($row->registrations / $row->total * 100, 1) : 0;

                return $row;
            });

        $programLeadKpis = $compactBreakdown($programLeadKpis)->map(function (object $row): object {
            $row->program = $row->label === 'Lainnya' ? 'Program lainnya' : $row->label;
            $row->registrations ??= 0;
            $row->revenue ??= 0;
            $row->conversion = $row->total > 0 ? round($row->registrations / $row->total * 100, 1) : 0;

            return $row;
        });

        $programDimensionDefinitions = [
            'kategori_program' => ['label' => 'Kategori', 'empty' => 'Belum ada kategori'],
            'tipe_program' => ['label' => 'Tipe program', 'empty' => 'Belum ada tipe program'],
            'metode_belajar' => ['label' => 'Metode belajar', 'empty' => 'Belum ada metode belajar'],
            'program_kelas' => ['label' => 'Paket program', 'empty' => 'Belum ada paket program'],
            'jenjang_program' => ['label' => 'Jenjang', 'empty' => 'Belum ada jenjang'],
        ];
        $programLeadDimensions = collect($programDimensionDefinitions)->map(function (array $definition, string $column) use ($programLeadRows, $compactBreakdown): array {
            $items = $programLeadRows
                ->groupBy(fn (object $row): string => filled($row->{$column}) ? (string) $row->{$column} : $definition['empty'])
                ->map(fn ($rows, string $label): object => (object) [
                    'label' => $label,
                    'total' => (int) $rows->sum('total'),
                ]);

            return [
                'label' => $definition['label'],
                'items' => $compactBreakdown($items, 6),
            ];
        });
        $topSchools = (clone $leads)
            ->join('sekolah', 'sekolah.sekolah_id', '=', 'leads.sekolah_id')
            ->groupBy('sekolah.sekolah_id', 'sekolah.nama_sekolah', 'sekolah.kota')
            ->select('sekolah.nama_sekolah', 'sekolah.kota')
            ->selectRaw('COUNT(leads.lead_id) total')
            ->orderByDesc('total')
            ->orderBy('sekolah.nama_sekolah')
            ->limit(5)
            ->get();
        $cityExpression = "COALESCE(NULLIF(TRIM(leads.kota_asal), ''), NULLIF(TRIM(sekolah.kota), ''))";
        $leadLocations = (clone $leads)
            ->leftJoin('sekolah', 'sekolah.sekolah_id', '=', 'leads.sekolah_id')
            ->selectRaw("leads.lead_id, {$cityExpression} kota");
        $cityRows = DB::query()
            ->fromSub($leadLocations, 'lead_locations')
            ->whereNotNull('kota')
            ->groupBy('kota')
            ->select('kota')
            ->selectRaw('COUNT(lead_id) total')
            ->orderByDesc('total')
            ->orderBy('kota')
            ->get();
        $topCities = $cityRows->take(5);
        $leadMapPoints = $cityRows
            ->map(function (object $row): ?array {
                $coordinates = IndonesiaCityCoordinates::find($row->kota);

                if (! $coordinates) {
                    return null;
                }

                return [
                    'city' => (string) $row->kota,
                    'latitude' => $coordinates['latitude'],
                    'longitude' => $coordinates['longitude'],
                    'total' => (int) $row->total,
                ];
            })
            ->filter()
            ->values();
        $mappedLeadCount = (int) $leadMapPoints->sum('total');
        $locatedLeadCount = (int) $cityRows->sum('total');
        $totalLeadCountForMap = (int) (clone $leads)->count();
        $mapCoverage = $totalLeadCountForMap > 0 ? round($mappedLeadCount / $totalLeadCountForMap * 100, 1) : 0;
        $unlocatedLeadCount = max(0, $totalLeadCountForMap - $locatedLeadCount);
        $unmappedCities = $cityRows
            ->reject(fn (object $row): bool => IndonesiaCityCoordinates::find($row->kota) !== null)
            ->pluck('kota')
            ->values();
        $leadFunnelCounts = (clone $leads)
            ->selectRaw('status_lead, COUNT(*) total')
            ->groupBy('status_lead')
            ->pluck('total', 'status_lead');
        $leadFunnel = collect(LeadStatus::cases())->map(fn (LeadStatus $status): object => (object) [
            'label' => $status->value,
            'total' => (int) ($leadFunnelCounts[$status->value] ?? 0),
        ]);
        $usesNormalizedLeadSources = Schema::hasTable('sumber_leads')
            && Schema::hasColumn('leads', 'sumber_lead_id');
        $sourceColumn = $usesNormalizedLeadSources ? 'sumber_leads.nama' : 'leads.sumber_lead';
        $sourceGroupColumns = $usesNormalizedLeadSources
            ? ['leads.sumber_lead_id', 'sumber_leads.nama']
            : ['leads.sumber_lead'];
        $sourceLeads = clone $leads;
        $sourceRegistrations = (clone $registrations)
            ->join('leads', 'leads.lead_id', '=', 'pendaftaran.lead_id');
        $sourceRevenue = (clone $payments)
            ->join('leads', 'leads.lead_id', '=', 'pendaftaran.lead_id');

        if ($usesNormalizedLeadSources) {
            $sourceLeads->leftJoin('sumber_leads', 'sumber_leads.sumber_lead_id', '=', 'leads.sumber_lead_id');
            $sourceRegistrations->leftJoin('sumber_leads', 'sumber_leads.sumber_lead_id', '=', 'leads.sumber_lead_id');
            $sourceRevenue->leftJoin('sumber_leads', 'sumber_leads.sumber_lead_id', '=', 'leads.sumber_lead_id');
        }

        $sourceLeads = $sourceLeads
            ->selectRaw("COALESCE({$sourceColumn}, 'Belum diisi') sumber, COUNT(*) total")
            ->groupBy(...$sourceGroupColumns)
            ->orderByDesc('total')
            ->get();
        $sourceRegistrations = $sourceRegistrations
            ->selectRaw("COALESCE({$sourceColumn}, 'Belum diisi') sumber, COUNT(*) total")
            ->groupBy(...$sourceGroupColumns)
            ->pluck('total', 'sumber');
        $sourceRevenue = $sourceRevenue
            ->selectRaw("COALESCE({$sourceColumn}, 'Belum diisi') sumber, COALESCE(SUM(pembayaran.nominal), 0) total")
            ->groupBy(...$sourceGroupColumns)
            ->pluck('total', 'sumber');
        $leadSources = $sourceLeads->map(function (object $source) use ($sourceRegistrations, $sourceRevenue): object {
            $source->total = (int) $source->total;
            $source->registrations = (int) ($sourceRegistrations[$source->sumber] ?? 0);
            $source->revenue = (float) ($sourceRevenue[$source->sumber] ?? 0);
            $source->conversion = $source->total > 0 ? round($source->registrations / $source->total * 100, 1) : 0;

            return $source;
        });
        $periods = $this->bulan
            ? range(1, now()->setDate($this->tahun, $this->bulan, 1)->daysInMonth)
            : range(1, 12);
        [$periodStart, $periodEndExclusive] = $this->periodRange();
        $ranking = DB::table('users')->leftJoin('leads', function ($join) use ($periodStart, $periodEndExclusive) {
            $join->on('leads.diserahkan_ke_user_id', '=', 'users.id')->where('leads.tanggal_masuk', '>=', $periodStart)->where('leads.tanggal_masuk', '<', $periodEndExclusive);
        })->when($this->cabang, fn ($q) => $q->where('users.cabang_id', $this->cabang))->when($this->pengguna, fn ($q) => $q->where('users.id', $this->pengguna))->whereIn('users.role', [UserRole::Staff->value, UserRole::AdminCabang->value])->groupBy('users.id', 'users.name')->selectRaw("users.name, COUNT(leads.lead_id) leads, SUM(CASE WHEN leads.status_lead = 'Daftar' THEN 1 ELSE 0 END) registrations")->orderByDesc('registrations')->orderByDesc('leads')->limit(5)->get()->map(function (object $person): object {
            $person->conversion = $person->leads > 0 ? round($person->registrations / $person->leads * 100, 1) : 0;

            return $person;
        });
        $activities = DB::table('leads')->leftJoin('users', 'users.id', '=', 'leads.diserahkan_ke_user_id')->where('leads.tanggal_masuk', '>=', $periodStart)->where('leads.tanggal_masuk', '<', $periodEndExclusive)->when($this->cabang, fn ($q) => $q->where('leads.cabang_id', $this->cabang))->when($this->pengguna, fn ($q) => $q->where(fn ($leadQuery) => $leadQuery->where('leads.diserahkan_ke_user_id', $this->pengguna)->orWhere('leads.diinput_oleh_user_id', $this->pengguna)))->select('leads.nama', 'leads.status_lead', 'leads.created_at', 'users.name as petugas')->latest('leads.created_at')->limit(6)->get();
        $stats = ['leads_today' => (clone $leads)->whereDate('tanggal_masuk', today())->count(), 'followups_today' => (clone $followups)->whereDate('tanggal_followup', today())->count(), 'new_students' => (clone $registrations)->whereDate('tanggal_daftar', today())->count(), 'revenue_today' => (float) $todayPayments->whereDate('tanggal_pembayaran', today())->sum('nominal'), 'leads' => (clone $leads)->count(), 'registrations' => (clone $registrations)->count(), 'students' => (clone $registrations)->where('status_pendaftaran', 'Aktif')->count(), 'revenue' => (float) (clone $payments)->sum('nominal')];
        $stats['conversion'] = $stats['leads'] > 0 ? round($stats['registrations'] / $stats['leads'] * 100, 1) : 0;
        [$previousStart, $previousEndExclusive] = $this->previousPeriodRange();
        $previousLeads = $this->scopeRange(DB::table('leads'), 'tanggal_masuk', $previousStart, $previousEndExclusive, 'cabang_id', 'diserahkan_ke_user_id');
        $previousRegistrations = $this->scopeRange(DB::table('pendaftaran'), 'tanggal_daftar', $previousStart, $previousEndExclusive, 'pendaftaran.cabang_id');
        if ($this->pengguna) {
            $previousRegistrations->whereIn('pendaftaran.lead_id', $this->leadIdsForSelectedUser());
        }
        $previousPayments = DB::table('pembayaran')->join('pendaftaran', 'pendaftaran.pendaftaran_id', '=', 'pembayaran.pendaftaran_id')->whereIn('status_pembayaran', ['Lunas', 'Terverifikasi']);
        $this->scopeRange($previousPayments, 'tanggal_pembayaran', $previousStart, $previousEndExclusive, 'pendaftaran.cabang_id');
        if ($this->pengguna) {
            $previousPayments->whereIn('pendaftaran.lead_id', $this->leadIdsForSelectedUser());
        }
        $previousStats = [
            'leads' => (clone $previousLeads)->count(),
            'registrations' => (clone $previousRegistrations)->count(),
            'students' => (clone $previousRegistrations)->where('status_pendaftaran', 'Aktif')->count(),
            'revenue' => (float) (clone $previousPayments)->sum('nominal'),
        ];
        $previousStats['conversion'] = $previousStats['leads'] > 0 ? round($previousStats['registrations'] / $previousStats['leads'] * 100, 1) : 0;
        $comparisons = collect(['leads', 'conversion', 'students', 'revenue'])->mapWithKeys(fn (string $metric): array => [$metric => $this->percentageChange($stats[$metric], $previousStats[$metric])])->all();
        $periodStartDate = now()->parse($periodStart)->startOfDay();
        $periodEndDate = now()->parse($periodEndExclusive)->startOfDay();
        $elapsedDays = max(0, min($periodStartDate->diffInDays($periodEndDate), $periodStartDate->diffInDays(now()->startOfDay(), false)));
        $targetPace = $periodStartDate->gte(now()->startOfDay()) ? 0 : min(100, round($elapsedDays / max(1, $periodStartDate->diffInDays($periodEndDate)) * 100));
        $pendingPayments = DB::table('pembayaran')->join('pendaftaran', 'pendaftaran.pendaftaran_id', '=', 'pembayaran.pendaftaran_id')->whereNotIn('status_pembayaran', ['Lunas', 'Terverifikasi']);
        $this->scope($pendingPayments, 'pembayaran.tanggal_pembayaran', 'pendaftaran.cabang_id');
        if ($this->pengguna) {
            $pendingPayments->whereIn('pendaftaran.lead_id', $this->leadIdsForSelectedUser());
        }

        return ['branches' => Cabang::where('aktif', true)->when(! $viewer->hasGlobalBranchAccess(), fn ($q) => $q->whereKey($viewer->cabang_id))->orderBy('nama')->get(), 'users' => User::when($this->cabang, fn ($q) => $q->where('cabang_id', $this->cabang))->when($viewer->hasRole(UserRole::Staff), fn ($q) => $q->whereKey($viewer->id))->whereIn('role', [UserRole::Staff->value, UserRole::AdminCabang->value])->where('aktif', true)->orderBy('name')->get(), 'dashboardTagline' => CrmSetting::getValue('dashboard_tagline'), 'stats' => $stats, 'previousStats' => $previousStats, 'comparisons' => $comparisons, 'target' => $target, 'targetPace' => $targetPace, 'ranking' => $ranking, 'activities' => $activities, 'days' => $periods, 'trendLeads' => collect($periods)->map(fn ($period) => $trendLeads[$period] ?? 0), 'trendRegs' => collect($periods)->map(fn ($period) => $trendRegs[$period] ?? 0), 'leadFunnel' => $leadFunnel, 'leadSources' => $leadSources, 'programLeadKpis' => $programLeadKpis, 'programLeadDimensions' => $programLeadDimensions, 'topSchools' => $topSchools, 'topCities' => $topCities, 'leadMapPoints' => $leadMapPoints, 'mappedLeadCount' => $mappedLeadCount, 'locatedLeadCount' => $locatedLeadCount, 'mapCoverage' => $mapCoverage, 'unlocatedLeadCount' => $unlocatedLeadCount, 'unmappedCities' => $unmappedCities, 'pending' => ['followup' => (clone $followups)->whereNotNull('tanggal_followup_berikutnya')->where('tanggal_followup_berikutnya', '<=', now())->count(), 'payments' => $pendingPayments->count(), 'new_leads' => (clone $leads)->where('status_lead', LeadStatus::Cold->value)->count()]];
    }

    private function emptyDashboardData(): array
    {
        $days = $this->bulan
            ? range(1, now()->setDate($this->tahun ?? now()->year, $this->bulan, 1)->daysInMonth)
            : range(1, 12);

        return [
            'branches' => collect(),
            'users' => collect(),
            'dashboardTagline' => CrmSetting::getValue('dashboard_tagline'),
            'stats' => [
                'leads_today' => 0,
                'followups_today' => 0,
                'new_students' => 0,
                'revenue_today' => 0,
                'leads' => 0,
                'registrations' => 0,
                'students' => 0,
                'revenue' => 0,
                'conversion' => 0,
            ],
            'previousStats' => ['leads' => 0, 'registrations' => 0, 'students' => 0, 'revenue' => 0, 'conversion' => 0],
            'comparisons' => ['leads' => 0, 'conversion' => 0, 'students' => 0, 'revenue' => 0],
            'target' => (object) ['omzet' => 0, 'siswa' => 0],
            'targetPace' => 0,
            'ranking' => collect(),
            'activities' => collect(),
            'days' => $days,
            'trendLeads' => collect($days)->map(fn () => 0),
            'trendRegs' => collect($days)->map(fn () => 0),
            'leadFunnel' => collect(LeadStatus::cases())->map(fn (LeadStatus $status): object => (object) ['label' => $status->value, 'total' => 0]),
            'leadSources' => collect(),
            'programLeadKpis' => collect(),
            'programLeadDimensions' => collect([
                'kategori_program' => ['label' => 'Kategori', 'items' => collect()],
                'tipe_program' => ['label' => 'Tipe program', 'items' => collect()],
                'metode_belajar' => ['label' => 'Metode belajar', 'items' => collect()],
                'program_kelas' => ['label' => 'Paket program', 'items' => collect()],
                'jenjang_program' => ['label' => 'Jenjang', 'items' => collect()],
            ]),
            'topSchools' => collect(),
            'topCities' => collect(),
            'leadMapPoints' => collect(),
            'mappedLeadCount' => 0,
            'locatedLeadCount' => 0,
            'mapCoverage' => 0,
            'unlocatedLeadCount' => 0,
            'unmappedCities' => collect(),
            'pending' => ['followup' => 0, 'payments' => 0, 'new_leads' => 0],
        ];
    }
}
