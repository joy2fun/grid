<?php

namespace App\Filament\Resources\Stocks\Pages;

use App\Filament\Resources\Stocks\StockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStocks extends ManageRecords
{
    protected static string $resource = StockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('sync_realtime')
                ->label('Sync Realtime')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    $stocks = \App\Models\Stock::all();
                    $codes = $stocks->pluck('code')->toArray();
                    $chunks = array_chunk($codes, 50);

                    $stockService = app(\App\Services\StockService::class);
                    $processedCount = 0;

                    foreach ($chunks as $chunk) {
                        try {
                            $realtimeData = \App\Utilities\StockPriceService::getRealtimePrices(...$chunk);
                            $stockService->updateRealtimePrices($realtimeData);
                            $processedCount += count($chunk);
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Error syncing realtime prices: '.$e->getMessage());
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Realtime Prices Synced')
                        ->body("Successfully triggered sync for {$processedCount} stocks.")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
