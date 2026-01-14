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
        Schema::create('day_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks')->onDelete('cascade');
            $table->date('date');
            $table->decimal('open_price', 10, 2);
            $table->decimal('high_price', 10, 2);
            $table->decimal('low_price', 10, 2);
            $table->decimal('close_price', 10, 2);
            $table->bigInteger('volume');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_prices');
    }
};
