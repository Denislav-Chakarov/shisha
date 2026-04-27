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
            $table->unsignedSmallInteger('reserved_people')->nullable()->after('reserved_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_tables', function (Blueprint $table) {
            $table->dropColumn('reserved_people');
        });
    }
};
