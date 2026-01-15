<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DayPrice extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'stock_id',
        'date',
        'open_price',
        'high_price',
        'low_price',
        'close_price',
        'volume',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
