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
        Schema::create('product_image_color_variant', function (Blueprint $table) {
            $table->foreignUuid('product_image_id')->constrained('product_images')->onDelete('cascade');
            $table->foreignUuid('product_color_variant_id')->constrained('product_color_variants')->onDelete('cascade');
            $table->primary(['product_image_id', 'product_color_variant_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_image_color_variant');
    }
};
