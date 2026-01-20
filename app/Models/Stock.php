<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'peak_value',
    ];

    public function dayPrices()
    {
        return $this->hasMany(DayPrice::class);
    }

    public function holding()
    {
        return $this->hasOne(Holding::class);
    }
}
