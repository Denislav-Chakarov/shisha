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
        Schema::create('tobacco_pack_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('pack_grams');
            $table->unsignedInteger('boxes_on_hand')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'pack_grams']);
        });

        if (Schema::hasTable('tobacco_pack_purchases')) {
            $aggregates = DB::table('tobacco_pack_purchases')
                ->select('product_id', 'pack_grams', DB::raw('SUM(boxes_count) as boxes'))
                ->groupBy('product_id', 'pack_grams')
                ->get();

            foreach ($aggregates as $row) {
                DB::table('tobacco_pack_inventory')->insert([
                    'product_id' => (int) $row->product_id,
                    'pack_grams' => (int) $row->pack_grams,
                    'boxes_on_hand' => (int) $row->boxes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $productIds = DB::table('tobacco_pack_inventory')->distinct()->pluck('product_id');
            foreach ($productIds as $pid) {
                $grams = (int) DB::table('tobacco_pack_inventory')
                    ->where('product_id', $pid)
                    ->get()
                    ->sum(fn ($r) => (int) $r->pack_grams * (int) $r->boxes_on_hand);
                DB::table('products')
                    ->where('id', $pid)
                    ->where('category', 'tobacco')
                    ->update(['stock_quantity' => $grams, 'updated_at' => now()]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tobacco_pack_inventory');
    }
};
