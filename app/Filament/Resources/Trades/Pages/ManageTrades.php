<?php

namespace App\Filament\Resources\Trades\Pages;

use App\Filament\Resources\Trades\TradeResource;
use App\Models\Stock;
use App\Models\Trade;
use App\Services\BaiduOCRService;
use App\Services\DeepSeekService;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageTrades extends ManageRecords
{
    protected static string $resource = TradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('bulkImport')
                ->label('Bulk Import')
                ->modalHeading('Bulk Import Trades')
                ->schema([
                    FileUpload::make('image')
                        ->label('Upload Trade Image')
                        ->image()
                        ->disk('public')
                        ->directory('trade-imports')
                        ->maxSize(5120)
                        ->helperText('Upload an image of your trade records to parse it automatically.')
                        ->live()
                        ->previewable(false)
                        ->hintAction(
                            Action::make('parseImage')
                                ->label('Parse with DeepSeek')
                                ->icon('heroicon-m-sparkles')
                                ->action(function (Set $set, $state, DeepSeekService $deepSeekService, FileUpload $component) {
                                    if (! $state) {
                                        Notification::make()
                                            ->title('No image uploaded')
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    $path = $state;

                                    // Try to get the real path from TemporaryUploadedFile if available in raw state
                                    $rawState = $component->getRawState();
                                    $file = is_array($rawState) ? reset($rawState) : $rawState;

                                    if ($file instanceof TemporaryUploadedFile) {
                                        $path = $file->getRealPath();
                                    } elseif (! str_starts_with($path, '/')) {
                                        $path = Storage::disk('public')->path($state);
                                    }

                                    if (! file_exists($path)) {
                                        // Try one more time with local disk (private)
                                        $path = Storage::disk('local')->path($state);
                                    }

                                    if (! file_exists($path)) {
                                        Log::error("Bulk Import: Image file not found at {$path}", ['state' => $state, 'rawState' => $rawState]);

                                        Notification::make()
                                            ->title('Image file not found')
                                            ->body("The uploaded image could not be located at: {$path}")
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    $result = null;

                                    if (config('services.baidu.ocr.token')) {
                                        // Use Baidu + DeepSeek workflow
                                        $baiduOCRService = app(BaiduOCRService::class);
                                        $ocrData = $baiduOCRService->ocr($path);

                                        if ($ocrData['error_msg'] ?? null) {
                                            Notification::make()
                                                ->title('Baidu OCR Failed')
                                                ->body($ocrData['error_msg'])
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        if ($ocrData) {
                                            $result = $deepSeekService->parseTradeFromOCR($ocrData);
                                        }
                                    }

                                    if ($result && isset($result['trades'])) {
                                        $set('json_data', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                                        Notification::make()
                                            ->title('Image parsed successfully')
                                            ->success()
                                            ->send();
                                    } else {
                                        Notification::make()
                                            ->title('Failed to parse image')
                                            ->body('DeepSeek could not extract trade data.')
                                            ->danger()
                                            ->send();
                                    }
                                })
                        ),
                    TextInput::make('fallback_code')
                        ->label('Fallback Stock Code')
                        ->placeholder('e.g. 601166')
                        ->helperText('This code will be used if the image parsing fails to extract a stock code.')
                        ->live(),
                    TextEntry::make('import_preview')
                        ->label('Trade Preview')
                        ->state(function ($get) {
                            $json = $get('json_data');
                            if (! $json) {
                                return 'No data to preview';
                            }

                            $data = json_decode($json, true);
                            if (! $data || ! isset($data['trades']) || ! is_array($data['trades'])) {
                                return 'Invalid JSON format';
                            }

                            $html = '<div class="fi-ta-ctn overflow-hidden border border-gray-200 dark:border-white/10 rounded-xl">';
                            $html .= '<table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5 text-sm">';
                            $html .= '<thead class="bg-gray-50 dark:bg-white/5">';
                            $html .= '<tr>';
                            foreach (['Code', 'Name', 'Side', 'Qty', 'Price', 'Time'] as $header) {
                                $html .= '<th class="fi-ta-header-cell px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-950 dark:text-white">'.$header.'</th>';
                            }
                            $html .= '</tr></thead>';
                            $html .= '<tbody class="divide-y divide-gray-200 dark:divide-white/5 bg-white dark:bg-gray-900">';
                            foreach ($data['trades'] as $trade) {
                                $side = strtoupper($trade['side'] ?? '-');
                                $sideColorClasses = $side === 'BUY'
                                    ? 'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400 ring-danger-600/10'
                                    : 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400 ring-success-600/10';

                                $code = $trade['code'] ?? $get('fallback_code') ?? '-';

                                $html .= '<tr>';
                                $html .= '<td class="fi-ta-cell px-3 py-2 font-mono">'.$code.'</td>';
                                $html .= '<td class="fi-ta-cell px-3 py-2">'.($trade['name'] ?? '-').'</td>';
                                $html .= '<td class="fi-ta-cell px-3 py-2">';
                                $html .= '<span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset '.$sideColorClasses.'">'.$side.'</span>';
                                $html .= '</td>';
                                $html .= '<td class="fi-ta-cell px-3 py-2 font-mono">'.($trade['quantity'] ?? '0').'</td>';
                                $html .= '<td class="fi-ta-cell px-3 py-2 font-mono text-gray-950 dark:text-white">'.($trade['price'] ?? '0.00').'</td>';
                                $html .= '<td class="fi-ta-cell px-3 py-2 text-xs text-gray-500 dark:text-gray-400">'.($trade['time'] ?? '-').'</td>';
                                $html .= '</tr>';
                            }
                            $html .= '</tbody></table></div>';

                            return new HtmlString($html);
                        }),
                    Section::make('Raw JSON Data')
                        ->collapsible()
                        ->collapsed()
                        ->compact()
                        ->schema([
                            Textarea::make('json_data')
                                ->hiddenLabel()
                                ->rows(6)
                                ->required()
                                ->live(),
                        ]),
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
                            $code = $tradeData['code'] ?? $data['fallback_code'] ?? null;

                            // Validate required fields
                            if (
                                ! $code || ! isset($tradeData['quantity']) ||
                                ! isset($tradeData['price']) || ! isset($tradeData['time']) || ! isset($tradeData['side'])
                            ) {
                                $errors[] = 'Trade #'.($index + 1).': Missing required fields (code, quantity, price, time, side)';

                                continue;
                            }

                            // Auto prefix stock code
                            $prefixedCode = $stockService->autoPrefixCode($code);

                            // Find or create stock by prefixed code
                            $stock = Stock::firstOrCreate(
                                ['code' => $prefixedCode],
                                ['name' => $tradeData['name'] ?? $prefixedCode]
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
