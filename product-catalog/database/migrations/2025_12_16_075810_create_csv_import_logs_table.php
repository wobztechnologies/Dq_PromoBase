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
        Schema::create('csv_import_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('csv_import_id')->constrained('csv_imports')->onDelete('cascade');
            $table->integer('row_number')->nullable(); // Numéro de ligne dans le CSV
            $table->string('sku')->nullable(); // SKU du produit/distributeur pour référence
            $table->enum('level', ['info', 'warning', 'error'])->default('info');
            $table->text('message');
            $table->json('data')->nullable(); // Données supplémentaires (ligne CSV, etc.)
            $table->timestamps();
            
            $table->index(['csv_import_id', 'level']);
            $table->index('row_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csv_import_logs');
    }
};
