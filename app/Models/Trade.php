<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
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
