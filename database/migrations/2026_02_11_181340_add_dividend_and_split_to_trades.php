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
        // Check if type column already exists
        $hasType = Schema::hasColumn('trades', 'type');
        $hasSplitRatio = Schema::hasColumn('trades', 'split_ratio');
        $hasSide = Schema::hasColumn('trades', 'side');

        // Step 1: Add new columns if they don't exist
        if (! $hasType || ! $hasSplitRatio) {
            Schema::table('trades', function (Blueprint $table) use ($hasType, $hasSplitRatio) {
                if (! $hasType) {
                    // Add type column first (nullable)
                    $table->string('type')->nullable()->after('stock_id');
                }
                if (! $hasSplitRatio) {
                    // For stock_split/stock_dividend: ratio
                    $table->decimal('split_ratio', 10, 4)->nullable()->after('price')
                        ->comment('Split ratio: 10 means 10送1, 0.1 means 10:1 split');
                }
            });
        }

        // Step 2: Migrate existing data - copy side to type if side exists
        if ($hasSide) {
            DB::table('trades')->update(['type' => DB::raw('side')]);
        }

        // Step 3: Make type NOT NULL and drop side if exists
        if ($hasSide) {
            Schema::table('trades', function (Blueprint $table) {
                $table->string('type')->nullable(false)->change();
                $table->dropColumn('side');
            });
        }

        // Ensure type is not null for any records
        DB::table('trades')->whereNull('type')->update(['type' => 'buy']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            // Add side column back
            $table->string('side')->nullable()->after('stock_id');
        });

        // Copy type back to side
        DB::table('trades')->update(['side' => DB::raw('type')]);

        Schema::table('trades', function (Blueprint $table) {
            $table->string('side')->nullable(false)->change();
            $table->dropColumn(['type', 'split_ratio']);
        });
    }
};
