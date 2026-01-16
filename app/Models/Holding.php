<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Holding extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'quantity',
        'average_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'average_cost' => 'decimal:8',
        'total_cost' => 'decimal:8',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
