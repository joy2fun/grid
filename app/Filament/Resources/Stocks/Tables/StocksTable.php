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

class StocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
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

                TextColumn::make('last_trade_at_formatted')
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
                Action::make('full_sync_price')
                    ->label(__('app.actions.full_sync_price'))
                    ->icon('heroicon-o-arrow-down-on-square-stack')
                    ->requiresConfirmation()
                    ->modalHeading(__('app.actions.full_sync_price'))
                    ->modalDescription(__('app.messages.full_sync_confirmation'))
                    ->action(function (Stock $record, \App\Services\StockService $stockService) {
                        $result = $stockService->fullSyncStockPrices($record->code);

                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('app.notifications.full_sync_completed'))
                                ->body("Synced {$result['total_processed']} records in {$result['api_calls']} API calls.")
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
