<?php

namespace App\Models;

use App\Observers\TradeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(TradeObserver::class)]
class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'grid_id',
        'stock_id',
        'side',
        'price',
        'quantity',
        'executed_at',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];

    public function grid()
    {
        return $this->belongsTo(Grid::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * Calculate the percentage change between this trade's price and the current stock price
     *
     * @return float|null The percentage change, or null if current price is not available
     */
    public function getPriceChangePercentageAttribute(): ?float
    {
        $currentPrice = $this->stock->current_price;
        if ($currentPrice === null || $this->price === 0) {
            return null;
        }

        return (($currentPrice - $this->price) / $this->price) * 100;
    }

    /**
     * Calculate the absolute price change between this trade's price and the current stock price
     *
     * @return float|null The absolute price change, or null if current price is not available
     */
    public function getPriceChangeAttribute(): ?float
    {
        $currentPrice = $this->stock->current_price;
        if ($currentPrice === null) {
            return null;
        }

        return $currentPrice - $this->price;
    }
}
