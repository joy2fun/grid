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

    /**
     * Get formatted last trade date (cached in database column)
     * Formats the stored last_trade_at datetime as human readable
     */
    public function getLastTradeAtFormattedAttribute(): string
    {
        $value = $this->getAttributes()['last_trade_at'] ?? null;

        return $value ? Carbon::parse($value)->diffForHumans() : '-';
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

    /**
     * Get XIRR from cache or calculate if not cached
     * Returns null for index stocks or when no valid XIRR exists
     * Uses 0 as sentinel value in database to indicate "calculated but null"
     */
    public function getXirrAttribute(?float $value): ?float
    {
        if ($this->type == 'index') {
            return null;
        }

        // Return null for sentinel value 0 (no valid XIRR)
        if ($value === 0.0 || $value === 0) {
            return null;
        }

        // Return cached value if available
        if ($value !== null) {
            return $value;
        }

        // Calculate on-demand if not cached (during transition)
        return $this->calculateXirr();
    }

    /**
     * Calculate XIRR from trades (expensive operation)
     */
    public function calculateXirr(): ?float
    {
        if ($this->type == 'index') {
            return null;
        }

        $trades = $this->trades()
            ->select('type', 'price', 'quantity', 'executed_at')
            ->get();

        if ($trades->isEmpty()) {
            return null;
        }

        $trades = $trades->sortBy('executed_at');

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
                    $dividendAmount = (float) $trade->quantity * (float) $trade->price;
                    if ($dividendAmount > 0) {
                        $cashFlows[] = $dividendAmount;
                        $dates[] = $date;
                    }

                    break;

                case 'stock_split':
                case 'stock_dividend':
                    break;
            }
        }

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
