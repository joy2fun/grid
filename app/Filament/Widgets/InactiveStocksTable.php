<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Trades\TradeResource;
use App\Models\AppSetting;
use App\Models\Stock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class InactiveStocksTable extends TableWidget
{
    public function getHeading(): string
    {
        return __('app.widgets.inactive_stocks');
    }

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Stock::inactiveStocks()->exists();
    }

    public function table(Table $table): Table
    {
        $threshold = AppSetting::get('inactive_stocks_threshold', 30);

        return $table
            ->query(
                Stock::inactiveStocks()
                    ->with(['trades' => function ($query) {
                        $query->whereIn('type', ['buy', 'sell'])->latest('executed_at');
                    }])
            )
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
                TextColumn::make('lastTradePrice')
                    ->label(__('app.stock.last_trade'))
                    ->getStateUsing(function (Stock $record): ?float {
                        return $record->trades->first()?->price;
                    }),
                TextColumn::make('priceChange')
                    ->label(__('app.widgets.change_percentage'))
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
                    ->label(__('app.widgets.inactive_days'))
                    ->getStateUsing(function (Stock $record): int {
                        $lastTrade = $record->trades->first();
                        if (! $lastTrade) {
                            return 0;
                        }

                        return $lastTrade->executed_at->diffInDays();
                    })
                    ->suffix(' '.__('app.widgets.days'))
                    ->sortable(),
            ])
            ->defaultSort('daysInactive', 'desc')
            ->paginated(false)
            ->emptyStateHeading(__('app.widgets.no_inactive_stocks'))
            ->emptyStateDescription(__('app.widgets.all_traded_recently', ['days' => $threshold]));
    }
}
