<?php

namespace App\Filament\Resources\Stocks\Tables;

use App\Models\Stock;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
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
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
