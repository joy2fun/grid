<?php

namespace App\Mcp\Tools;

use App\Models\DayPrice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class QueryDayPricesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Query historical daily price data from the database. Supports filtering by stock code/name, date range, and price conditions. Returns OHLCV data (open, high, low, close, volume) for technical analysis and price trend monitoring.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'stock_code' => ['nullable', 'string', 'max:20'],
            'stock_name' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'min_close' => ['nullable', 'numeric', 'min:0'],
            'max_close' => ['nullable', 'numeric', 'min:0'],
            'min_volume' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ], [
            'end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
            'min_close.min' => 'The min close price must be 0 or greater.',
            'max_close.min' => 'The max close price must be 0 or greater.',
            'limit.max' => 'The limit cannot exceed 1000 records.',
        ]);

        $limit = $validated['limit'] ?? 100;

        $query = DayPrice::query()
            ->with('stock')
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
            ->when($validated['start_date'] ?? null, function (Builder $query, string $startDate): void {
                $query->where('date', '>=', $startDate);
            })
            ->when($validated['end_date'] ?? null, function (Builder $query, string $endDate): void {
                $query->where('date', '<=', $endDate);
            })
            ->when($validated['min_close'] ?? null, function (Builder $query, float $minClose): void {
                $query->where('close_price', '>=', $minClose);
            })
            ->when($validated['max_close'] ?? null, function (Builder $query, float $maxClose): void {
                $query->where('close_price', '<=', $maxClose);
            })
            ->when($validated['min_volume'] ?? null, function (Builder $query, int $minVolume): void {
                $query->where('volume', '>=', $minVolume);
            })
            ->orderBy('date', 'desc')
            ->limit($limit);

        $prices = $query->get();

        if ($prices->isEmpty()) {
            return Response::text('No price data found matching the specified criteria.');
        }

        $summary = $this->buildSummary($prices);
        $priceList = $this->formatPriceList($prices);

        $output = "Found {$prices->count()} price records.\n\n";
        $output .= "=== Summary ===\n{$summary}\n\n";
        $output .= "=== Price Data (OHLCV) ===\n{$priceList}";

        return Response::text($output);
    }

    /**
     * Build summary statistics for the price data.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, DayPrice>  $prices
     */
    private function buildSummary($prices): string
    {
        $highestHigh = $prices->max('high_price');
        $lowestLow = $prices->min('low_price');
        $avgClose = $prices->avg('close_price');
        $avgVolume = $prices->avg('volume');
        $totalVolume = $prices->sum('volume');
        $priceChange = $prices->first()->close_price - $prices->last()->close_price;
        $priceChangePercent = $prices->last()->close_price > 0
            ? ($priceChange / $prices->last()->close_price) * 100
            : 0;

        $lines = [
            "Period: {$prices->last()->date} to {$prices->first()->date}",
            'Highest High: ¥'.number_format($highestHigh, 3),
            'Lowest Low: ¥'.number_format($lowestLow, 3),
            'Average Close: ¥'.number_format($avgClose, 3),
            'Price Change: ¥'.number_format($priceChange, 3).' ('.number_format($priceChangePercent, 2).'%)',
            'Average Volume: '.number_format($avgVolume, 0),
            'Total Volume: '.number_format($totalVolume, 0),
        ];

        return implode("\n", $lines);
    }

    /**
     * Format the price list for display.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, DayPrice>  $prices
     */
    private function formatPriceList($prices): string
    {
        $lines = [];
        $lines[] = sprintf(
            '%-10s | %-12s | %8s | %8s | %8s | %8s | %12s',
            'Date',
            'Stock',
            'Open',
            'High',
            'Low',
            'Close',
            'Volume'
        );
        $lines[] = str_repeat('-', 85);

        foreach ($prices as $price) {
            $stockCode = $price->stock?->code ?? 'N/A';
            $stockName = $price->stock?->name ?? 'N/A';
            $stockDisplay = mb_strimwidth($stockCode, 0, 12, '');

            $lines[] = sprintf(
                '%-10s | %-12s | %8s | %8s | %8s | %8s | %12s',
                $price->date,
                $stockDisplay,
                number_format($price->open_price, 3),
                number_format($price->high_price, 3),
                number_format($price->low_price, 3),
                number_format($price->close_price, 3),
                number_format($price->volume ?? 0, 0)
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'stock_code' => $schema->string()
                ->description('Filter by stock code (partial match supported, e.g., "000001"). Optional.'),
            'stock_name' => $schema->string()
                ->description('Filter by stock name (partial match supported, e.g., "Ping An"). Optional.'),
            'start_date' => $schema->string()
                ->description('Filter prices from this date onwards (ISO 8601 date, e.g., "2024-01-01"). Optional.'),
            'end_date' => $schema->string()
                ->description('Filter prices up to this date (ISO 8601 date, e.g., "2024-12-31"). Optional.'),
            'min_close' => $schema->number()
                ->description('Filter days with close price greater than or equal to this value. Optional.'),
            'max_close' => $schema->number()
                ->description('Filter days with close price less than or equal to this value. Optional.'),
            'min_volume' => $schema->integer()
                ->description('Filter days with volume greater than or equal to this value. Optional.'),
            'limit' => $schema->integer()
                ->description('Maximum number of price records to return (default: 100, max: 1000). Optional.'),
        ];
    }
}
