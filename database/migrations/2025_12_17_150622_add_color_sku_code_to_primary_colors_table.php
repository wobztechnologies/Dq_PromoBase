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
        Schema::table('primary_colors', function (Blueprint $table) {
            $table->string('color_sku_code')->nullable()->after('hex_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('primary_colors', function (Blueprint $table) {
            $table->dropColumn('color_sku_code');
        });
    }
};
