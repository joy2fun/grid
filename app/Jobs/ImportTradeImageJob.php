<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Models\Trade;
use App\Services\BaiduOCRService;
use App\Services\DeepSeekService;
use App\Services\StockService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportTradeImageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $imagePath,
        public ?string $fallbackCode = null,
        public string $disk = 'public',
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        BaiduOCRService $baiduOCRService,
        DeepSeekService $deepSeekService,
        StockService $stockService
    ): void {
        $fullPath = Storage::disk($this->disk)->path($this->imagePath);

        if (! file_exists($fullPath)) {
            // Try to fallback to other common disks
            $alternativeDisks = ['public', 'local'];
            $found = false;

            foreach ($alternativeDisks as $altDisk) {
                if ($altDisk === $this->disk) {
                    continue;
                }
                $altPath = Storage::disk($altDisk)->path($this->imagePath);
                if (file_exists($altPath)) {
                    $fullPath = $altPath;
                    $found = true;
                    Log::info("ImportTradeImageJob: Found file on alternative disk [{$altDisk}] at {$fullPath}");
                    break;
                }
            }

            if (! $found) {
                Log::error("ImportTradeImageJob: Image file not found at {$fullPath}", [
                    'imagePath' => $this->imagePath,
                    'disk' => $this->disk,
                    'storage_path' => storage_path(),
                ]);

                return;
            }
        }

        $ocrData = $baiduOCRService->ocr($fullPath);

        if (! $ocrData || isset($ocrData['error_msg'])) {
            Log::error('ImportTradeImageJob: Baidu OCR Failed', [
                'error' => $ocrData['error_msg'] ?? 'unknown error',
                'image' => $this->imagePath,
            ]);

            return;
        }

        $result = $deepSeekService->parseTradeFromOCR($ocrData);

        if (! $result || ! isset($result['trades'])) {
            Log::error('ImportTradeImageJob: DeepSeek could not extract trade data.', [
                'image' => $this->imagePath,
            ]);

            return;
        }

        $importedCount = 0;
        $skippedCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($result['trades'] as $index => $tradeData) {
                $code = $tradeData['code'] ?? $this->fallbackCode;

                if (
                    ! $code || ! isset($tradeData['quantity']) ||
                    ! isset($tradeData['price']) || ! isset($tradeData['time']) || ! isset($tradeData['side'])
                ) {
                    $errors[] = 'Trade #'.($index + 1).': Missing required fields';

                    continue;
                }

                $prefixedCode = $stockService->autoPrefixCode($code);

                $stock = Stock::firstOrCreate(
                    ['code' => $prefixedCode],
                    ['name' => $tradeData['name'] ?? $prefixedCode]
                );

                $exists = Trade::where('stock_id', $stock->id)
                    ->where('executed_at', $tradeData['time'])
                    ->exists();

                if ($exists) {
                    $skippedCount++;

                    continue;
                }

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

            Log::info("ImportTradeImageJob: Finished processing {$this->imagePath}", [
                'imported' => $importedCount,
                'skipped' => $skippedCount,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("ImportTradeImageJob: Transaction failed for {$this->imagePath}: ".$e->getMessage());
            throw $e;
        }
    }
}
