<?php

namespace App\Providers\Filament;

use App\Enums\ThemePreset;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\TwoFactorChallenge;
use App\Http\Middleware\EnforceRoleTwoFactorSetup;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\EnsureTwoFactorChallengePassed;
use App\Http\Middleware\TrackUserSession;
use App\Models\InstitutionSetting;
use App\Models\User;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ErpPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('routine-grid', resource_path('css/filament/routine-grid.css')),
            Css::make('report-table', resource_path('css/filament/report-table.css')),
        ]);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('erp')
            ->path('/')
            ->viteTheme('resources/css/filament/erp/theme.css')
            ->login()
            ->profile(EditProfile::class, isSimple: false)
            ->colors([
                'primary' => Color::hex(ThemePreset::ForestOchre->primaryColor()),
                'gray' => ThemePreset::ForestOchre->grayColor(),
            ])
            ->font('Inter')
            ->serifFont('Newsreader')
            ->defaultThemeMode(ThemeMode::Light)
            ->navigationGroups([
                'People',
                'Routine',
                'Manage Exams',
                'Academic',
                'Clearance',
                'Campus',
                'Settings',
                'My Account',
                'Security & Access',
            ])
            ->sidebarCollapsibleOnDesktop(true)
            ->brandName(fn (): string => InstitutionSetting::current()->institution_name ?? 'ERP')
            ->brandLogo(function (): ?string {
                $setting = InstitutionSetting::current();

                return $setting->logo_path ? Storage::disk('public')->url($setting->logo_path) : null;
            })
            ->brandLogoHeight('2rem')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('/two-factor-challenge', TwoFactorChallenge::class)->name('auth.two-factor-challenge');
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
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
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->databaseNotifications()
            ->authMiddleware([
                TrackUserSession::class,
                Authenticate::class,
                EnsureTwoFactorChallengePassed::class,
                EnforceRoleTwoFactorSetup::class,
                EnsureProfileComplete::class,
            ], isPersistent: true)
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Auth::check() ? view('assistant.widget')->render() : '',
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => $this->themeStyleTag(),
            );
    }

    protected function currentThemePreset(): ThemePreset
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        return $user?->theme ?? ThemePreset::ForestOchre;
    }

    protected function currentPrimaryColor(ThemePreset $preset): string
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();
        $custom = $user?->theme_primary_color;

        return ($custom && preg_match('/^#[0-9a-f]{6}$/i', $custom))
            ? $custom
            : $preset->primaryColor();
    }

    /**
     * Overrides Filament's panel-wide default colors (registered once at
     * boot, before auth is resolved — see Panel::boot()) with the current
     * user's chosen preset and accent color. Rendered late in <head> so it
     * wins the CSS cascade over the boot-time :root values.
     */
    protected function themeStyleTag(): string
    {
        $preset = $this->currentThemePreset();

        $variables = [
            '--erp-nav' => $preset->navColor(),
            '--erp-nav-deep' => $preset->navColorDeep(),
            '--erp-content' => $preset->contentColor(),
            '--erp-content-dark' => $preset->contentColorDark(),
        ];

        foreach (Color::hex($this->currentPrimaryColor($preset)) as $shade => $value) {
            $variables["--primary-{$shade}"] = $value;
        }

        foreach ($preset->grayColor() as $shade => $value) {
            $variables["--gray-{$shade}"] = $value;
        }

        $declarations = collect($variables)
            ->map(fn (string $value, string $name): string => "{$name}:{$value}")
            ->implode(';');

        return "<style>:root{{$declarations}}</style>";
    }
}
