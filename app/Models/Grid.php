<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grid extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'name',
        'initial_amount',
        'grid_interval',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }

    /**
     * Calculate metrics for the grid based on trade history.
     */
    public function getMetrics(): array
    {
        $trades = $this->trades()->orderBy('executed_at')->get();
        $stock = $this->stock;

        // Get current price
        $currentPrice = \App\Models\DayPrice::query()
            ->where('stock_id', $this->stock_id)
            ->orderBy('date', 'desc')
            ->first()
            ?->close_price ?? 0;

        $cash = 0;
        $shares = 0;
        $cashFlows = [];
        $dates = [];
        $maxCashOccupied = 0;

        foreach ($trades as $trade) {
            $cost = (float) $trade->quantity * (float) $trade->price;
            $date = $trade->executed_at->toDateString();

            if ($trade->side === 'buy') {
                $cash -= $cost;
                $shares += $trade->quantity;
                $cashFlows[] = -$cost;
            } else {
                $cash += $cost;
                $shares -= $trade->quantity;
                $cashFlows[] = $cost;
            }

            $dates[] = $date;

            if ($cash < $maxCashOccupied) {
                $maxCashOccupied = $cash;
            }
        }

        $holdingValue = $shares * (float) $currentPrice;

        // Add final "liquidation" for XIRR calculation
        if ($holdingValue > 0) {
            $cashFlows[] = (float) $holdingValue;
            $dates[] = now()->toDateString();
        }

        $xirr = \App\Utilities\Helper::calculateXIRR($cashFlows, $dates);

        return [
            'xirr' => $xirr,
            'total_profit' => $cash + $holdingValue,
            'net_cash' => $cash,
            'holding_value' => $holdingValue,
            'max_cash_occupied' => abs($maxCashOccupied),
            'trades_count' => $trades->count(),
            'final_shares' => $shares,
            'final_price' => $currentPrice,
        ];
    }
}
