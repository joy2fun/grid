<?php

namespace App\Providers;

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
        // \Livewire\Livewire::component('app.filament.widgets.stock-price-chart', \App\Filament\Widgets\StockPriceChart::class);
        // \Livewire\Livewire::component('app.filament.resources.grids.widgets.grid-trades-chart', \App\Filament\Resources\Grids\Widgets\GridTradesChart::class);
    }
}
