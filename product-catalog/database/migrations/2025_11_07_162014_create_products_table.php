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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('main_image_s3_url')->nullable();
            $table->string('model_3d_s3_url')->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignUuid('manufacturer_id')->nullable()->constrained('manufacturers')->onDelete('set null');
            $table->timestamps();

            $table->index('sku');
            $table->index('category_id');
            $table->index('manufacturer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
