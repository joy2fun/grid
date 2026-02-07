<?php

namespace App\Filament\Resources\Stocks\Pages;

use App\Filament\Resources\Stocks\StockResource;
use App\Models\Stock;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageStocks extends ManageRecords
{
    protected static string $resource = StockResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('app.common.all')),
            'etf' => Tab::make(__('app.stock.type_etf'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'etf')),
            'index' => Tab::make(__('app.stock.type_index'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'index')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('index_chart')
                ->label(__('app.actions.index_chart'))
                ->icon('heroicon-o-chart-bar')
                ->url(StockResource::getUrl('chart')),
            ActionGroup::make([
                Action::make('sync_realtime')
                    ->label(__('app.actions.sync_prices'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function () {
                        $stocks = Stock::all();
                        $codes = $stocks->pluck('code')->toArray();
                        $chunks = array_chunk($codes, 50);

                        $stockService = app(StockService::class);
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

                        Notification::make()
                            ->title(__('app.notifications.price_synced'))
                            ->body("Successfully triggered sync for {$processedCount} stocks.")
                            ->success()
                            ->send();
                    }),
                Action::make('bulkImport')
                    ->label(__('app.common.import'))
                    ->icon('heroicon-o-document-arrow-up')
                    ->modalHeading(__('app.import_export.label'))
                    ->modalDescription('Paste JSON data to import stocks in bulk')
                    ->form([
                        Textarea::make('json_data')
                            ->label(__('app.import_export.stock_json'))
                            ->rows(12)
                            ->required()
                            ->hint('Example JSON format:')
                            ->hintIcon('heroicon-o-information-circle')
                            ->helperText('Paste JSON in the following format:
{
  "stocks": [
    {
      "code": "000001",
      "name": "平安银行",
      "type": "index",
      "peak_value": 123.45
    }
  ]
}'),
                    ])
                    ->action(function (array $data, StockService $stockService) {
                        $jsonData = json_decode($data['json_data'], true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            Notification::make()
                                ->title(__('app.notifications.invalid_json'))
                                ->body('Please ensure your JSON is properly formatted.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! isset($jsonData['stocks']) || ! is_array($jsonData['stocks'])) {
                            Notification::make()
                                ->title(__('app.notifications.invalid_format'))
                                ->body('JSON must contain a "stocks" array.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $importedCount = 0;
                        $updatedCount = 0;
                        $skippedCount = 0;
                        $errors = [];

                        \Illuminate\Support\Facades\DB::beginTransaction();

                        try {
                            foreach ($jsonData['stocks'] as $index => $stockData) {
                                // Validate required fields
                                if (! isset($stockData['code'])) {
                                    $errors[] = 'Stock #'.($index + 1).': Missing required field (code)';

                                    continue;
                                }

                                // Auto prefix stock code
                                $prefixedCode = $stockService->autoPrefixCode($stockData['code']);
                                $stockType = $stockData['type'] ?? 'etf';

                                // Check if stock already exists (by code and type)
                                $existingStock = Stock::where('code', $prefixedCode)
                                    ->where('type', $stockType)
                                    ->first();

                                if ($existingStock) {
                                    // Update existing stock with provided fields
                                    $updateData = [];
                                    if (isset($stockData['name'])) {
                                        $updateData['name'] = $stockData['name'];
                                    }
                                    if (isset($stockData['type'])) {
                                        $updateData['type'] = $stockData['type'];
                                    }
                                    if (isset($stockData['peak_value'])) {
                                        $updateData['peak_value'] = (float) $stockData['peak_value'];
                                    }

                                    if (! empty($updateData)) {
                                        $existingStock->update($updateData);
                                        $updatedCount++;
                                    } else {
                                        $skippedCount++;
                                    }
                                } else {
                                    // Create new stock
                                    Stock::create([
                                        'code' => $prefixedCode,
                                        'name' => $stockData['name'] ?? 'Unknown',
                                        'type' => $stockData['type'] ?? null,
                                        'peak_value' => isset($stockData['peak_value']) ? (float) $stockData['peak_value'] : null,
                                    ]);
                                    $importedCount++;
                                }
                            }

                            \Illuminate\Support\Facades\DB::commit();

                            $message = "Imported {$importedCount} stocks successfully.";
                            if ($updatedCount > 0) {
                                $message .= " Updated {$updatedCount} existing stocks.";
                            }
                            if ($skippedCount > 0) {
                                $message .= " Skipped {$skippedCount} duplicates.";
                            }

                            if (! empty($errors)) {
                                $message .= ' '.count($errors).' errors occurred.';
                            }

                            Notification::make()
                                ->title($importedCount > 0 || $updatedCount > 0 ? __('app.notifications.import_completed') : __('app.notifications.import_failed'))
                                ->body($message)
                                ->when(! empty($errors), fn ($notification) => $notification->danger())
                                ->when(empty($errors) && ($importedCount > 0 || $updatedCount > 0), fn ($notification) => $notification->success())
                                ->send();
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\DB::rollBack();

                            Notification::make()
                                ->title(__('app.notifications.import_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
                ->label(__('app.common.tools'))
                ->icon('heroicon-o-wrench')
                ->color('gray')
                ->button(),
            CreateAction::make()
                ->label(__('app.common.new'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
