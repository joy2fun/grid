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
        'rise_percentage',
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

    public function getLastTradeAtAttribute(): string
    {
        $dt = $this->trades()
            ->whereIn('type', ['buy', 'sell'])
            ->max('executed_at');

        return $dt ? Carbon::parse($dt)->diffForHumans() : '-';
    }

    public function isInactive(): bool
    {
        $threshold = AppSetting::get('inactive_stocks_threshold', 30);
        $lastTrade = $this->trades()
            ->whereIn('type', ['buy', 'sell'])
            ->latest('executed_at')
            ->first();

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

        $trades = $this->trades;

        if ($trades->isEmpty()) {
            return null;
        }

        $trades = collect($trades)->sortBy('executed_at');

        $cashFlows = [];
        $dates = [];

        foreach ($trades as $trade) {
            $date = $trade->executed_at->toDateString();

            switch ($trade->type) {
                case 'buy':
                    $cost = (float) $trade->quantity * (float) $trade->price;
                    $cashFlows[] = -$cost;
                    $dates[] = $date;

                    break;

                case 'sell':
                    $proceeds = (float) $trade->quantity * (float) $trade->price;
                    $cashFlows[] = $proceeds;
                    $dates[] = $date;

                    break;

                case 'dividend':
                    // Dividend is positive cash flow (no cost)
                    $dividendAmount = (float) $trade->quantity * (float) $trade->price;
                    if ($dividendAmount > 0) {
                        $cashFlows[] = $dividendAmount;
                        $dates[] = $date;
                    }

                    break;

                case 'stock_split':
                case 'stock_dividend':
                    // Stock splits and dividends don't directly affect cash flow
                    // They affect quantity which impacts final holding value
                    break;
            }
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

        // Only include stocks with buy/sell trades, but none more recent than the threshold, and not index type
        return static::where('type', '!=', 'index')
            ->whereHas('trades', function ($query) {
                $query->whereIn('type', ['buy', 'sell']);
            })
            ->whereDoesntHave('trades', function ($subquery) use ($cutoffDate) {
                $subquery->whereIn('type', ['buy', 'sell'])
                    ->where('executed_at', '>=', $cutoffDate);
            });
    }
}
