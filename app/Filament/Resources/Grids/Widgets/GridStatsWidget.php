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
            Stat::make('XIRR (Annual Return)', $metrics['xirr'] !== null ? number_format($xirr * 100, 3).'%' : 'N/A')
                ->color($xirrColor)
                ->description('Annualized return rate')
                ->icon($xirr >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'),

            Stat::make('Total Profit/Loss', '¥'.number_format($profit, 0))
                ->color($profitColor)
                ->description('Cash: ¥'.number_format($metrics['net_cash'], 0).' | Holdings: ¥'.number_format($metrics['holding_value'], 0)),

            Stat::make('Max Cash Required', '¥'.number_format($metrics['max_cash_occupied'], 0))
                ->color('danger')
                ->description('Peak capital needed'),

            Stat::make('Status', $metrics['trades_count'].' Trades')
                ->description('Final Shares: '.number_format($metrics['final_shares'])),
        ];
    }
}
