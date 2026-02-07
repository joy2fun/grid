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
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
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
            CreateAction::make()
                ->label(__('app.common.new'))
                ->icon('heroicon-o-plus'),
            ActionGroup::make([
                Action::make('bulkImport')
                    ->label(__('app.common.import'))
                    ->icon('heroicon-o-document-arrow-up')
                    ->modalHeading(__('app.import_export.label'))
                    ->schema([
                        FileUpload::make('image')
                            ->label(__('app.import_export.upload_image'))
                            ->image()
                            ->disk('local')
                            ->directory('trade-imports')
                            ->maxSize(5120)
                            ->helperText(__('app.import_export.image_helper'))
                            ->live()
                            ->previewable(false)
                            ->hintAction(
                                Action::make('parseImage')
                                    ->label(__('app.import_export.parse_with_deepseek'))
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
                            ->label(__('app.import_export.fallback_code'))
                            ->placeholder(__('app.import_export.fallback_placeholder'))
                            ->helperText(__('app.import_export.fallback_helper'))
                            ->live(),
                        Grid::make(1)
                            ->schema(function ($get) {
                                $json = $get('json_data');
                                if (! $json) {
                                    return [
                                        TextEntry::make('no_data')
                                            ->hiddenLabel()
                                            ->default(__('app.import_export.no_data'))
                                            ->size('text-sm'),
                                    ];
                                }

                                $data = json_decode($json, true);
                                if (! $data || ! isset($data['trades']) || ! is_array($data['trades'])) {
                                    return [
                                        TextEntry::make('invalid_json')
                                            ->hiddenLabel()
                                            ->default(__('app.import_export.invalid_json'))
                                            ->size('text-sm'),
                                    ];
                                }

                                return [
                                    RepeatableEntry::make('trades')
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
                        Section::make(__('app.import_export.raw_json'))
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
                                ->title(__('app.notifications.invalid_json'))
                                ->body('Please ensure your JSON is properly formatted.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! isset($jsonData['trades']) || ! is_array($jsonData['trades'])) {
                            Notification::make()
                                ->title(__('app.notifications.invalid_format'))
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
                                ->title($importedCount > 0 ? __('app.notifications.import_completed') : __('app.notifications.import_failed'))
                                ->body($message)
                                ->when(! empty($errors), fn ($notification) => $notification->danger())
                                ->when(empty($errors) && $importedCount > 0, fn ($notification) => $notification->success())
                                ->send();
                        } catch (\Exception $e) {
                            DB::rollBack();

                            Notification::make()
                                ->title(__('app.notifications.import_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('bulkImportBackground')
                    ->label(__('app.import_export.bulk_import_bg'))
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('info')
                    ->modalHeading(__('app.import_export.bulk_import_bg'))
                    ->modalDescription(__('app.import_export.bulk_desc'))
                    ->schema([
                        FileUpload::make('images')
                            ->label(__('app.import_export.upload_images'))
                            ->image()
                            ->multiple()
                            ->previewable(false)
                            ->disk('local')
                            ->directory('trade-imports')
                            ->maxSize(5120)
                            ->required()
                            ->helperText(__('app.import_export.bulk_desc')),
                        TextInput::make('fallback_code')
                            ->label(__('app.import_export.fallback_code'))
                            ->placeholder(__('app.import_export.fallback_placeholder'))
                            ->helperText(__('app.import_export.fallback_helper')),
                    ])
                    ->action(function (array $data) {
                        $images = $data['images'];
                        $fallbackCode = $data['fallback_code'] ?? null;

                        foreach ($images as $imagePath) {
                            ImportTradeImageJob::dispatch($imagePath, $fallbackCode);
                        }

                        Notification::make()
                            ->title(__('app.notifications.import_completed'))
                            ->body(count($images).' image(s) have been queued for processing.')
                            ->success()
                            ->send();
                    }),
                Action::make('backup')
                    ->label(__('app.common.backup'))
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
                    ->label(__('app.common.restore'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->modalHeading(__('app.common.restore'))
                    ->modalDescription('Upload a JSON backup file to restore trades. Existing trades will be skipped.')
                    ->schema([
                        FileUpload::make('backup_file')
                            ->label(__('app.import_export.backup_file'))
                            ->acceptedFileTypes(['application/json', 'text/plain', '.json'])
                            ->disk('local')
                            ->directory('trade-backups')
                            ->maxSize(10240)
                            ->required()
                            ->helperText(__('app.import_export.backup_helper')),
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
                                ->title(__('app.notifications.no_file'))
                                ->body('Please upload a backup file.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $fullPath = Storage::disk('local')->path($filePath);

                        if (! file_exists($fullPath)) {
                            Notification::make()
                                ->title(__('app.notifications.file_not_found'))
                                ->body('The uploaded backup file could not be found.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $jsonContent = file_get_contents($fullPath);
                        $backupData = json_decode($jsonContent, true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            Notification::make()
                                ->title(__('app.notifications.invalid_json'))
                                ->body('The backup file contains invalid JSON: '.json_last_error_msg())
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! isset($backupData['trades']) || ! is_array($backupData['trades'])) {
                            Notification::make()
                                ->title(__('app.notifications.invalid_format'))
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
                                ->title($importedCount > 0 ? __('app.notifications.restore_completed') : __('app.notifications.import_failed'))
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
                                ->title(__('app.notifications.restore_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
                ->label(__('app.import_export.label'))
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('gray')
                ->button(),
        ];
    }
}
