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
        Schema::create('tobacco_pack_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('restocked_at');
            $table->unsignedInteger('pack_grams');
            $table->unsignedInteger('boxes_count');
            $table->decimal('purchase_price_per_box', 10, 2);
            $table->timestamps();

            $table->index(['product_id', 'restocked_at']);
        });

        if (Schema::hasColumn('products', 'tobacco_pack_options')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('tobacco_pack_options');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tobacco_pack_purchases');

        Schema::table('products', function (Blueprint $table) {
            $table->json('tobacco_pack_options')->nullable()->after('purchase_price');
        });
    }
};
