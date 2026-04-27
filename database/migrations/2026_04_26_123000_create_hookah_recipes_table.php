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
        Schema::create('hookah_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hookah_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tobacco_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('grams_per_serving', 8, 2);
            $table->timestamps();

            $table->unique(['hookah_product_id', 'tobacco_product_id'], 'hookah_recipe_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hookah_recipes');
    }
};
