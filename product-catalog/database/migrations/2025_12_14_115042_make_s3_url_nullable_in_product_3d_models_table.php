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
        Schema::table('product_3d_models', function (Blueprint $table) {
            $table->string('s3_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_3d_models', function (Blueprint $table) {
            // Note: En cas de rollback, on ne peut pas remettre NOT NULL si des valeurs NULL existent
            // Il faudrait d'abord mettre à jour toutes les valeurs NULL
            $table->string('s3_url')->nullable(false)->change();
        });
    }
};
