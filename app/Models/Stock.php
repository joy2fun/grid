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
    ];

    public function dayPrices()
    {
        return $this->hasMany(DayPrice::class);
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
            return true; // No trades ever, considered inactive
        }

        return $lastTrade->created_at->diffInDays() > $threshold;
    }

    public static function inactiveStocks()
    {
        $threshold = AppSetting::get('inactive_stocks_threshold', 30);
        $cutoffDate = Carbon::now()->subDays($threshold);

        // Stocks with no trades at all OR stocks with last trade before cutoff date
        return static::where(function ($query) use ($cutoffDate) {
            $query->whereDoesntHave('trades')
                ->orWhereHas('trades', function ($subquery) use ($cutoffDate) {
                    $subquery->where('created_at', '<', $cutoffDate);
                });
        });
    }
}
