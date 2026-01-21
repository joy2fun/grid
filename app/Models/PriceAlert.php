<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'threshold_type',
        'threshold_value',
        'last_notified_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_notified_at' => 'datetime',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * Check if alert is due for notification (not notified today).
     */
    public function isDueForNotification(): bool
    {
        if (! $this->last_notified_at) {
            return true;
        }

        return ! $this->last_notified_at->isToday();
    }

    /**
     * Check if current price has crossed the threshold.
     */
    public function shouldTrigger(float $currentPrice): bool
    {
        if ($this->threshold_type === 'rise') {
            return $currentPrice >= $this->threshold_value;
        }

        return $currentPrice <= $this->threshold_value;
    }

    /**
     * Scope to get only active alerts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
