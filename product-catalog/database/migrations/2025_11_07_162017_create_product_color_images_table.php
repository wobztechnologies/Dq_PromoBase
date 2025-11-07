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
        Schema::create('product_color_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variant_id')->constrained('product_color_variants')->onDelete('cascade');
            $table->string('s3_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_color_images');
    }
};
