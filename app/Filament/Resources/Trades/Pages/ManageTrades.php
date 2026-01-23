<?php

namespace App\Filament\Resources\Trades\Pages;

use App\Filament\Resources\Trades\TradeResource;
use App\Models\Stock;
use App\Models\Trade;
use App\Services\StockService;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;

class ManageTrades extends ManageRecords
{
    protected static string $resource = TradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            \Filament\Actions\Action::make('bulkImport')
                ->label('Bulk Import')
                ->modalHeading('Bulk Import Trades')
                ->modalDescription('Paste JSON data to import trades in bulk')
                ->schema([
                    Textarea::make('json_data')
                        ->label('Trade JSON')
                        ->rows(12)
                        ->required()
                        ->helperText('Example JSON format:
{
  "trades": [
    {
      "code": "601166",
      "quantity": 100,
      "price": 10.2,
      "time": "2026-01-01 09:30:55",
      "side": "buy/sell"
    }
  ]
}'),
                ])
                ->action(function (array $data, StockService $stockService) {
                    $jsonData = json_decode($data['json_data'], true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Notification::make()
                            ->title('Invalid JSON')
                            ->body('Please ensure your JSON is properly formatted.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if (! isset($jsonData['trades']) || ! is_array($jsonData['trades'])) {
                        Notification::make()
                            ->title('Invalid Format')
                            ->body('JSON must contain a "trades" array.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $importedCount = 0;
                    $skippedCount = 0;
                    $errors = [];

                    DB::beginTransaction();

                    try {
                        foreach ($jsonData['trades'] as $index => $tradeData) {
                            // Validate required fields
                            if (! isset($tradeData['code']) || ! isset($tradeData['quantity']) ||
                                ! isset($tradeData['price']) || ! isset($tradeData['time']) || ! isset($tradeData['side'])) {
                                $errors[] = 'Trade #'.($index + 1).': Missing required fields (code, quantity, price, time, side)';

                                continue;
                            }

                            // Auto prefix stock code
                            $prefixedCode = $stockService->autoPrefixCode($tradeData['code']);

                            // Find or create stock by prefixed code
                            $stock = Stock::firstOrCreate(
                                ['code' => $prefixedCode],
                                ['name' => $prefixedCode]
                            );

                            // Check for duplicates (same time and stock_id)
                            $exists = Trade::where('stock_id', $stock->id)
                                ->where('executed_at', $tradeData['time'])
                                ->exists();

                            if ($exists) {
                                $skippedCount++;

                                continue;
                            }

                            // Create trade with grid_id as null
                            Trade::create([
                                'grid_id' => null,
                                'stock_id' => $stock->id,
                                'side' => $tradeData['side'],
                                'quantity' => (int) $tradeData['quantity'],
                                'price' => (float) $tradeData['price'],
                                'executed_at' => $tradeData['time'],
                            ]);

                            $importedCount++;
                        }

                        DB::commit();

                        $message = "Imported {$importedCount} trades successfully.";
                        if ($skippedCount > 0) {
                            $message .= " Skipped {$skippedCount} duplicates.";
                        }

                        if (! empty($errors)) {
                            $message .= ' '.count($errors).' errors occurred.';
                        }

                        Notification::make()
                            ->title($importedCount > 0 ? 'Import Completed' : 'Import Failed')
                            ->body($message)
                            ->when(! empty($errors), fn ($notification) => $notification->danger())
                            ->when(empty($errors) && $importedCount > 0, fn ($notification) => $notification->success())
                            ->send();

                    } catch (\Exception $e) {
                        DB::rollBack();

                        Notification::make()
                            ->title('Import Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
