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
        $lastTrade = $this->trades()->latest()->first();

        if (! $lastTrade) {
            return false; // No trades ever, not considered inactive
        }

        return $lastTrade->created_at->diffInDays() > $threshold;
    }

    public static function inactiveStocks()
    {
        $threshold = AppSetting::get('inactive_stocks_threshold', 30);
        $cutoffDate = Carbon::now()->subDays($threshold);

        // Only include stocks with trades older than threshold and not index type
        return static::where('type', '!=', 'index')
            ->whereHas('trades', function ($subquery) use ($cutoffDate) {
                $subquery->where('executed_at', '<', $cutoffDate);
            });
    }
}
