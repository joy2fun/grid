<?php

namespace App\Filament\Resources\Stocks\Tables;

use App\Filament\Resources\Stocks\Pages\BacktestStock;
use App\Models\Stock;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('rise_percentage')
                    ->label('Rise %')
                    ->getStateUsing(function (Stock $record) {
                        $prices = $record->dayPrices->sortByDesc('date')->values();

                        if ($prices->count() < 2) {
                            return null;
                        }

                        $latest = $prices[0]->close_price;
                        $previous = $prices[1]->close_price;

                        if ($previous <= 0) {
                            return null;
                        }

                        return (($latest - $previous) / $previous) * 100;
                    })
                    ->badge()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => number_format($state, 2).'%'),

                TextColumn::make('last_trade_at')
                    ->label('Last Trade')
                    ->getStateUsing(fn (Stock $record) => $record->trades->max('executed_at'))
                    ->dateTime()
                    ->formatStateUsing(fn ($state) => $state?->diffForHumans() ?? '-'),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('peak_value')
                    ->label('Peak Value')
                    ->sortable()
                    ->numeric(decimalPlaces: 4)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with([
                'dayPrices' => fn ($q) => $q->latest('date')->limit(2),
                'trades' => fn ($q) => $q->select('id', 'stock_id', 'executed_at'),
            ]))
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                Action::make('backtest')
                    ->label('Backtest')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->url(fn (Stock $record) => BacktestStock::getUrl(['record' => $record])),
                Action::make('chart')
                    ->label('Price Chart')
                    ->icon('heroicon-o-chart-bar')
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalContent(fn (Stock $record) => view('filament.resources.stocks.stock-chart-modal', ['record' => $record])),
                Action::make('sync_price')
                    ->label('Sync Price')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Stock $record, \App\Services\StockService $stockService) {
                        $result = $stockService->syncPriceByStockCode($record->code);

                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->title('Price Synced')
                                ->body("Successfully synced {$result['processed_count']} records.")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Sync Failed')
                                ->body('Failed to sync stock prices.')
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
