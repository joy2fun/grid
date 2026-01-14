<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, remove duplicate records keeping only the latest one
        DB::statement('
            DELETE FROM day_prices
            WHERE id NOT IN (
                SELECT MAX(id)
                FROM day_prices
                GROUP BY stock_id, date
            )
        ');

        // Add unique constraint on stock_id and date
        Schema::table('day_prices', function (Blueprint $table) {
            $table->unique(['stock_id', 'date'], 'unique_stock_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('day_prices', function (Blueprint $table) {
            $table->dropUnique('unique_stock_date');
        });
    }
};
