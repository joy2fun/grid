<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Stocks\StockResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

class ViewIndexChart extends Page
{
    protected static string $resource = StockResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.pages.view-index-chart';

    public static function getNavigationLabel(): string
    {
        return __('app.nav.index_chart');
    }

    public function getTitle(): string
    {
        return __('app.index_chart.title');
    }

    protected static ?int $navigationSort = 2;

    #[Url]
    public string $timeRange = '3y';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('3m')
                ->label(__('app.index_chart.time_range.3m'))
                ->color(fn () => $this->timeRange === '3m' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '3m';
                    $this->dispatch('updateChart', timeRange: '3m')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('6m')
                ->label(__('app.index_chart.time_range.6m'))
                ->color(fn () => $this->timeRange === '6m' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '6m';
                    $this->dispatch('updateChart', timeRange: '6m')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('12m')
                ->label(__('app.index_chart.time_range.12m'))
                ->color(fn () => $this->timeRange === '12m' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '12m';
                    $this->dispatch('updateChart', timeRange: '12m')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('18m')
                ->label(__('app.index_chart.time_range.18m'))
                ->color(fn () => $this->timeRange === '18m' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '18m';
                    $this->dispatch('updateChart', timeRange: '18m')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('2y')
                ->label(__('app.index_chart.time_range.2y'))
                ->color(fn () => $this->timeRange === '2y' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '2y';
                    $this->dispatch('updateChart', timeRange: '2y')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('3y')
                ->label(__('app.index_chart.time_range.3y'))
                ->color(fn () => $this->timeRange === '3y' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '3y';
                    $this->dispatch('updateChart', timeRange: '3y')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('4y')
                ->label(__('app.index_chart.time_range.4y'))
                ->color(fn () => $this->timeRange === '4y' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '4y';
                    $this->dispatch('updateChart', timeRange: '4y')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('5y')
                ->label(__('app.index_chart.time_range.5y'))
                ->color(fn () => $this->timeRange === '5y' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '5y';
                    $this->dispatch('updateChart', timeRange: '5y')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('6y')
                ->label(__('app.index_chart.time_range.6y'))
                ->color(fn () => $this->timeRange === '6y' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '6y';
                    $this->dispatch('updateChart', timeRange: '6y')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\IndexStockPricesChart::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'timeRange' => $this->timeRange,
        ];
    }
}
