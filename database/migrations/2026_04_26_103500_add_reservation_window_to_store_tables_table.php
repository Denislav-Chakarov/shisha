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
        Schema::table('store_tables', function (Blueprint $table) {
            $table->timestamp('reserved_from')->nullable()->after('status');
            $table->timestamp('reserved_to')->nullable()->after('reserved_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_tables', function (Blueprint $table) {
            $table->dropColumn(['reserved_from', 'reserved_to']);
        });
    }
};
