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

    protected static ?string $navigationLabel = 'Index Chart';

    protected static ?string $title = 'Index Stock Price Chart';

    protected static ?int $navigationSort = 2;

    #[Url]
    public string $timeRange = '3y';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('3m')
                ->label('3M')
                ->color(fn () => $this->timeRange === '3m' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '3m';
                    $this->dispatch('updateChart', timeRange: '3m')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('6m')
                ->label('6M')
                ->color(fn () => $this->timeRange === '6m' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '6m';
                    $this->dispatch('updateChart', timeRange: '6m')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('12m')
                ->label('12M')
                ->color(fn () => $this->timeRange === '12m' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '12m';
                    $this->dispatch('updateChart', timeRange: '12m')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('18m')
                ->label('18M')
                ->color(fn () => $this->timeRange === '18m' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '18m';
                    $this->dispatch('updateChart', timeRange: '18m')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('2y')
                ->label('2Y')
                ->color(fn () => $this->timeRange === '2y' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '2y';
                    $this->dispatch('updateChart', timeRange: '2y')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('3y')
                ->label('3Y')
                ->color(fn () => $this->timeRange === '3y' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '3y';
                    $this->dispatch('updateChart', timeRange: '3y')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('4y')
                ->label('4Y')
                ->color(fn () => $this->timeRange === '4y' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '4y';
                    $this->dispatch('updateChart', timeRange: '4y')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('5y')
                ->label('5Y')
                ->color(fn () => $this->timeRange === '5y' ? 'primary' : 'gray')
                ->action(function () {
                    $this->timeRange = '5y';
                    $this->dispatch('updateChart', timeRange: '5y')->to(\App\Filament\Widgets\IndexStockPricesChart::class);
                }),

            Action::make('6y')
                ->label('6Y')
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
