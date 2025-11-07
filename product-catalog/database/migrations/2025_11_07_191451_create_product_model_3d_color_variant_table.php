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
        Schema::create('product_model_3d_color_variant', function (Blueprint $table) {
            $table->foreignUuid('product_model_3d_id')->constrained('product_3d_models')->onDelete('cascade');
            $table->foreignUuid('product_color_variant_id')->constrained('product_color_variants')->onDelete('cascade');
            $table->primary(['product_model_3d_id', 'product_color_variant_id'], 'product_model_3d_color_variant_pk');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_model_3d_color_variant');
    }
};
