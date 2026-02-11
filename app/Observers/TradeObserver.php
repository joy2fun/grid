<?php

namespace App\Observers;

use App\Models\Holding;
use App\Models\Trade;

class TradeObserver
{
    public function created(Trade $trade): void
    {
        $this->recalculateHolding($trade->stock_id);
    }

    public function updated(Trade $trade): void
    {
        $this->recalculateHolding($trade->stock_id);

        if ($trade->isDirty('stock_id')) {
            $this->recalculateHolding($trade->getOriginal('stock_id'));
        }
    }

    public function deleted(Trade $trade): void
    {
        $this->recalculateHolding($trade->stock_id);
    }

    protected function recalculateHolding(int $stockId): void
    {
        $trades = Trade::where('stock_id', $stockId)
            ->orderBy('executed_at')
            ->orderBy('id')
            ->get();

        // Get the holding with initial values but don't update them in this calculation
        $holding = Holding::where('stock_id', $stockId)->first();
        $initialQuantity = $holding?->initial_quantity ?? 0;
        $initialCost = $holding?->initial_cost ?? 0;

        $quantity = (float) $initialQuantity;
        $totalCost = (float) ($initialQuantity * $initialCost);

        foreach ($trades as $trade) {
            switch ($trade->type) {
                case 'buy':
                    $quantity += (float) $trade->quantity;
                    $totalCost += (float) $trade->quantity * (float) $trade->price;

                    break;

                case 'sell':
                    $quantity -= (float) $trade->quantity;
                    $totalCost -= (float) $trade->quantity * (float) $trade->price;

                    break;

                case 'dividend':
                    // Dividend is cash flow, doesn't affect holdings quantity or cost
                    // Total cost remains unchanged
                    break;

                case 'stock_split':
                    // Stock split: e.g., 10:1 split, ratio = 0.1 (every 10 shares become 1)
                    // Or reverse split: 1:10, ratio = 10 (every 1 share becomes 10)
                    $ratio = (float) ($trade->split_ratio ?? 1);
                    if ($ratio > 0) {
                        $quantity = $quantity * $ratio;
                        // Cost basis remains the same, only quantity changes
                    }

                    break;

                case 'stock_dividend':
                    // Stock dividend (送股/转增): e.g., 10送3, ratio = 0.3
                    // Add new shares without changing total cost
                    $ratio = (float) ($trade->split_ratio ?? 0);
                    if ($ratio > 0) {
                        $additionalShares = $quantity * $ratio;
                        $quantity += $additionalShares;
                        // Total cost remains unchanged, average cost decreases
                    }

                    break;
            }
        }

        $averageCost = $quantity > 0 ? $totalCost / $quantity : 0;

        // Only update the calculated fields, preserve initial values
        Holding::updateOrCreate(
            ['stock_id' => $stockId],
            [
                'quantity' => $quantity,
                'total_cost' => $totalCost,
                'average_cost' => $averageCost,
            ]
        );
    }
}
