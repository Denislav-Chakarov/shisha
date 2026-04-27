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
            $table->string('category', 20)->default('tobacco')->after('name');
        });

        DB::table('brands')->update(['category' => 'tobacco']);

        $now = now();
        foreach (['Coca-Cola', 'Pepsi', 'Sprite', 'Fanta', 'Schweppes', 'Mineral Water', 'Lime'] as $brandName) {
            DB::table('brands')->updateOrInsert(
                ['name' => $brandName],
                ['category' => 'drink', 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
