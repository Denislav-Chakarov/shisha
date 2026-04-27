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
        Schema::table('store_tables', function (Blueprint $table) {
            $table->string('status', 20)->default('available')->after('is_active');
        });

        DB::table('store_tables')
            ->where('is_active', 0)
            ->update(['status' => 'inactive']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_tables', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
