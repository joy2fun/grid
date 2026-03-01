<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Grid extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'name',
        'initial_amount',
        'grid_interval',
        'last_trade_at',
        'last_trade_price',
        'xirr',
    ];

    protected function casts(): array
    {
        return [
            'last_trade_at' => 'datetime',
            'last_trade_price' => 'decimal:4',
            'xirr' => 'decimal:6',
        ];
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }

    /**
     * Get formatted last trade date (cached in database column)
     * Formats the stored last_trade_at datetime as human readable
     */
    public function getLastTradeAtFormattedAttribute(): string
    {
        $value = $this->getAttributes()['last_trade_at'] ?? null;

        return $value ? Carbon::parse($value)->diffForHumans() : '-';
    }

    /**
     * Calculate the percentage change between last trade price and current stock price
     *
     * @return float|null The percentage change, or null if data is not available
     */
    public function getPriceChangePercentageAttribute(): ?float
    {
        $lastTradePrice = $this->getAttributes()['last_trade_price'] ?? null;
        $currentPrice = $this->stock?->current_price;

        if ($lastTradePrice === null || $currentPrice === null || $lastTradePrice == 0) {
            return null;
        }

        return (($currentPrice - (float) $lastTradePrice) / (float) $lastTradePrice) * 100;
    }

    /**
     * Get XIRR from cache or calculate if not cached
     * Uses 0 as sentinel value in database to indicate "calculated but null"
     */
    public function getXirrAttribute(?float $value): ?float
    {
        // Return null for sentinel value 0 (no valid XIRR)
        if ($value === 0.0 || $value === 0) {
            return null;
        }

        // Return cached value if available
        if ($value !== null) {
            return $value;
        }

        // Calculate on-demand if not cached (during transition)
        $metrics = $this->getMetrics();

        return $metrics['xirr'] ?? null;
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
            $date = $trade->executed_at->toDateString();

            switch ($trade->type) {
                case 'buy':
                    $cost = (float) $trade->quantity * (float) $trade->price;
                    $cash -= $cost;
                    $shares += (float) $trade->quantity;
                    $cashFlows[] = -$cost;
                    $dates[] = $date;

                    if ($cash < $maxCashOccupied) {
                        $maxCashOccupied = $cash;
                    }

                    break;

                case 'sell':
                    $proceeds = (float) $trade->quantity * (float) $trade->price;
                    $cash += $proceeds;
                    $shares -= (float) $trade->quantity;
                    $cashFlows[] = $proceeds;
                    $dates[] = $date;

                    break;

                case 'dividend':
                    // Dividend is cash income
                    $dividendAmount = (float) $trade->quantity * (float) $trade->price;
                    if ($dividendAmount > 0) {
                        $cash += $dividendAmount;
                        $cashFlows[] = $dividendAmount;
                        $dates[] = $date;
                    }

                    break;

                case 'stock_split':
                    $ratio = (float) ($trade->split_ratio ?? 1);
                    if ($ratio > 0) {
                        $shares = $shares * $ratio;
                    }

                    break;

                case 'stock_dividend':
                    $ratio = (float) ($trade->split_ratio ?? 0);
                    if ($ratio > 0) {
                        $additionalShares = $shares * $ratio;
                        $shares += $additionalShares;
                    }

                    break;
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
