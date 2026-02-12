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
            ->query(Stock::inactiveStocks())
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
                    ->sortable(),
                TextColumn::make('daysInactive')
                    ->label(__('app.widgets.inactive_days'))
                    ->getStateUsing(function (Stock $record): int {
                        if (! $record->last_trade_at) {
                            return 0;
                        }

                        return $record->last_trade_at->diffInDays();
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
