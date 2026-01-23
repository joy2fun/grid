<?php

namespace App\Filament\Widgets;

use App\Models\AppSetting;
use App\Models\Stock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PriceChangeStocksTable extends TableWidget
{
    protected static ?string $heading = 'Significant Price Changes';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::getPriceChangeStocks()->isNotEmpty();
    }

    protected static function getPriceChangeStocks(): \Illuminate\Support\Collection
    {
        $threshold = (float) AppSetting::get('price_change_threshold', 5);

        return Stock::query()
            ->where('type', '!=', 'index')
            ->whereHas('trades')
            ->with(['trades' => function ($query) {
                $query->latest('executed_at');
            }])
            ->whereNotNull('current_price')
            ->get()
            ->filter(function (Stock $stock) use ($threshold) {
                $lastTrade = $stock->trades->first();
                if (! $lastTrade || $lastTrade->price === 0) {
                    return false;
                }

                $priceChangePercentage = (($stock->current_price - $lastTrade->price) / $lastTrade->price) * 100;

                return abs($priceChangePercentage) >= $threshold;
            });
    }

    public function table(Table $table): Table
    {
        $threshold = (float) AppSetting::get('price_change_threshold', 5);

        $stocks = static::getPriceChangeStocks()
            ->sortByDesc(function (Stock $stock) {
                $lastTrade = $stock->trades->first();
                if (! $lastTrade || $lastTrade->price === 0) {
                    return 0;
                }

                return abs((($stock->current_price - $lastTrade->price) / $lastTrade->price) * 100);
            })
            ->values();

        $query = $stocks->isNotEmpty() ? $stocks->toQuery() : Stock::query()->whereRaw('1 = 0');

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('name')
                    ->label('Name'),
                TextColumn::make('current_price')
                    ->label('Current'),
                TextColumn::make('lastTradePrice')
                    ->label('Last Trade')
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
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('daysSinceTrade')
                    ->label('Days Since')
                    ->getStateUsing(function (Stock $record): int {
                        $lastTrade = $record->trades->first();
                        if (! $lastTrade) {
                            return 0;
                        }

                        return $lastTrade->executed_at->diffInDays();
                    })
                    ->suffix(' days')
                    ->sortable(),
            ])
            ->defaultSort('priceChange', 'desc')
            ->paginated(false)
            ->emptyStateHeading('No Significant Price Changes')
            ->emptyStateDescription("No stocks have price changes exceeding ±{$threshold}% from their last traded price.");
    }
}
