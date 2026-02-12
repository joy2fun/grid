<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Trades\TradeResource;
use App\Models\AppSetting;
use App\Models\Stock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PriceChangeStocksTable extends TableWidget
{
    public function getHeading(): string
    {
        return __('app.widgets.significant_price_changes');
    }

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
            ->whereNotNull('last_trade_price')
            ->whereNotNull('current_price')
            ->get()
            ->filter(function (Stock $stock) use ($threshold) {
                if ($stock->last_trade_price == 0) {
                    return false;
                }

                $priceChangePercentage = (($stock->current_price - $stock->last_trade_price) / $stock->last_trade_price) * 100;

                return abs($priceChangePercentage) >= $threshold;
            });
    }

    public function table(Table $table): Table
    {
        $stocks = static::getPriceChangeStocks()
            ->sortByDesc(function (Stock $stock) {
                if ($stock->last_trade_price == 0) {
                    return 0;
                }

                return abs((($stock->current_price - $stock->last_trade_price) / $stock->last_trade_price) * 100);
            })
            ->values();

        if ($stocks->isEmpty()) {
            $query = Stock::query()->whereRaw('1 = 0');
        } else {
            $ids = $stocks->pluck('id');
            $idsString = $ids->implode(',');

            // Build CASE statement for ordering
            $sql = 'CASE id ';
            foreach ($ids as $index => $id) {
                $sql .= "WHEN {$id} THEN {$index} ";
            }
            $sql .= 'END';

            $query = Stock::query()
                ->whereIntegerInRaw('id', $ids)
                ->orderByRaw($sql);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.stock.name'))
                    ->url(fn ($record) => TradeResource::getUrl('index', [
                        'filters' => [
                            'stock_id' => [
                                'value' => $record->id,
                            ],
                        ],
                    ])),
                TextColumn::make('current_price')
                    ->label(__('app.widgets.current')),
                TextColumn::make('last_trade_price')
                    ->label(__('app.stock.last_trade'))
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('priceChange')
                    ->label(__('app.widgets.change_percentage'))
                    ->getStateUsing(function (Stock $record): ?float {
                        if (! $record->last_trade_price || ! $record->current_price || $record->last_trade_price == 0) {
                            return null;
                        }

                        return (($record->current_price - $record->last_trade_price) / $record->last_trade_price) * 100;
                    })
                    ->formatStateUsing(fn (?float $state): string => $state !== null ? number_format($state, 2).'%' : 'N/A')
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state > 0 => 'success',
                        $state < 0 => 'danger',
                        default => 'gray',
                    })
                    ->weight('bold'),
                TextColumn::make('daysSinceTrade')
                    ->label(__('app.widgets.days_since'))
                    ->getStateUsing(function (Stock $record): int {
                        if (! $record->last_trade_at) {
                            return 0;
                        }

                        return $record->last_trade_at->diffInDays();
                    })
                    ->suffix(' '.__('app.widgets.days'))
                    ->sortable(),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('app.widgets.no_significant_changes'));
        // Removed defaultSort as query is pre-sorted
    }
}
