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
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('rise_percentage')
                    ->label('Rise %')
                    ->badge()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => is_numeric($state) ? number_format($state, 2).'%' : '-')
                    ->sortable(),

                TextColumn::make('current_price')
                    ->label('Current Price')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->toggleable()
                    ->default('-'),

                TextColumn::make('peak_percentage')
                    ->label('Peak %')
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
                    ->label('XIRR')
                    ->getStateUsing(fn (Stock $record) => ($record->xirr !== null) ? $record->xirr * 100 : null)
                    ->badge()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => is_numeric($state) ? number_format($state, 2).'%' : $state)
                    ->default('-'),

                TextColumn::make('last_trade_at')
                    ->label('Last Trade')
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
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->copyable(),

                TextColumn::make('peak_value')
                    ->label('Peak Value')
                    ->sortable()
                    ->numeric(decimalPlaces: 4)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with([
                'dayPrices' => fn ($q) => $q->latest('date')->limit(2),
                'trades' => fn ($q) => $q->select('id', 'stock_id', 'side', 'price', 'quantity', 'executed_at'),
                'holding',
            ]))
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'etf' => 'ETF',
                        'index' => 'Index',
                    ]),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->iconSize('sm'),
                Action::make('backtest')
                    ->label('Backtest')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->url(fn (Stock $record) => BacktestStock::getUrl(['record' => $record])),
                Action::make('chart')
                    ->label('Price Chart')
                    ->icon('heroicon-o-chart-bar')
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalHeading(fn (Stock $record) => $record->name)
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
                DeleteAction::make()->iconButton()->iconSize('sm'),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
