<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $brands = ['Aoen', 'Karm', 'Mechanica', 'Maklaud'];

        foreach ($brands as $brandName) {
            $exists = DB::table('brands')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($brandName)])
                ->exists();

            if (! $exists) {
                DB::table('brands')->insert([
                    'name' => $brandName,
                    'category' => 'hookah',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('brands')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($brandName)])
                    ->update([
                        'category' => 'hookah',
                        'updated_at' => $now,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('brands')
            ->whereIn('name', ['Aoen', 'Karm', 'Mechanica', 'Maklaud'])
            ->where('category', 'hookah')
            ->delete();
    }
};
