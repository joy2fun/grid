<?php

namespace App\Observers;

use App\Models\Trade;
use App\Models\Holding;

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

        $quantity = 0;
        $totalCost = 0;

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
