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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 40)->unique();
            $table->string('behavior_type', 20)->default('generic');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();
        $seed = [
            ['name' => 'Tobacco', 'slug' => 'tobacco', 'behavior_type' => 'tobacco', 'position' => 10],
            ['name' => 'Drinks', 'slug' => 'drink', 'behavior_type' => 'drink', 'position' => 20],
            ['name' => 'Hookah', 'slug' => 'hookah', 'behavior_type' => 'hookah', 'position' => 30],
        ];

        foreach ($seed as $row) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'behavior_type' => $row['behavior_type'],
                    'position' => $row['position'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

