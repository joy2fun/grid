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
            $table->decimal('xirr', 10, 6)->nullable()->after('last_trade_price');
        });

        // Backfill existing data
        $this->backfillXirr();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('xirr');
        });
    }

    /**
     * Backfill XIRR for existing stocks
     */
    protected function backfillXirr(): void
    {
        \App\Models\Stock::query()
            ->where('type', '!=', 'index')
            ->whereHas('trades')
            ->chunk(100, function ($stocks) {
                foreach ($stocks as $stock) {
                    $xirr = $stock->calculateXirr();
                    $stock->update(['xirr' => $xirr ?? 0]);
                }
            });
    }
};
