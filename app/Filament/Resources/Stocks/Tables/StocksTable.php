<?php

namespace App\Filament\Resources\Stocks\Tables;

use App\Filament\Resources\Stocks\Pages\BacktestStock;
use App\Filament\Resources\Trades\TradeResource;
use App\Models\Stock;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with('trades');
            })
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.stock.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('rise_percentage')
                    ->label(__('app.stock.rise_percentage'))
                    ->badge()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => is_numeric($state) ? number_format($state, 2).'%' : '-')
                    ->sortable(),

                TextColumn::make('current_price')
                    ->label(__('app.stock.current_price'))
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->toggleable()
                    ->default('-'),

                TextColumn::make('peak_percentage')
                    ->label(__('app.table.peak_percentage'))
                    ->getStateUsing(function (Stock $record) {
                        if (! $record->current_price || ! $record->peak_value || $record->peak_value <= 0) {
                            return null;
                        }

                        return ($record->current_price / $record->peak_value) * 100;
                    })
                    ->toggleable()
                    ->badge()
                    ->color(fn ($state) => $state >= 80 ? 'success' : ($state >= 50 ? 'warning' : 'danger'))
                    ->formatStateUsing(fn ($state) => is_numeric($state) ? number_format($state, 2).'%' : $state),

                TextColumn::make('xirr')
                    ->label(__('app.stock.xirr'))
                    ->getStateUsing(fn (Stock $record) => ($record->xirr !== null) ? $record->xirr * 100 : null)
                    ->badge()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => is_numeric($state) ? number_format($state, 2).'%' : $state)
                    ->default('-')
                    ->action(
                        Action::make('exportXirrCashFlow')
                            ->label(__('app.actions.export_xirr_cashflow'))
                            ->icon('heroicon-o-arrow-down-tray')
                            ->hidden(fn (Stock $record) => $record->type !== 'etf')
                            ->action(function (Stock $record) {
                                $cashFlows = [];
                                $trades = $record->trades->sortBy('executed_at');

                                foreach ($trades as $trade) {
                                    $amount = (float) $trade->quantity * (float) $trade->price;
                                    $cashFlow = match ($trade->type) {
                                        'buy' => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => -$amount,
                                            'description' => 'Buy '.$trade->quantity.' @ '.$trade->price,
                                        ],
                                        'sell' => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => $amount,
                                            'description' => 'Sell '.$trade->quantity.' @ '.$trade->price,
                                        ],
                                        'dividend' => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => $amount,
                                            'description' => 'Dividend '.$trade->quantity.' shares @ '.$trade->price.'/share',
                                        ],
                                        'stock_dividend' => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => 0,
                                            'description' => 'Stock Dividend base='.$trade->quantity.' ratio='.($trade->split_ratio ?? $trade->price),
                                        ],
                                        'stock_split' => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => 0,
                                            'description' => 'Stock Split ratio='.($trade->split_ratio ?? $trade->price),
                                        ],
                                        default => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => 0,
                                            'description' => $trade->type.' '.$trade->quantity.' @ '.$trade->price,
                                        ],
                                    };
                                    $cashFlows[] = $cashFlow;
                                }

                                $holding = $record->holding;
                                if ($holding && $holding->quantity > 0 && $record->current_price) {
                                    $holdingValue = (float) $holding->quantity * (float) $record->current_price;
                                    $cashFlows[] = [
                                        'date' => now()->toDateString(),
                                        'amount' => $holdingValue,
                                        'description' => 'Current holding value: '.$holding->quantity.' shares @ '.$record->current_price,
                                    ];
                                }

                                $csvContent = "Date,Amount,Description\n";
                                foreach ($cashFlows as $flow) {
                                    $csvContent .= "{$flow['date']},{$flow['amount']},\"{$flow['description']}\"\n";
                                }

                                $filename = 'xirr_cashflow_'.$record->code.'_'.now()->format('Ymd_His').'.csv';

                                return response()->streamDownload(function () use ($csvContent) {
                                    echo $csvContent;
                                }, $filename, [
                                    'Content-Type' => 'text/csv',
                                ]);
                            })
                    ),

                TextColumn::make('last_trade_at')
                    ->label(__('app.stock.last_trade'))
                    ->url(
                        fn (Stock $record): ?string => $record->type === 'etf'
                            ? TradeResource::getUrl('index', [
                                'filters' => [
                                    'stock_id' => [
                                        'value' => $record->id,
                                    ],
                                ],
                            ])
                            : null
                    ),

                TextColumn::make('code')
                    ->label(__('app.stock.code'))
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->copyable(),

                TextColumn::make('peak_value')
                    ->label(__('app.stock.peak_value'))
                    ->sortable()
                    ->numeric(decimalPlaces: 4)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with([
                'dayPrices' => fn ($q) => $q->latest('date')->limit(2),
                'trades' => fn ($q) => $q->select('id', 'stock_id', 'type', 'price', 'quantity', 'split_ratio', 'executed_at'),
                'holding',
            ]))
            ->filters([
                SelectFilter::make('type')
                    ->label(__('app.stock.type'))
                    ->options([
                        'etf' => __('app.stock.type_etf'),
                        'index' => __('app.stock.type_index'),
                    ]),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->iconSize('sm'),
                Action::make('backtest')
                    ->label(__('app.actions.backtest'))
                    ->icon('heroicon-o-presentation-chart-line')
                    ->url(fn (Stock $record) => BacktestStock::getUrl(['record' => $record])),
                Action::make('chart')
                    ->label(__('app.actions.price_chart'))
                    ->icon('heroicon-o-chart-bar')
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalHeading(fn (Stock $record) => $record->name)
                    ->modalContent(fn (Stock $record) => view('filament.resources.stocks.stock-chart-modal', ['record' => $record])),
                Action::make('sync_price')
                    ->label(__('app.actions.sync_price'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Stock $record, \App\Services\StockService $stockService) {
                        $result = $stockService->syncPriceByStockCode($record->code);

                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('app.notifications.price_synced'))
                                ->body("Successfully synced {$result['processed_count']} records.")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title(__('app.notifications.import_failed'))
                                ->body('Failed to sync stock prices.')
                                ->danger()
                                ->send();
                        }
                    }),
                DeleteAction::make()->iconButton()->iconSize('sm'),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
