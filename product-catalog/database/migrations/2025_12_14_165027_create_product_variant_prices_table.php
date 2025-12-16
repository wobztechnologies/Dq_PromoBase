<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Cette table identifie chaque variation complète d'un produit avec un distributeur.
     * Une variation complète peut être :
     * - Produit seul (product_id seulement)
     * - Produit + couleur (product_id + product_color_variant_id)
     * - Produit + couleur + taille (product_id + product_color_variant_id + product_size_variant_id)
     * - Produit + taille (product_id + product_size_variant_id)
     */
    public function up(): void
    {
        Schema::create('product_variant_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Référence au produit de base (toujours présent)
            $table->foreignUuid('product_id')
                ->constrained('products')
                ->onDelete('cascade');
            
            // Référence à la variante de couleur (nullable - pour produits simples ou avec taille seule)
            $table->foreignUuid('product_color_variant_id')
                ->nullable()
                ->constrained('product_color_variants')
                ->onDelete('cascade');
            
            // Référence à la variante de taille (nullable - pour produits sans taille)
            $table->foreignUuid('product_size_variant_id')
                ->nullable()
                ->constrained('product_size_variants')
                ->onDelete('cascade');
            
            // Référence au distributeur
            $table->foreignUuid('distributor_id')
                ->constrained('distributors')
                ->onDelete('cascade');
            
            // SKU du distributeur pour cette variation
            $table->string('sku_distributor')->nullable();
            
            // Stock disponible chez ce distributeur
            $table->integer('stock')->default(0);
            
            // Statut (actif/inactif)
            $table->boolean('is_active')->default(true);
            
            // Date de dernière mise à jour (pour tracking des imports)
            $table->timestamp('last_updated_at')->nullable();
            
            $table->timestamps();
            
            // Index composites pour optimiser les recherches fréquentes
            $table->index(['product_id', 'distributor_id']);
            $table->index(['product_color_variant_id', 'distributor_id']);
            $table->index(['product_size_variant_id', 'distributor_id']);
            $table->index(['distributor_id', 'is_active']);
            $table->index('sku_distributor');
            $table->index('last_updated_at');
            
            // Contrainte unique : une seule entrée par combinaison variation complète + distributeur
            $table->unique([
                'product_id',
                'product_color_variant_id',
                'product_size_variant_id',
                'distributor_id'
            ], 'unique_variant_distributor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_prices');
    }
};
