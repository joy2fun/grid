<?php

namespace App\Mcp\Tools;

use App\Models\Stock;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class QueryStocksTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Query stocks from the database with filters. Supports filtering by stock code, name, price range, and rise percentage. Returns stock details including current price, rise percentage, and peak value.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'min_rise' => ['nullable', 'numeric'],
            'max_rise' => ['nullable', 'numeric'],
            'has_trades' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ], [
            'max_price.min' => 'The max price must be 0 or greater.',
            'min_price.min' => 'The min price must be 0 or greater.',
            'limit.max' => 'The limit cannot exceed 1000 records.',
        ]);

        $limit = $validated['limit'] ?? 100;

        $query = Stock::query()
            ->when($validated['code'] ?? null, function (Builder $query, string $code): void {
                $query->where('code', 'like', "%{$code}%");
            })
            ->when($validated['name'] ?? null, function (Builder $query, string $name): void {
                $query->where('name', 'like', "%{$name}%");
            })
            ->when($validated['min_price'] ?? null, function (Builder $query, float $minPrice): void {
                $query->where('current_price', '>=', $minPrice);
            })
            ->when($validated['max_price'] ?? null, function (Builder $query, float $maxPrice): void {
                $query->where('current_price', '<=', $maxPrice);
            })
            ->when($validated['min_rise'] ?? null, function (Builder $query, float $minRise): void {
                $query->where('rise_percentage', '>=', $minRise);
            })
            ->when($validated['max_rise'] ?? null, function (Builder $query, float $maxRise): void {
                $query->where('rise_percentage', '<=', $maxRise);
            })
            ->when($validated['has_trades'] ?? null, function (Builder $query, bool $hasTrades): void {
                if ($hasTrades) {
                    $query->whereHas('trades');
                } else {
                    $query->whereDoesntHave('trades');
                }
            })
            ->orderBy('code')
            ->limit($limit);

        $stocks = $query->get();

        if ($stocks->isEmpty()) {
            return Response::text('No stocks found matching the specified criteria.');
        }

        $output = $this->formatStocksList($stocks);

        return Response::text($output);
    }

    /**
     * Format the stocks list for display.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Stock>  $stocks
     */
    private function formatStocksList($stocks): string
    {
        $lines = [];
        $lines[] = "Found {$stocks->count()} stocks.\n";
        $lines[] = '=== Stocks ===';
        $lines[] = sprintf(
            '%-12s | %-20s | %10s | %8s | %10s | %8s',
            'Code',
            'Name',
            'Price',
            'Rise%',
            'Peak',
            'Trades'
        );
        $lines[] = str_repeat('-', 80);

        foreach ($stocks as $stock) {
            $tradesCount = $stock->trades()->count();
            $riseColor = $stock->rise_percentage > 0 ? '+' : '';

            $lines[] = sprintf(
                '%-12s | %-20s | %10s | %7s%% | %10s | %8s',
                $stock->code,
                mb_strimwidth($stock->name, 0, 20, '...'),
                $stock->current_price !== null ? '¥'.number_format($stock->current_price, 3) : 'N/A',
                $stock->rise_percentage !== null ? $riseColor.number_format($stock->rise_percentage, 2) : 'N/A',
                $stock->peak_value !== null ? '¥'.number_format($stock->peak_value, 3) : 'N/A',
                $tradesCount
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
            'code' => $schema->string()
                ->description('Filter by stock code (partial match supported, e.g., "000001"). Optional.'),
            'name' => $schema->string()
                ->description('Filter by stock name (partial match supported, e.g., "Ping An"). Optional.'),
            'min_price' => $schema->number()
                ->description('Filter stocks with current price greater than or equal to this value. Optional.'),
            'max_price' => $schema->number()
                ->description('Filter stocks with current price less than or equal to this value. Optional.'),
            'min_rise' => $schema->number()
                ->description('Filter stocks with rise percentage greater than or equal to this value (e.g., 10 for +10%). Optional.'),
            'max_rise' => $schema->number()
                ->description('Filter stocks with rise percentage less than or equal to this value (e.g., -10 for -10%). Optional.'),
            'has_trades' => $schema->boolean()
                ->description('Filter stocks that have trades (true) or no trades (false). Optional.'),
            'limit' => $schema->integer()
                ->description('Maximum number of stocks to return (default: 100, max: 1000). Optional.'),
        ];
    }
}
