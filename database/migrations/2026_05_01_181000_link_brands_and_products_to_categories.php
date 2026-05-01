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
        Schema::table('brands', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('name');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('brand_id');
        });

        $categoryIdsBySlug = DB::table('categories')
            ->whereIn('slug', ['tobacco', 'drink', 'hookah'])
            ->pluck('id', 'slug');

        $tobaccoId = $categoryIdsBySlug['tobacco'] ?? null;
        $drinkId = $categoryIdsBySlug['drink'] ?? null;
        $hookahId = $categoryIdsBySlug['hookah'] ?? null;

        if ($tobaccoId !== null) {
            DB::table('brands')
                ->whereNull('category_id')
                ->where('category', 'tobacco')
                ->update(['category_id' => $tobaccoId]);

            DB::table('products')
                ->whereNull('category_id')
                ->where('category', 'tobacco')
                ->update(['category_id' => $tobaccoId]);

            DB::table('brands')
                ->whereNull('category_id')
                ->update(['category_id' => $tobaccoId]);

            DB::table('products')
                ->whereNull('category_id')
                ->update(['category_id' => $tobaccoId]);
        }

        if ($drinkId !== null) {
            DB::table('brands')
                ->where('category', 'drink')
                ->update(['category_id' => $drinkId]);

            DB::table('products')
                ->where('category', 'drink')
                ->update(['category_id' => $drinkId]);
        }

        if ($hookahId !== null) {
            DB::table('brands')
                ->where('category', 'hookah')
                ->update(['category_id' => $hookahId]);

            DB::table('products')
                ->where('category', 'hookah')
                ->update(['category_id' => $hookahId]);
        }

        Schema::table('brands', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE brands MODIFY category_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE products MODIFY category_id BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE brands ALTER COLUMN category_id SET NOT NULL');
            DB::statement('ALTER TABLE products ALTER COLUMN category_id SET NOT NULL');
        }

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category', 20)->default('tobacco')->after('brand_id');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('category', 20)->default('tobacco')->after('name');
        });

        $categoryIdsBySlug = DB::table('categories')
            ->whereIn('slug', ['tobacco', 'drink', 'hookah'])
            ->pluck('id', 'slug');

        $tobaccoId = $categoryIdsBySlug['tobacco'] ?? null;
        $drinkId = $categoryIdsBySlug['drink'] ?? null;
        $hookahId = $categoryIdsBySlug['hookah'] ?? null;

        if ($drinkId !== null) {
            DB::table('brands')->where('category_id', $drinkId)->update(['category' => 'drink']);
            DB::table('products')->where('category_id', $drinkId)->update(['category' => 'drink']);
        }

        if ($hookahId !== null) {
            DB::table('brands')->where('category_id', $hookahId)->update(['category' => 'hookah']);
            DB::table('products')->where('category_id', $hookahId)->update(['category' => 'hookah']);
        }

        if ($tobaccoId !== null) {
            DB::table('brands')->where('category_id', $tobaccoId)->update(['category' => 'tobacco']);
            DB::table('products')->where('category_id', $tobaccoId)->update(['category' => 'tobacco']);
        }

        Schema::table('brands', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};

