<?php

namespace Database\Seeders;

use App\Models\Stock;
use App\Models\DayPrice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some sample stocks
        $stocks = Stock::factory(10)->create();

        // Create sample day prices for each stock
        foreach ($stocks as $stock) {
            DayPrice::factory(30)->create(['stock_id' => $stock->id]);
        }
    }
}
