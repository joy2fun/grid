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
        Schema::table('stocks', function (Blueprint $table) {
            $table->decimal('last_trade_price', 12, 4)->nullable()->after('last_trade_at');
        });

        // Backfill existing data
        $this->backfillLastTradePrice();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('last_trade_price');
        });
    }

    /**
     * Backfill last_trade_price for existing stocks
     */
    protected function backfillLastTradePrice(): void
    {
        \App\Models\Stock::query()
            ->whereHas('trades', fn ($q) => $q->whereIn('type', ['buy', 'sell']))
            ->chunk(100, function ($stocks) {
                foreach ($stocks as $stock) {
                    $lastTrade = $stock->trades()
                        ->whereIn('type', ['buy', 'sell'])
                        ->latest('executed_at')
                        ->first();

                    if ($lastTrade) {
                        $stock->update(['last_trade_price' => $lastTrade->price]);
                    }
                }
            });
    }
};
