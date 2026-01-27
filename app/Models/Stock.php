<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'peak_value',
        'current_price',
    ];

    public function dayPrices()
    {
        return $this->hasMany(DayPrice::class);
    }

    public function getCurrentPriceAttribute(): ?float
    {
        if ($this->attributes['current_price'] !== null) {
            return (float) $this->attributes['current_price'];
        }

        // Fallback to latest day price if current_price is not set
        return $this->dayPrices()->latest('date')->first()?->close_price;
    }

    public function holding()
    {
        return $this->hasOne(Holding::class);
    }

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }

    public function isInactive(): bool
    {
        $threshold = AppSetting::get('inactive_stocks_threshold', 30);
        $lastTrade = $this->trades()->latest('executed_at')->first();

        if (! $lastTrade) {
            return false; // No trades ever, not considered inactive
        }

        return $lastTrade->executed_at->diffInDays() > $threshold;
    }

    public function getXirrAttribute(): ?float
    {
        if ($this->type == 'index') {
            return null;
        }

        $trades = $this->trades()->orderBy('executed_at')->get();

        if ($trades->isEmpty()) {
            return null;
        }

        $cashFlows = [];
        $dates = [];

        foreach ($trades as $trade) {
            $cost = (float) $trade->quantity * (float) $trade->price;
            $date = $trade->executed_at->toDateString();

            if ($trade->side === 'buy') {
                $cashFlows[] = -$cost;
            } else {
                $cashFlows[] = $cost;
            }

            $dates[] = $date;
        }

        // Current valuation as final cash flow
        $holding = $this->holding;
        if ($holding && $holding->quantity > 0 && $this->current_price) {
            $holdingValue = (float) $holding->quantity * (float) $this->current_price;
            $cashFlows[] = $holdingValue;
            $dates[] = now()->toDateString();
        }

        return \App\Utilities\Helper::calculateXIRR($cashFlows, $dates);
    }

    public static function inactiveStocks()
    {
        $threshold = AppSetting::get('inactive_stocks_threshold', 30);
        $cutoffDate = Carbon::now()->subDays($threshold);

        // Only include stocks with trades, but none more recent than the threshold, and not index type
        return static::where('type', '!=', 'index')
            ->whereHas('trades')
            ->whereDoesntHave('trades', function ($subquery) use ($cutoffDate) {
                $subquery->where('executed_at', '>=', $cutoffDate);
            });
    }
}
