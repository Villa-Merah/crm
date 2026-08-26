<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Cabang;
use App\Models\FollowupRiwayat;
use App\Models\Lead;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\ProgramBimbel;
use App\Models\Sekolah;
use App\Models\TargetKinerjaCabang;
use App\Models\TargetKinerjaUser;
use App\Models\User;
use App\Models\UserWhatsAppAccount;
use App\Observers\CrmActivityObserver;
use App\Observers\CrmNotificationObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Cabang::class, Sekolah::class, User::class, ProgramBimbel::class, Lead::class, FollowupRiwayat::class, Pendaftaran::class, Pembayaran::class, TargetKinerjaCabang::class, TargetKinerjaUser::class, UserWhatsAppAccount::class] as $model) {
            $model::observe(CrmActivityObserver::class);
        }
        foreach ([Lead::class, Pembayaran::class, TargetKinerjaUser::class] as $model) {
            $model::observe(CrmNotificationObserver::class);
        }
        Event::listen(Login::class, fn (Login $event) => ActivityLog::create(['user_id' => $event->user->id, 'cabang_id' => $event->user->cabang_id, 'event' => 'login', 'description' => 'Pengguna masuk ke CRM', 'ip_address' => request()->ip(), 'user_agent' => str(request()->userAgent())->limit(500)->toString(), 'created_at' => now()]));
        Event::listen(Logout::class, fn (Logout $event) => $event->user && ActivityLog::create(['user_id' => $event->user->id, 'cabang_id' => $event->user->cabang_id, 'event' => 'logout', 'description' => 'Pengguna keluar dari CRM', 'ip_address' => request()->ip(), 'user_agent' => str(request()->userAgent())->limit(500)->toString(), 'created_at' => now()]));
    }
}
