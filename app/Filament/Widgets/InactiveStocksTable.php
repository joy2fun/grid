<?php

namespace App\Filament\Widgets;

use App\Models\AppSetting;
use App\Models\Stock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class InactiveStocksTable extends TableWidget
{
    protected static ?string $heading = 'Inactive Stocks';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $threshold = AppSetting::get('inactive_stocks_threshold', 30);

        return $table
            ->query(
                Stock::inactiveStocks()
                    ->with(['trades' => function ($query) {
                        $query->latest('executed_at');
                    }])
            )
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Stock Name')
                    ->sortable(),
                TextColumn::make('current_price')
                    ->label('Current Price')
                    ->money('CNY')
                    ->sortable(),
                TextColumn::make('lastTradePrice')
                    ->label('Last Trade Price')
                    ->money('CNY')
                    ->getStateUsing(function (Stock $record): ?float {
                        return $record->trades->first()?->price;
                    }),
                TextColumn::make('priceChange')
                    ->label('Change %')
                    ->getStateUsing(function (Stock $record): ?float {
                        $lastTrade = $record->trades->first();
                        if (! $lastTrade || ! $record->current_price || $lastTrade->price === 0) {
                            return null;
                        }

                        return (($record->current_price - $lastTrade->price) / $lastTrade->price) * 100;
                    })
                    ->formatStateUsing(fn (?float $state): string => $state !== null ? number_format($state, 2).'%' : 'N/A')
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state > 0 => 'success',
                        $state < 0 => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('daysInactive')
                    ->label('Days Inactive')
                    ->getStateUsing(function (Stock $record): int {
                        $lastTrade = $record->trades->first();
                        if (! $lastTrade) {
                            return 999; // No trades ever
                        }

                        return $lastTrade->executed_at->diffInDays();
                    })
                    ->suffix(' days')
                    ->sortable(),
            ])
            ->defaultSort('daysInactive', 'desc')
            ->paginated(false)
            ->emptyStateHeading('No Inactive Stocks')
            ->emptyStateDescription("All stocks have been traded within the last {$threshold} days.");
    }
}
