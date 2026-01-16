<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $stockIds = \App\Models\Trade::distinct()->pluck('stock_id');

        foreach ($stockIds as $stockId) {
            $trades = \App\Models\Trade::where('stock_id', $stockId)->get();

            $quantity = 0;
            $totalCost = 0;

            foreach ($trades as $trade) {
                if ($trade->side === 'buy') {
                    $quantity += $trade->quantity;
                    $totalCost += $trade->quantity * $trade->price;
                } elseif ($trade->side === 'sell') {
                    $quantity -= $trade->quantity;
                    $totalCost -= $trade->quantity * $trade->price;
                }
            }

            $averageCost = $quantity > 0 ? $totalCost / $quantity : 0;

            \App\Models\Holding::updateOrCreate(
                ['stock_id' => $stockId],
                [
                    'quantity' => $quantity,
                    'total_cost' => $totalCost,
                    'average_cost' => $averageCost,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            //
        });
    }
};
