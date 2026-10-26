<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Models\CrmSetting;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->homeUrl(fn (): string => Dashboard::getUrl())
            ->login()
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.auth.sso-login'))
            ->profile(EditProfile::class, isSimple: false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::hex('#1476B8'),
                'success' => Color::hex('#10B981'),
                'warning' => Color::hex('#F59E0B'),
                'danger' => Color::hex('#F43F5E'),
                'purple' => Color::hex('#A855F7'),
            ])
            ->brandName(fn () => (string) CrmSetting::getValue('app_name', 'SIVMI CRM'))
            ->brandLogo(fn () => view('filament.components.brand-logo'))
            ->brandLogoHeight('1.75rem')
            ->defaultThemeMode(ThemeMode::Light)
            ->darkMode(false)
            ->themeSwitcher(false)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarFullyCollapsibleOnDesktop()
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.partials.sidebar-state'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.partials.login-background'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('SIVMI CRM')->collapsible(false),
                NavigationGroup::make('MASTER DATA')->collapsible(false),
                NavigationGroup::make('BANTUAN & DUKUNGAN')->collapsible(false),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
