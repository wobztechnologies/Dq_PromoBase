<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\Manufacturer;
use App\Models\PrimaryColor;
use App\Models\Product;
use App\Models\ProductColorVariant;
use App\Models\ProductDistributor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Créer 5 fabricants
        $manufacturers = collect();
        for ($i = 0; $i < 5; $i++) {
            $manufacturers->push(Manufacturer::factory()->create());
        }

        // Créer 3 distributeurs
        $distributors = collect();
        for ($i = 0; $i < 3; $i++) {
            $distributors->push(Distributor::factory()->create());
        }

        // Créer des couleurs primaires
        $colors = [
            ['name' => 'Rouge', 'hex_code' => '#FF0000'],
            ['name' => 'Bleu', 'hex_code' => '#0000FF'],
            ['name' => 'Vert', 'hex_code' => '#00FF00'],
            ['name' => 'Jaune', 'hex_code' => '#FFFF00'],
            ['name' => 'Noir', 'hex_code' => '#000000'],
            ['name' => 'Blanc', 'hex_code' => '#FFFFFF'],
            ['name' => 'Gris', 'hex_code' => '#808080'],
        ];

        $primaryColors = [];
        foreach ($colors as $color) {
            $primaryColors[] = PrimaryColor::create([
                'id' => Str::uuid(),
                'name' => $color['name'],
                'hex_code' => $color['hex_code'],
            ]);
        }

        // Créer 10 catégories hiérarchiques avec ltree
        $categories = [];
        
        // Niveau 1 (racine)
        for ($i = 1; $i <= 3; $i++) {
            $category = Category::create([
                'id' => Str::uuid(),
                'name' => "Catégorie Racine $i",
                'path' => (string) $i,
            ]);
            $categories[] = $category;
        }

        // Niveau 2
        $level2Categories = [];
        foreach ($categories as $parent) {
            for ($i = 1; $i <= 2; $i++) {
                $category = Category::create([
                    'id' => Str::uuid(),
                    'name' => "Sous-catégorie {$parent->name} - $i",
                    'path' => $parent->path . '.' . $i,
                ]);
                $level2Categories[] = $category;
            }
        }

        // Niveau 3
        foreach ($level2Categories as $parent) {
            $category = Category::create([
                'id' => Str::uuid(),
                'name' => "Sous-sous-catégorie {$parent->name}",
                'path' => $parent->path . '.1',
            ]);
            $categories[] = $category;
        }

        $allCategories = array_merge($categories, $level2Categories);

        // Créer 100 produits
        for ($i = 1; $i <= 100; $i++) {
            $product = Product::create([
                'id' => Str::uuid(),
                'sku' => 'PROD-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'name' => "Produit Test $i",
                'model_3d_s3_url' => $i % 10 === 0 ? "https://example.com/models/product-$i.glb" : null,
                'category_id' => $allCategories[array_rand($allCategories)]->id,
                'manufacturer_id' => $manufacturers->random()->id,
            ]);

            // Créer 1-3 variantes de couleur par produit
            $numVariants = rand(1, 3);
            $selectedColors = collect($primaryColors)->random($numVariants);
            
            foreach ($selectedColors as $color) {
                ProductColorVariant::create([
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'primary_color_id' => $color->id,
                    'sku' => $product->sku . '-' . strtoupper(substr($color->name, 0, 3)),
                ]);
            }

            // Associer 1-2 distributeurs par produit
            $numDistributors = rand(1, 2);
            $selectedDistributors = $distributors->random($numDistributors);
            
            foreach ($selectedDistributors as $distributor) {
                ProductDistributor::create([
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'distributor_id' => $distributor->id,
                    'sku_distributor' => 'DIST-' . $distributor->id . '-' . $product->sku,
                ]);
            }
        }
    }
}
