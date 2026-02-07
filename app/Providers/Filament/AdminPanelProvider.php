<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AppSettings;
use App\Filament\Pages\McpSettings;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->userMenuItems([
                'settings' => \Filament\Navigation\MenuItem::make()
                    ->label(fn () => __('app.common.settings'))
                    ->url(fn (): string => AppSettings::getUrl())
                    ->icon('heroicon-o-cog-6-tooth'),
                'mcp' => \Filament\Navigation\MenuItem::make()
                    ->label(fn () => __('app.mcp_settings.title'))
                    ->url(fn (): string => McpSettings::getUrl())
                    ->icon('heroicon-o-server'),
            ])
            // ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\InactiveStocksTable::class,
                \App\Filament\Widgets\PriceChangeStocksTable::class,
                \App\Filament\Widgets\MonthlyCashFlowChart::class,
            ])
            ->plugins([
                FilamentApexChartsPlugin::make(),
            ])
            ->topNavigation()
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => view('filament.components.mobile-quick-links')->render(),
            )

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
