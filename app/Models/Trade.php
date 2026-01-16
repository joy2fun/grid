<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\TradeObserver;

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
