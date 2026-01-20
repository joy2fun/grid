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
        Schema::table('holdings', function (Blueprint $table) {
            $table->decimal('initial_quantity', 20, 8)->default(0)->after('stock_id');
            $table->decimal('initial_cost', 20, 8)->default(0)->after('initial_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn(['initial_quantity', 'initial_cost']);
        });
    }
};
