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
        'type',
        'price',
        'quantity',
        'split_ratio',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
            'price' => 'decimal:4',
            'split_ratio' => 'decimal:4',
        ];
    }

    /**
     * Check if this is a buy trade
     */
    public function isBuy(): bool
    {
        return $this->type === 'buy';
    }

    /**
     * Check if this is a sell trade
     */
    public function isSell(): bool
    {
        return $this->type === 'sell';
    }

    /**
     * Check if this is a dividend record
     */
    public function isDividend(): bool
    {
        return $this->type === 'dividend';
    }

    /**
     * Check if this is a stock split
     */
    public function isStockSplit(): bool
    {
        return $this->type === 'stock_split';
    }

    /**
     * Check if this is a stock dividend (送股/转增)
     */
    public function isStockDividend(): bool
    {
        return $this->type === 'stock_dividend';
    }

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
        if (! $this->isBuy() && ! $this->isSell()) {
            return null;
        }

        $currentPrice = $this->stock->current_price;
        if ($currentPrice === null || $this->price == 0) {
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
        if (! $this->isBuy() && ! $this->isSell()) {
            return null;
        }

        $currentPrice = $this->stock->current_price;
        if ($currentPrice === null) {
            return null;
        }

        return $currentPrice - $this->price;
    }
}
