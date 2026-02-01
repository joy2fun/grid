<?php

namespace App\Mcp\Tools;

use App\Models\Stock;
use App\Models\Trade;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class QueryTradesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Query trades from the database with filters. Supports two modes:
        1. Standard trade query: Filter by time range, stock code/name, trade side (buy/sell), grid ID.
        2. Advanced stock analysis: Find stocks where last trade was X days ago AND price changed by Y%.
        Returns trade details including stock information, price, quantity, and execution time.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            // Standard filters
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'stock_code' => ['nullable', 'string', 'max:20'],
            'stock_name' => ['nullable', 'string', 'max:100'],
            'side' => ['nullable', 'string', 'in:buy,sell'],
            'grid_id' => ['nullable', 'integer'],
            // Advanced filters
            'min_days_since_trade' => ['nullable', 'integer', 'min:1'],
            'min_price_change_percent' => ['nullable', 'numeric', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ], [
            'end_time.after_or_equal' => 'The end time must be after or equal to the start time.',
            'side.in' => 'The side must be either "buy" or "sell".',
            'limit.max' => 'The limit cannot exceed 1000 records.',
        ]);

        // Check if using advanced stock analysis mode
        if (($validated['min_days_since_trade'] ?? null) !== null || ($validated['min_price_change_percent'] ?? null) !== null) {
            return $this->handleStockAnalysisQuery($validated);
        }

        return $this->handleStandardTradeQuery($validated);
    }

    /**
     * Handle standard trade query.
     *
     * @param  array<string, mixed>  $validated
     */
    private function handleStandardTradeQuery(array $validated): Response
    {
        $limit = $validated['limit'] ?? 100;

        $query = Trade::query()
            ->with(['stock', 'grid'])
            ->when($validated['start_time'] ?? null, function (Builder $query, string $startTime): void {
                $query->where('executed_at', '>=', $startTime);
            })
            ->when($validated['end_time'] ?? null, function (Builder $query, string $endTime): void {
                $query->where('executed_at', '<=', $endTime);
            })
            ->when($validated['stock_code'] ?? null, function (Builder $query, string $stockCode): void {
                $query->whereHas('stock', function (Builder $q) use ($stockCode): void {
                    $q->where('code', 'like', "%{$stockCode}%");
                });
            })
            ->when($validated['stock_name'] ?? null, function (Builder $query, string $stockName): void {
                $query->whereHas('stock', function (Builder $q) use ($stockName): void {
                    $q->where('name', 'like', "%{$stockName}%");
                });
            })
            ->when($validated['side'] ?? null, function (Builder $query, string $side): void {
                $query->where('side', $side);
            })
            ->when($validated['grid_id'] ?? null, function (Builder $query, int $gridId): void {
                $query->where('grid_id', $gridId);
            })
            ->orderBy('executed_at', 'desc')
            ->limit($limit);

        $trades = $query->get();

        if ($trades->isEmpty()) {
            return Response::text('No trades found matching the specified criteria.');
        }

        $summary = $this->buildSummary($trades);
        $tradeList = $this->formatTradeList($trades);

        $output = "Found {$trades->count()} trades.\n\n";
        $output .= "=== Summary ===\n{$summary}\n\n";
        $output .= "=== Trades ===\n{$tradeList}";

        return Response::text($output);
    }

    /**
     * Handle advanced stock analysis query.
     * Finds stocks where last trade was X days ago AND price changed by Y%.
     *
     * @param  array<string, mixed>  $validated
     */
    private function handleStockAnalysisQuery(array $validated): Response
    {
        $minDays = $validated['min_days_since_trade'] ?? 30;
        $minPriceChangePercent = $validated['min_price_change_percent'] ?? 10;
        $limit = $validated['limit'] ?? 100;

        $stocks = Stock::whereHas('trades')->get();
        $results = [];

        foreach ($stocks as $stock) {
            $lastTrade = $stock->trades()->orderBy('executed_at', 'desc')->first();

            if (! $lastTrade) {
                continue;
            }

            $daysSinceLastTrade = Carbon::parse($lastTrade->executed_at)->diffInDays(now());

            // Skip if not enough days passed
            if ($daysSinceLastTrade < $minDays) {
                continue;
            }

            // Calculate price change
            $priceChange = $stock->current_price - $lastTrade->price;
            $priceChangePercent = $lastTrade->price > 0
                ? ($priceChange / $lastTrade->price) * 100
                : 0;

            // Skip if price change doesn't meet threshold
            if (abs($priceChangePercent) < $minPriceChangePercent) {
                continue;
            }

            $results[] = [
                'stock' => $stock,
                'last_trade' => $lastTrade,
                'days_since_trade' => (int) $daysSinceLastTrade,
                'price_change_percent' => round($priceChangePercent, 2),
                'last_trade_price' => $lastTrade->price,
                'current_price' => $stock->current_price,
            ];
        }

        // Apply limit
        $results = array_slice($results, 0, $limit);

        if (empty($results)) {
            return Response::text("No stocks found where last trade was >= {$minDays} days ago AND price changed by >= {$minPriceChangePercent}%.");
        }

        $output = $this->formatStockAnalysisResults($results, $minDays, $minPriceChangePercent);

        return Response::text($output);
    }

    /**
     * Build summary statistics for the trades.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Trade>  $trades
     */
    private function buildSummary($trades): string
    {
        $totalBuyQuantity = $trades->where('side', 'buy')->sum('quantity');
        $totalSellQuantity = $trades->where('side', 'sell')->sum('quantity');
        $totalBuyValue = $trades->where('side', 'buy')->sum(fn (Trade $trade): float => $trade->price * $trade->quantity);
        $totalSellValue = $trades->where('side', 'sell')->sum(fn (Trade $trade): float => $trade->price * $trade->quantity);
        $avgPrice = $trades->avg('price');

        $lines = [
            "Total Buy Quantity: {$totalBuyQuantity}",
            "Total Sell Quantity: {$totalSellQuantity}",
            'Total Buy Value: ¥'.number_format($totalBuyValue, 2),
            'Total Sell Value: ¥'.number_format($totalSellValue, 2),
            'Net Quantity: '.($totalBuyQuantity - $totalSellQuantity),
            'Net Value: ¥'.number_format($totalSellValue - $totalBuyValue, 2),
            'Average Price: ¥'.number_format($avgPrice, 3),
        ];

        return implode("\n", $lines);
    }

    /**
     * Format the trades list for display.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Trade>  $trades
     */
    private function formatTradeList($trades): string
    {
        $lines = [];

        foreach ($trades as $trade) {
            $stockCode = $trade->stock?->code ?? 'N/A';
            $stockName = $trade->stock?->name ?? 'N/A';
            $gridName = $trade->grid?->name ?? 'N/A';
            $value = $trade->price * $trade->quantity;

            $lines[] = sprintf(
                '[%s] %s %s | %s (%s) | %s shares @ ¥%s = ¥%s | Grid: %s',
                $trade->executed_at->format('Y-m-d H:i:s'),
                strtoupper($trade->side),
                $stockCode,
                $stockName,
                $trade->side === 'buy' ? '买入' : '卖出',
                number_format($trade->quantity),
                number_format($trade->price, 3),
                number_format($value, 2),
                $gridName
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Format stock analysis results for display.
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    private function formatStockAnalysisResults(array $results, int $minDays, float $minPriceChangePercent): string
    {
        $count = count($results);
        $output = "Found {$count} stocks where last trade was >= {$minDays} days ago AND price changed by >= {$minPriceChangePercent}%.\n\n";

        $output .= sprintf(
            "%-12s | %-20s | %8s | %12s | %12s | %10s | %12s\n",
            'Code',
            'Name',
            'Days',
            'Last Price',
            'Current',
            'Change%',
            'Last Trade'
        );
        $output .= str_repeat('-', 100)."\n";

        foreach ($results as $result) {
            $stock = $result['stock'];
            $lastTrade = $result['last_trade'];
            $changeSign = $result['price_change_percent'] >= 0 ? '+' : '';

            $output .= sprintf(
                "%-12s | %-20s | %8d | %12s | %12s | %9s%% | %12s\n",
                $stock->code,
                mb_strimwidth($stock->name, 0, 20, '...'),
                $result['days_since_trade'],
                '¥'.number_format($result['last_trade_price'], 3),
                '¥'.number_format($result['current_price'], 3),
                $changeSign.$result['price_change_percent'],
                Carbon::parse($lastTrade->executed_at)->format('Y-m-d')
            );
        }

        $output .= "\nTip: Use standard filters (stock_code, start_time, etc.) to query specific trades for these stocks.";

        return $output;
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            // Standard filters
            'start_time' => $schema->string()
                ->description('Filter trades executed after this time (ISO 8601 datetime, e.g., "2024-01-01T00:00:00Z"). Optional.'),
            'end_time' => $schema->string()
                ->description('Filter trades executed before this time (ISO 8601 datetime, e.g., "2024-12-31T23:59:59Z"). Optional.'),
            'stock_code' => $schema->string()
                ->description('Filter by stock code (partial match supported, e.g., "000001"). Optional.'),
            'stock_name' => $schema->string()
                ->description('Filter by stock name (partial match supported, e.g., "Ping An"). Optional.'),
            'side' => $schema->string()
                ->description('Filter by trade side: "buy" or "sell". Optional.'),
            'grid_id' => $schema->integer()
                ->description('Filter by grid ID. Optional.'),
            // Advanced filters for stock analysis
            'min_days_since_trade' => $schema->integer()
                ->description('ADVANCED: Find stocks where last trade was at least X days ago. When used with min_price_change_percent, switches to stock analysis mode. Optional.'),
            'min_price_change_percent' => $schema->number()
                ->description('ADVANCED: Find stocks where price changed by at least Y% since last trade. When used with min_days_since_trade, switches to stock analysis mode. Optional.'),
            'limit' => $schema->integer()
                ->description('Maximum number of records to return (default: 100, max: 1000). Optional.'),
        ];
    }
}
