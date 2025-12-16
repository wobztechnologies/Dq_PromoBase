<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Cette table stocke les grilles de prix (quantité → prix unitaire) pour chaque variation complète.
     * Permet de définir des prix dégressifs selon la quantité commandée.
     */
    public function up(): void
    {
        Schema::create('product_price_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Référence à la variation complète avec distributeur
            $table->foreignUuid('product_variant_price_id')
                ->constrained('product_variant_prices')
                ->onDelete('cascade');
            
            // Quantité minimum pour ce palier de prix
            $table->integer('quantity_min')->default(1);
            
            // Quantité maximum pour ce palier (null = pas de limite)
            $table->integer('quantity_max')->nullable();
            
            // Prix unitaire pour ce palier de quantité
            $table->decimal('unit_price', 10, 2);
            
            // Devise (par défaut EUR, mais peut être étendu)
            $table->string('currency', 3)->default('EUR');
            
            $table->timestamps();
            
            // Index pour optimiser les recherches de prix par quantité
            $table->index(['product_variant_price_id', 'quantity_min']);
            $table->index(['product_variant_price_id', 'quantity_max']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_price_tiers');
    }
};
