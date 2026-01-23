<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SaveTradesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Save one or more trades to the database. This tool accepts trade data including stock code, side (buy/sell), quantity, price, and execution time. If a stock does not exist, it will be automatically created.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'trades' => ['required', 'array', 'min:1'],
            'trades.*.code' => ['required', 'string', 'max:20'],
            'trades.*.side' => ['required', 'string', 'in:buy,sell'],
            'trades.*.quantity' => ['required', 'integer', 'min:1'],
            'trades.*.price' => ['required', 'numeric', 'min:0'],
            'trades.*.time' => ['required', 'date'],
            'trades.*.grid_id' => ['nullable', 'integer', 'exists:grids,id'],
        ], [
            'trades.required' => 'You must provide at least one trade in the "trades" array.',
            'trades.*.code.required' => 'Each trade must include a stock code.',
            'trades.*.side.required' => 'Each trade must specify a side (buy or sell).',
            'trades.*.side.in' => 'The side must be either "buy" or "sell".',
            'trades.*.quantity.required' => 'Each trade must include a quantity.',
            'trades.*.quantity.min' => 'Quantity must be at least 1.',
            'trades.*.price.required' => 'Each trade must include a price.',
            'trades.*.price.min' => 'Price must be 0 or greater.',
            'trades.*.time.required' => 'Each trade must include an execution time.',
            'trades.*.time.date' => 'The execution time must be a valid date.',
            'trades.*.grid_id.exists' => 'The specified grid does not exist.',
        ]);

        $savedCount = 0;
        $errors = [];

        foreach ($validated['trades'] as $index => $tradeData) {
            try {
                // Find or create the stock
                $stock = \App\Models\Stock::firstOrCreate(
                    ['code' => $tradeData['code']],
                    ['name' => $tradeData['code']]
                );

                // Create the trade
                \App\Models\Trade::create([
                    'grid_id' => $tradeData['grid_id'] ?? null,
                    'stock_id' => $stock->id,
                    'side' => $tradeData['side'],
                    'quantity' => $tradeData['quantity'],
                    'price' => $tradeData['price'],
                    'executed_at' => $tradeData['time'],
                ]);

                $savedCount++;
            } catch (\Exception $e) {
                $errors[] = "Trade #{$index}: {$e->getMessage()}";
            }
        }

        $totalCount = count($validated['trades']);
        $message = "Successfully saved {$savedCount} out of {$totalCount} trades.";

        if (! empty($errors)) {
            $message .= "\n\nErrors:\n".implode("\n", $errors);
        }

        return Response::text($message);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trades' => $schema->array()
                ->description('An array of trade objects. Each object must include: code (string), side (string: "buy" or "sell"), quantity (integer), price (number), time (string: ISO 8601 datetime), and optionally grid_id (integer).')
                ->required(),
        ];
    }
}
