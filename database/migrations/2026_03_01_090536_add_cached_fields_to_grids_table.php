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
        Schema::table('grids', function (Blueprint $table) {
            $table->timestamp('last_trade_at')->nullable()->after('grid_interval');
            $table->decimal('last_trade_price', 15, 4)->nullable()->after('last_trade_at');
            $table->decimal('xirr', 10, 6)->nullable()->after('last_trade_price');
        });

        // Backfill existing data
        $this->backfillGridCache();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grids', function (Blueprint $table) {
            $table->dropColumn(['last_trade_at', 'last_trade_price', 'xirr']);
        });
    }

    /**
     * Backfill cache fields for existing grids
     */
    protected function backfillGridCache(): void
    {
        \App\Models\Grid::query()
            ->whereHas('trades', fn ($q) => $q->whereIn('type', ['buy', 'sell']))
            ->chunk(100, function ($grids) {
                foreach ($grids as $grid) {
                    $lastTrade = $grid->trades()
                        ->whereIn('type', ['buy', 'sell'])
                        ->latest('executed_at')
                        ->first();

                    $metrics = $grid->getMetrics();

                    $grid->update([
                        'last_trade_at' => $lastTrade?->executed_at,
                        'last_trade_price' => $lastTrade?->price,
                        'xirr' => $metrics['xirr'] ?? 0,
                    ]);
                }
            });
    }
};
