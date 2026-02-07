<?php

namespace App\Filament\Resources\Trades\Pages;

use App\Filament\Resources\Trades\TradeResource;
use App\Jobs\ImportTradeImageJob;
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
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageTrades extends ManageRecords
{
    protected static string $resource = TradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New'),
            Action::make('bulkImport')
                ->label('Import')
                ->modalHeading('Bulk Import Trades')
                ->schema([
                    FileUpload::make('image')
                        ->label('Upload Trade Image')
                        ->image()
                        ->disk('local')
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
                                    } elseif (! file_exists($path)) {
                                        $path = Storage::disk('local')->path($state);
                                    }

                                    if (! file_exists($path)) {
                                        // Try public disk as well for robustness
                                        $path = Storage::disk('public')->path($state);
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

                                    $baiduOCRService = app(BaiduOCRService::class);
                                    $ocrData = $baiduOCRService->ocr($path);

                                    if (! $ocrData || isset($ocrData['error_msg'])) {
                                        Notification::make()
                                            ->title('Baidu OCR Failed')
                                            ->body($ocrData['error_msg'] ?? 'unknown error')
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    $result = $deepSeekService->parseTradeFromOCR($ocrData);

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
                    Grid::make(1)
                        ->schema(function ($get) {
                            $json = $get('json_data');
                            if (! $json) {
                                return [
                                    TextEntry::make('no_data')
                                        ->hiddenLabel()
                                        ->default('No data to preview')
                                        ->size('text-sm'),
                                ];
                            }

                            $data = json_decode($json, true);
                            if (! $data || ! isset($data['trades']) || ! is_array($data['trades'])) {
                                return [
                                    TextEntry::make('invalid_json')
                                        ->hiddenLabel()
                                        ->default('Invalid JSON format')
                                        ->size('text-sm'),
                                ];
                            }

                            return [
                                RepeatableEntry::make('trades')
                                    // ->table([
                                    //     TableColumn::make('Code'),
                                    //     TableColumn::make('Name'),
                                    //     TableColumn::make('Side'),
                                    //     TableColumn::make('Qty'),
                                    //     TableColumn::make('Price'),
                                    //     TableColumn::make('Time'),
                                    // ])
                                    ->schema([
                                        TextEntry::make('code')
                                            ->weight('medium')
                                            ->color('primary'),
                                        TextEntry::make('name'),
                                        TextEntry::make('side')
                                            ->formatStateUsing(fn ($state) => strtoupper($state ?? '-'))
                                            ->badge()
                                            ->color(fn ($state) => strtoupper($state ?? '') === 'BUY' ? 'danger' : 'success'),
                                        TextEntry::make('quantity')
                                            ->weight('medium'),
                                        TextEntry::make('price')
                                            ->formatStateUsing(fn ($state) => number_format((float) ($state ?? 0), 2)),
                                        TextEntry::make('time')
                                            ->hiddenLabel()
                                            ->color('gray'),
                                    ])
                                    ->state(function () use ($data) {
                                        return $data['trades'];
                                    })
                                    ->columns(['md' => 6, 'default' => 3]),
                            ];
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
            Action::make('bulkImportBackground')
                ->label('Bulk Import')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('info')
                ->modalHeading('Bulk Import Trades (Background)')
                ->modalDescription('Upload multiple images of your trade records. They will be processed in the background.')
                ->schema([
                    FileUpload::make('images')
                        ->label('Upload Trade Images')
                        ->image()
                        ->multiple()
                        ->previewable(false)
                        ->disk('local')
                        ->directory('trade-imports')
                        ->maxSize(5120)
                        ->required()
                        ->helperText('Upload one or more images of your trade records.'),
                    TextInput::make('fallback_code')
                        ->label('Fallback Stock Code')
                        ->placeholder('e.g. 601166')
                        ->helperText('This code will be used if the image parsing fails to extract a stock code for any of the images.'),
                ])
                ->action(function (array $data) {
                    $images = $data['images'];
                    $fallbackCode = $data['fallback_code'] ?? null;

                    foreach ($images as $imagePath) {
                        ImportTradeImageJob::dispatch($imagePath, $fallbackCode);
                    }

                    Notification::make()
                        ->title('Import Started')
                        ->body(count($images).' image(s) have been queued for processing.')
                        ->success()
                        ->send();
                }),
            Action::make('backup')
                ->label('Backup')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $trades = Trade::with('stock')->get();

                    $backupData = [
                        'export_date' => now()->toIso8601String(),
                        'total_trades' => $trades->count(),
                        'trades' => $trades->map(function (Trade $trade) {
                            return [
                                'id' => $trade->id,
                                'stock_code' => $trade->stock->code,
                                'stock_name' => $trade->stock->name,
                                'side' => $trade->side,
                                'quantity' => $trade->quantity,
                                'price' => $trade->price,
                                'executed_at' => $trade->executed_at->toIso8601String(),
                                'created_at' => $trade->created_at->toIso8601String(),
                                'updated_at' => $trade->updated_at->toIso8601String(),
                            ];
                        })->toArray(),
                    ];

                    $jsonContent = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    $filename = 'trades-backup-'.now()->format('Y-m-d-His').'.json';

                    return response()->streamDownload(function () use ($jsonContent) {
                        echo $jsonContent;
                    }, $filename, [
                        'Content-Type' => 'application/json',
                    ]);
                }),
            Action::make('restore')
                ->label('Restore')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->modalHeading('Restore Trades from Backup')
                ->modalDescription('Upload a JSON backup file to restore trades. Existing trades will be skipped.')
                ->schema([
                    FileUpload::make('backup_file')
                        ->label('Backup JSON File')
                        ->acceptedFileTypes(['application/json', 'text/plain', '.json'])
                        ->disk('local')
                        ->directory('trade-backups')
                        ->maxSize(10240)
                        ->required()
                        ->helperText('Upload a trades backup JSON file.'),
                ])
                ->action(function (array $data, StockService $stockService) {
                    $filePaths = $data['backup_file'];

                    // FileUpload returns an array, get the first file
                    if (is_array($filePaths)) {
                        $filePath = $filePaths[0] ?? null;
                    } else {
                        $filePath = $filePaths;
                    }

                    if (! $filePath) {
                        Notification::make()
                            ->title('No File Uploaded')
                            ->body('Please upload a backup file.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $fullPath = Storage::disk('local')->path($filePath);

                    if (! file_exists($fullPath)) {
                        Notification::make()
                            ->title('File Not Found')
                            ->body('The uploaded backup file could not be found.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $jsonContent = file_get_contents($fullPath);
                    $backupData = json_decode($jsonContent, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Notification::make()
                            ->title('Invalid JSON')
                            ->body('The backup file contains invalid JSON: '.json_last_error_msg())
                            ->danger()
                            ->send();

                        return;
                    }

                    if (! isset($backupData['trades']) || ! is_array($backupData['trades'])) {
                        Notification::make()
                            ->title('Invalid Format')
                            ->body('The backup file must contain a "trades" array.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $importedCount = 0;
                    $skippedCount = 0;
                    $errors = [];

                    DB::beginTransaction();

                    try {
                        foreach ($backupData['trades'] as $index => $tradeData) {
                            $code = $tradeData['stock_code'] ?? null;

                            // Validate required fields
                            if (
                                ! $code || ! isset($tradeData['quantity']) ||
                                ! isset($tradeData['price']) || ! isset($tradeData['executed_at']) || ! isset($tradeData['side'])
                            ) {
                                $errors[] = 'Trade #'.($index + 1).': Missing required fields (stock_code, quantity, price, executed_at, side)';

                                continue;
                            }

                            // Auto prefix stock code
                            $prefixedCode = $stockService->autoPrefixCode($code);

                            // Find or create stock by prefixed code
                            $stock = Stock::firstOrCreate(
                                ['code' => $prefixedCode],
                                ['name' => $tradeData['stock_name'] ?? $prefixedCode]
                            );

                            // Parse the executed_at datetime to ensure proper comparison
                            $executedAt = \Carbon\Carbon::parse($tradeData['executed_at']);

                            // Check for duplicates (same time and stock_id)
                            $exists = Trade::where('stock_id', $stock->id)
                                ->where('executed_at', $executedAt)
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
                                'executed_at' => $executedAt,
                            ]);

                            $importedCount++;
                        }

                        DB::commit();

                        // Clean up the uploaded file
                        Storage::disk('local')->delete($filePath);

                        $message = "Restored {$importedCount} trades successfully.";
                        if ($skippedCount > 0) {
                            $message .= " Skipped {$skippedCount} duplicates.";
                        }

                        if (! empty($errors)) {
                            $message .= ' '.count($errors).' errors occurred.';
                        }

                        Notification::make()
                            ->title($importedCount > 0 ? 'Restore Completed' : 'No Trades Restored')
                            ->body($message)
                            ->when(! empty($errors), fn ($notification) => $notification->warning())
                            ->when(empty($errors) && $importedCount > 0, fn ($notification) => $notification->success())
                            ->when($importedCount === 0 && empty($errors), fn ($notification) => $notification->info())
                            ->send();
                    } catch (\Exception $e) {
                        DB::rollBack();

                        // Clean up the uploaded file even on error
                        Storage::disk('local')->delete($filePath);

                        Notification::make()
                            ->title('Restore Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
