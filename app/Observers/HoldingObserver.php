<?php

namespace App\Observers;

use App\Models\Holding;
use App\Models\Trade;

class HoldingObserver
{
    public function updated(Holding $holding): void
    {
        $this->recalculateIfInitialValuesChanged($holding);
    }

    protected function recalculateIfInitialValuesChanged(Holding $holding): void
    {
        if (! $holding->isDirty(['initial_quantity', 'initial_cost'])) {
            return;
        }

        $trades = Trade::where('stock_id', $holding->stock_id)->get();

        $quantity = $holding->initial_quantity;
        $totalCost = $holding->initial_quantity * $holding->initial_cost;

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

        $holding->updateQuietly([
            'initial_quantity' => $holding->initial_quantity,
            'initial_cost' => $holding->initial_cost,
            'quantity' => $quantity,
            'total_cost' => $totalCost,
            'average_cost' => $averageCost,
        ]);
    }
}
