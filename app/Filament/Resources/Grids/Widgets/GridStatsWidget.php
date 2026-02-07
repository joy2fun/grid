<?php

namespace App\Filament\Resources\Grids\Widgets;

use App\Models\Grid;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class GridStatsWidget extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        if (! $this->record instanceof Grid) {
            return [];
        }

        $metrics = $this->record->getMetrics();

        $xirr = $metrics['xirr'] ?? 0;
        $xirrColor = $xirr >= 0 ? 'success' : 'danger';

        $profit = $metrics['total_profit'] ?? 0;
        $profitColor = $profit >= 0 ? 'success' : 'danger';

        return [
            Stat::make(__('app.grid.annual_return'), $metrics['xirr'] !== null ? number_format($xirr * 100, 3).'%' : 'N/A')
                ->color($xirrColor)
                ->description(__('app.grid.xirr'))
                ->icon($xirr >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'),

            Stat::make(__('app.grid.total_profit'), '¥'.number_format($profit, 0))
                ->color($profitColor)
                ->description(__('app.grid.cash').': ¥'.number_format($metrics['net_cash'], 0).' | '.__('app.grid.holdings').': ¥'.number_format($metrics['holding_value'], 0)),

            Stat::make(__('app.grid.max_cash_required'), '¥'.number_format($metrics['max_cash_occupied'], 0))
                ->color('danger')
                ->description(__('app.grid.peak_capital')),

            Stat::make(__('app.grid.status'), $metrics['trades_count'].' '.__('app.trade.label'))
                ->description(__('app.grid.final_shares').': '.number_format($metrics['final_shares'])),
        ];
    }
}
