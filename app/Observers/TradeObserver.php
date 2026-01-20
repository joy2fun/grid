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
        $trades = Trade::where('stock_id', $stockId)->get();

        // Get the holding with initial values but don't update them in this calculation
        $holding = Holding::where('stock_id', $stockId)->first();
        $initialQuantity = $holding?->initial_quantity ?? 0;
        $initialCost = $holding?->initial_cost ?? 0;

        $quantity = $initialQuantity;
        $totalCost = $initialQuantity * $initialCost;

        foreach ($trades as $trade) {
            if ($trade->side === 'buy') {
                $quantity += $trade->quantity;
                $totalCost += $trade->quantity * $trade->price;
            } elseif ($trade->side === 'sell') {
                $quantity -= $trade->quantity;
                $totalCost -= $trade->quantity * $trade->price;
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
