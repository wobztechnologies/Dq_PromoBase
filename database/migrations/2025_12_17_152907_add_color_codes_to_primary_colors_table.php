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
            $table->string('rgb')->nullable()->after('color_sku_code');
            $table->string('pantone_c')->nullable()->after('rgb');
            $table->string('pantone_tcx')->nullable()->after('pantone_c');
            $table->string('pms')->nullable()->after('pantone_tcx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('primary_colors', function (Blueprint $table) {
            $table->dropColumn(['rgb', 'pantone_c', 'pantone_tcx', 'pms']);
        });
    }
};
