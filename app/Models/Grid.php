<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grid extends Model
{
    protected $fillable = [
        'stock_id',
        'name',
        'initial_amount',
        'grid_interval',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }
}
