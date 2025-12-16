<?php

namespace Database\Seeders;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\ProductColorVariant;
use App\Models\ProductPriceTier;
use App\Models\ProductSizeVariant;
use App\Models\ProductVariantPrice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductVariantPriceTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productId = '010a6c02-6e49-4126-b7d9-7dba47b17ab9';
        $product = Product::with(['colorVariants.sizeVariants.size', 'sizeVariants.size'])->find($productId);
        
        if (!$product) {
            $this->command->error("Produit avec l'ID {$productId} non trouvé.");
            return;
        }
        
        $this->command->info("Création de données de test pour le produit: {$product->sku} - {$product->name}");
        
        // Récupérer ou créer des distributeurs de test
        $distributor1 = Distributor::firstOrCreate(
            ['name' => 'Distributeur Test 1'],
            ['id' => \Illuminate\Support\Str::uuid()]
        );
        
        $distributor2 = Distributor::firstOrCreate(
            ['name' => 'Distributeur Test 2'],
            ['id' => \Illuminate\Support\Str::uuid()]
        );
        
        $this->command->info("Distributeurs: {$distributor1->name}, {$distributor2->name}");
        
        // Créer des prix pour le produit seul (si c'est un produit simple)
        if ($product->isSimple()) {
            $this->createProductPrice($product, null, null, $distributor1, 100, 25.50);
            $this->createProductPrice($product, null, null, $distributor2, 50, 26.00);
            
            // Créer des prix pour les variantes de taille directes
            foreach ($product->sizeVariants as $sizeVariant) {
                $this->createProductPrice($product, null, $sizeVariant, $distributor1, rand(10, 100), 25.50 + rand(0, 500) / 100);
                $this->createProductPrice($product, null, $sizeVariant, $distributor2, rand(10, 100), 26.00 + rand(0, 500) / 100);
            }
        }
        
        // Créer des prix pour les variantes de couleur
        foreach ($product->colorVariants as $colorVariant) {
            // Prix pour la couleur seule
            $this->createProductPrice($product, $colorVariant, null, $distributor1, rand(20, 150), 25.50 + rand(0, 1000) / 100);
            $this->createProductPrice($product, $colorVariant, null, $distributor2, rand(20, 150), 26.00 + rand(0, 1000) / 100);
            
            // Prix pour chaque taille de cette couleur
            foreach ($colorVariant->sizeVariants as $sizeVariant) {
                $this->createProductPrice($product, $colorVariant, $sizeVariant, $distributor1, rand(10, 100), 25.50 + rand(0, 1500) / 100);
                $this->createProductPrice($product, $colorVariant, $sizeVariant, $distributor2, rand(10, 100), 26.00 + rand(0, 1500) / 100);
            }
        }
        
        $this->command->info("Données de test créées avec succès!");
    }
    
    private function createProductPrice(
        Product $product,
        ?ProductColorVariant $colorVariant,
        ?ProductSizeVariant $sizeVariant,
        Distributor $distributor,
        int $stock,
        float $basePrice
    ): void {
        // Vérifier si le prix existe déjà
        $existingPrice = ProductVariantPrice::where('product_id', $product->id)
            ->where('product_color_variant_id', $colorVariant?->id)
            ->where('product_size_variant_id', $sizeVariant?->id)
            ->where('distributor_id', $distributor->id)
            ->first();
        
        if ($existingPrice) {
            $this->command->warn("Prix existant trouvé, mise à jour...");
            $variantPrice = $existingPrice;
            $variantPrice->stock = $stock;
            $variantPrice->is_active = true;
            $variantPrice->save();
        } else {
            $variantPrice = ProductVariantPrice::create([
                'product_id' => $product->id,
                'product_color_variant_id' => $colorVariant?->id,
                'product_size_variant_id' => $sizeVariant?->id,
                'distributor_id' => $distributor->id,
                'sku_distributor' => $this->generateDistributorSku($product, $colorVariant, $sizeVariant, $distributor),
                'stock' => $stock,
                'is_active' => true,
            ]);
        }
        
        // Créer des grilles de prix
        $this->createPriceTiers($variantPrice, $basePrice);
    }
    
    private function createPriceTiers(ProductVariantPrice $variantPrice, float $basePrice): void
    {
        // Supprimer les anciens paliers s'ils existent
        $variantPrice->priceTiers()->delete();
        
        // Créer plusieurs paliers de prix
        $tiers = [
            ['min' => 1, 'max' => 9, 'price' => $basePrice],
            ['min' => 10, 'max' => 49, 'price' => $basePrice * 0.95], // -5%
            ['min' => 50, 'max' => 99, 'price' => $basePrice * 0.90], // -10%
            ['min' => 100, 'max' => null, 'price' => $basePrice * 0.85], // -15%
        ];
        
        foreach ($tiers as $tier) {
            ProductPriceTier::create([
                'product_variant_price_id' => $variantPrice->id,
                'quantity_min' => $tier['min'],
                'quantity_max' => $tier['max'],
                'unit_price' => round($tier['price'], 2),
                'currency' => 'EUR',
            ]);
        }
    }
    
    private function generateDistributorSku(
        Product $product,
        ?ProductColorVariant $colorVariant,
        ?ProductSizeVariant $sizeVariant,
        Distributor $distributor
    ): string {
        $sku = $product->sku;
        
        if ($colorVariant) {
            $sku .= '-' . strtoupper(substr($colorVariant->sku, -3));
        }
        
        if ($sizeVariant && $sizeVariant->size) {
            $sku .= '-' . $sizeVariant->size->name;
        }
        
        return $sku . '-' . strtoupper(substr($distributor->name, 0, 3));
    }
}
