<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_size_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Soit lié à un produit (sans variante de couleur)
            $table->foreignUuid('product_id')
                ->nullable()
                ->constrained('products')
                ->onDelete('cascade');
            // Soit lié à une variante de couleur
            $table->foreignUuid('product_color_variant_id')
                ->nullable()
                ->constrained('product_color_variants')
                ->onDelete('cascade');
            $table->string('size'); // Ex: 'S', 'M', 'L', 'XL', '42', '43', etc.
            $table->string('sku')->unique();
            $table->timestamps();
            
            // Index pour les recherches
            $table->index('product_id');
            $table->index('product_color_variant_id');
            $table->index('sku');
        });
        
        // Ajouter la contrainte CHECK avec une requête SQL brute
        // Au moins un des deux (product_id ou product_color_variant_id) doit être défini
        // Note: Cette contrainte n'est appliquée que sur PostgreSQL
        // Sur SQLite (tests), la validation se fera au niveau applicatif
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_size_variants ADD CONSTRAINT check_product_or_color_variant CHECK ((product_id IS NOT NULL) OR (product_color_variant_id IS NOT NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer la contrainte CHECK si elle existe
        try {
            DB::statement('ALTER TABLE product_size_variants DROP CONSTRAINT IF EXISTS check_product_or_color_variant');
        } catch (\Exception $e) {
            // Ignorer si la contrainte n'existe pas
        }
        
        Schema::dropIfExists('product_size_variants');
    }
};
