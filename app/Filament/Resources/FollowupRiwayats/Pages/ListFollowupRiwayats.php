<?php

namespace App\Filament\Resources\FollowupRiwayats\Pages;

use App\Filament\Resources\FollowupRiwayats\FollowupRiwayatResource;
use App\Models\FollowupRiwayat;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Livewire\Attributes\Url;

class ListFollowupRiwayats extends ListRecords
{
    protected static string $resource = FollowupRiwayatResource::class;

    protected string $view = 'filament.resources.followup-riwayats.pages.list-followup-riwayats';

    #[Url(as: 'tahun')]
    public ?int $calendarYear = null;

    #[Url(as: 'bulan')]
    public ?int $calendarMonth = null;

    #[Url(as: 'tanggal')]
    public ?string $selectedDate = null;

    #[Url]
    public string $search = '';

    #[Url(as: 'metode')]
    public ?string $method = null;

    #[Url(as: 'terlambat')]
    public bool $overdueOnly = false;

    public function mount(): void
    {
        parent::mount();

        $today = CarbonImmutable::today();
        $selectedDate = $this->parseSelectedDate($this->selectedDate);
        if ($selectedDate) {
            $today = $selectedDate;
        }

        $this->calendarYear = $this->calendarYear && $this->calendarYear >= 2000 && $this->calendarYear <= 2100
            ? $this->calendarYear
            : $today->year;
        $this->calendarMonth = $this->calendarMonth && $this->calendarMonth >= 1 && $this->calendarMonth <= 12
            ? $this->calendarMonth
            : $today->month;
        $this->selectedDate = $selectedDate?->toDateString() ?? $today->toDateString();
        $this->setCalendarMonth($this->calendarDate());
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function previousMonth(): void
    {
        $this->setCalendarMonth($this->calendarDate()->subMonth());
    }

    public function nextMonth(): void
    {
        $this->setCalendarMonth($this->calendarDate()->addMonth());
    }

    public function previousWeek(): void
    {
        $this->selectDate(($this->parseSelectedDate($this->selectedDate) ?? $this->calendarDate())->subWeek()->toDateString());
    }

    public function nextWeek(): void
    {
        $this->selectDate(($this->parseSelectedDate($this->selectedDate) ?? $this->calendarDate())->addWeek()->toDateString());
    }

    public function goToCurrentMonth(): void
    {
        $this->selectDate(CarbonImmutable::today()->toDateString());
    }

    public function selectDate(string $date): void
    {
        $selectedDate = $this->parseSelectedDate($date);
        abort_unless($selectedDate, 422);

        $this->selectedDate = $selectedDate->toDateString();
        $this->setCalendarMonth($selectedDate);
    }

    protected function getViewData(): array
    {
        $monthStart = $this->calendarDate();
        $gridStart = $monthStart->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $monthEnd = $monthStart->endOfMonth();
        $selectedDay = $this->parseSelectedDate($this->selectedDate) ?? $monthStart;
        $weekStart = $selectedDay->startOfWeek(CarbonImmutable::MONDAY);
        $weekEnd = $weekStart->addDays(6);

        $followups = FollowupRiwayatResource::getEloquentQuery()
            ->whereNotNull('tanggal_followup_berikutnya')
            ->where(fn (Builder $query) => $query
                ->whereBetween('tanggal_followup_berikutnya', [$monthStart, $monthEnd->endOfDay()])
                ->orWhereBetween('tanggal_followup_berikutnya', [$weekStart, $weekEnd->endOfDay()]))
            ->whereNotExists(fn (QueryBuilder $query) => $query->selectRaw('1')
                ->from('followup_riwayat as newer_followups')
                ->whereColumn('newer_followups.lead_id', 'followup_riwayat.lead_id')
                ->where(fn (QueryBuilder $query) => $query
                    ->whereColumn('newer_followups.tanggal_followup', '>', 'followup_riwayat.tanggal_followup')
                    ->orWhere(fn (QueryBuilder $query) => $query
                        ->whereColumn('newer_followups.tanggal_followup', 'followup_riwayat.tanggal_followup')
                        ->whereColumn('newer_followups.followup_riwayat_id', '>', 'followup_riwayat.followup_riwayat_id'))))
            ->when(filled($this->search), fn (Builder $query) => $query->whereHas('lead', fn (Builder $leadQuery) => $leadQuery
                ->where('nama', 'like', '%'.$this->search.'%')
                ->orWhere('no_wa', 'like', '%'.$this->search.'%')))
            ->when($this->method, fn (Builder $query) => $query->where('metode_followup', $this->method))
            ->when($this->overdueOnly, fn (Builder $query) => $query->where('tanggal_followup_berikutnya', '<', now()))
            ->orderBy('tanggal_followup_berikutnya')
            ->get();

        $followupsByDate = $followups->groupBy(
            fn (FollowupRiwayat $followup): string => $followup->tanggal_followup_berikutnya->toDateString(),
        );
        $selectedDayEvents = $followupsByDate->get($selectedDay->toDateString(), collect());
        $weekDays = [];
        $weekEvents = collect();
        for ($date = $weekStart; $date <= $weekEnd; $date = $date->addDay()) {
            $events = $followupsByDate->get($date->toDateString(), collect());
            $weekDays[] = [
                'date' => $date,
                'events' => $events,
                'eventsByHour' => $events->groupBy(fn (FollowupRiwayat $followup): int => $followup->tanggal_followup_berikutnya->hour),
            ];
            $weekEvents = $weekEvents->concat($events);
        }

        $weeks = [];
        $week = [];
        for ($date = $gridStart; $date <= $gridEnd; $date = $date->addDay()) {
            $key = $date->toDateString();
            $week[] = [
                'date' => $date,
                'isCurrentMonth' => $date->month === $monthStart->month,
                'events' => $followupsByDate->get($key, collect()),
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        $startHour = $weekEvents->isEmpty()
            ? 8
            : min(8, (int) $weekEvents->min(fn (FollowupRiwayat $followup): int => $followup->tanggal_followup_berikutnya->hour));
        $endHour = $weekEvents->isEmpty()
            ? 20
            : max(20, (int) $weekEvents->max(fn (FollowupRiwayat $followup): int => $followup->tanggal_followup_berikutnya->hour) + 1);
        $startHour = max(0, $startHour);
        $endHour = min(24, $endHour);
        $timeSlots = range($startHour, $endHour - 1);

        return [
            'calendarWeeks' => $weeks,
            'monthLabel' => $monthStart->translatedFormat('F Y'),
            'weekLabel' => $weekStart->translatedFormat('d M').' - '.$weekEnd->translatedFormat('d M Y'),
            'selectedDate' => $selectedDay->toDateString(),
            'selectedDayLabel' => $selectedDay->translatedFormat('l, d F Y'),
            'selectedDayEvents' => $selectedDayEvents,
            'weekDays' => $weekDays,
            'timeSlots' => $timeSlots,
            'scheduledCount' => $weekEvents->count(),
            'overdueCount' => $weekEvents->filter(fn (FollowupRiwayat $followup): bool => $followup->tanggal_followup_berikutnya->isPast())->count(),
        ];
    }

    private function calendarDate(): CarbonImmutable
    {
        return CarbonImmutable::create($this->calendarYear, $this->calendarMonth, 1)->startOfMonth();
    }

    private function setCalendarMonth(CarbonImmutable $date): void
    {
        $this->calendarYear = $date->year;
        $this->calendarMonth = $date->month;
        $selectedDate = $this->parseSelectedDate($this->selectedDate);
        $day = min($selectedDate?->day ?? $date->day, $date->daysInMonth);
        $this->selectedDate = $date->setDay($day)->toDateString();
    }

    private function parseSelectedDate(?string $date): ?CarbonImmutable
    {
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed && $parsed->toDateString() === $date ? $parsed : null;
    }
}
