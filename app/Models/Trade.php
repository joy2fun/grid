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
}
