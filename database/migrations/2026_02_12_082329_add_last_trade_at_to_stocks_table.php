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
            $table->timestamp('last_trade_at')->nullable()->after('rise_percentage');
        });

        // Backfill existing data
        $this->backfillLastTradeAt();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('last_trade_at');
        });
    }

    /**
     * Backfill last_trade_at for existing stocks
     */
    protected function backfillLastTradeAt(): void
    {
        \App\Models\Stock::query()
            ->whereHas('trades', fn ($q) => $q->whereIn('type', ['buy', 'sell']))
            ->chunk(100, function ($stocks) {
                foreach ($stocks as $stock) {
                    $lastTradeAt = $stock->trades()
                        ->whereIn('type', ['buy', 'sell'])
                        ->max('executed_at');

                    if ($lastTradeAt) {
                        $stock->update(['last_trade_at' => $lastTradeAt]);
                    }
                }
            });
    }
};
