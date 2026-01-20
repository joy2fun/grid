<?php

namespace App\Models;

use App\Observers\HoldingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(HoldingObserver::class)]
class Holding extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'initial_quantity',
        'initial_cost',
        'quantity',
        'average_cost',
        'total_cost',
    ];

    protected $casts = [
        'initial_quantity' => 'decimal:8',
        'initial_cost' => 'decimal:8',
        'quantity' => 'decimal:8',
        'average_cost' => 'decimal:8',
        'total_cost' => 'decimal:8',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
