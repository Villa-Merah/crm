<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FollowupRiwayats\FollowupRiwayatResource;
use App\Filament\Resources\FollowupRiwayats\Pages\ListFollowupRiwayats;
use App\Models\Cabang;
use App\Models\FollowupRiwayat;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FollowupCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_followup_index_shows_scheduled_followups_in_month_calendar(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 17)->startOfDay());
        $branch = $this->branch('BDG', 'Bandung');
        $staff = $this->staff($branch, 'Staff Bandung');
        $lead = $this->lead($branch, $staff, 'Lead Kalender');
        $this->followup($lead, $staff, '2026-10-05 12:35:00', '2026-10-01 12:35:00');
        $this->followup($lead, $staff, '2026-10-26 12:35:00');

        $this->actingAs($staff)
            ->get('/admin/follow-up?tanggal=2026-10-26')
            ->assertOk()
            ->assertSee('Oktober 2026')
            ->assertSee('Jadwal tindak lanjut')
            ->assertSee('Lead Kalender')
            ->assertSee('12:35')
            ->assertSee('1 jadwal')
            ->assertSee('Agenda tanggal terpilih')
            ->assertDontSee('1 terlambat')
            ->assertSee('Metode follow-up');
    }

    public function test_calendar_month_navigation_updates_scheduled_events_and_respects_branch_scope(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 17)->startOfDay());
        $bandung = $this->branch('BDG', 'Bandung');
        $jakarta = $this->branch('JKS', 'Jakarta');
        $staff = $this->staff($bandung, 'Staff Bandung');
        $otherStaff = $this->staff($jakarta, 'Staff Jakarta');
        $ownOctoberLead = $this->lead($bandung, $staff, 'Lead Bandung Oktober');
        $ownNovemberLead = $this->lead($bandung, $staff, 'Lead Bandung November');
        $otherLead = $this->lead($jakarta, $otherStaff, 'Lead Jakarta Oktober');
        $this->followup($ownOctoberLead, $staff, '2026-10-26 12:35:00');
        $this->followup($ownNovemberLead, $staff, '2026-11-03 09:00:00');
        $this->followup($otherLead, $otherStaff, '2026-10-27 10:00:00');
        $this->actingAs($staff);
        $this->assertSame(2, FollowupRiwayatResource::getEloquentQuery()->count());

        Livewire::actingAs($staff)->test(ListFollowupRiwayats::class)
            ->call('selectDate', '2026-10-26')
            ->assertSee('Lead Bandung Oktober')
            ->assertDontSee('Lead Jakarta Oktober')
            ->assertDontSee('Lead Bandung November')
            ->call('nextWeek')
            ->assertSee('Lead Bandung November')
            ->call('previousWeek')
            ->assertSee('Lead Bandung Oktober')
            ->call('nextMonth')
            ->call('selectDate', '2026-11-03')
            ->assertSee('November 2026')
            ->assertSee('Lead Bandung November')
            ->assertDontSee('Lead Bandung Oktober');
    }

    private function branch(string $code, string $name): Cabang
    {
        return Cabang::create(['kode_cabang' => $code, 'nama' => $name, 'aktif' => true]);
    }

    private function staff(Cabang $branch, string $name): User
    {
        static $sequence = 0;
        $sequence++;

        return User::factory()->create([
            'name' => $name,
            'email' => 'calendar-staff-'.$sequence.'@sivmi.test',
            'cabang_id' => $branch->getKey(),
            'role' => UserRole::Staff,
            'aktif' => true,
        ]);
    }

    private function lead(Cabang $branch, User $staff, string $name): Lead
    {
        static $sequence = 0;
        $sequence++;

        return Lead::create([
            'nama' => $name,
            'no_wa' => '08'.str_pad((string) $sequence, 10, '0', STR_PAD_LEFT),
            'cabang_id' => $branch->getKey(),
            'status_lead' => 'Warm',
            'diinput_oleh_user_id' => $staff->getKey(),
            'diserahkan_ke_user_id' => $staff->getKey(),
            'tanggal_masuk' => today(),
        ]);
    }

    private function followup(Lead $lead, User $staff, string $scheduledAt, ?string $completedAt = null): FollowupRiwayat
    {
        return FollowupRiwayat::create([
            'lead_id' => $lead->getKey(),
            'user_id' => $staff->getKey(),
            'tanggal_followup' => $completedAt ?? now(),
            'metode_followup' => 'WhatsApp',
            'status_kontak' => 'Terhubung',
            'tanggal_followup_berikutnya' => $scheduledAt,
        ]);
    }
}
