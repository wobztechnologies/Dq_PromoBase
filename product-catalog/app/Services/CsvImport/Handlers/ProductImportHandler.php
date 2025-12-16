<?php

namespace App\Services\CsvImport\Handlers;

use App\Models\CsvImport;
use App\Models\Product;
use App\Models\ProductColorVariant;
use App\Models\ProductSizeVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductImage;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Distributor;
use App\Models\PrimaryColor;
use App\Models\Size;
use App\Services\CsvImport\ImportHandlerInterface;
use App\Services\CsvImport\MatchingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportHandler implements ImportHandlerInterface
{
    public function __construct(
        protected MatchingService $matchingService
    ) {}

    public function processRow(CsvImport $import, array $row, int $rowNumber): bool
    {
        return DB::transaction(function () use ($import, $row, $rowNumber) {
            try {
                $sku = $row['sku'] ?? null;
                $name = $row['name'] ?? null;
                $categoryName = $row['category_name'] ?? null;
                $manufacturerName = $row['manufacturer_name'] ?? null;
                $colorName = $row['color_name'] ?? null;
                $sizeName = $row['size_name'] ?? null;
                $skuDistributor = $row['sku_distributor'] ?? null;
                $distributorName = $row['distributor_name'] ?? null;
                
                if (!$sku || !$name || !$categoryName || !$manufacturerName) {
                    $import->addLog('error', 'Données obligatoires manquantes (sku, name, category_name, manufacturer_name)', $row, $rowNumber, $sku);
                    return false;
                }
                
                // Validation du mode
                if ($import->mode === 'distributor' && (!$skuDistributor || !$distributorName)) {
                    $import->addLog('error', 'Mode distributeur: sku_distributor et distributor_name requis', $row, $rowNumber, $sku);
                    return false;
                }
                
                // Trouver les entités liées
                $category = Category::where('name', $categoryName)->first();
                if (!$category) {
                    $import->addLog('error', "Catégorie '{$categoryName}' non trouvée", $row, $rowNumber, $sku);
                    return false;
                }
                
                $manufacturer = Manufacturer::where('name', $manufacturerName)->first();
                if (!$manufacturer) {
                    $import->addLog('error', "Fabricant '{$manufacturerName}' non trouvé", $row, $rowNumber, $sku);
                    return false;
                }
                
                // Trouver ou créer le produit principal
                $product = Product::where('sku', $sku)->first();
                
                if (!$product && $import->strategy === 'create_update') {
                    $product = new Product();
                    $product->sku = $sku;
                    $product->name = $name;
                    $product->category_id = $category->id;
                    $product->manufacturer_id = $manufacturer->id;
                    $product->save();
                } elseif (!$product) {
                    $import->addLog('error', "Produit avec SKU '{$sku}' non trouvé et création non autorisée", $row, $rowNumber, $sku);
                    return false;
                } else {
                    // Mettre à jour les champs de base si stratégie create_update
                    if ($import->strategy === 'create_update') {
                        $product->name = $name;
                        $product->category_id = $category->id;
                        $product->manufacturer_id = $manufacturer->id;
                        $product->save();
                    }
                }
                
                // Gérer les variantes de couleur
                $colorVariant = null;
                if ($colorName) {
                    $color = PrimaryColor::where('name', $colorName)->first();
                    if (!$color) {
                        $import->addLog('error', "Couleur '{$colorName}' non trouvée", $row, $rowNumber, $sku);
                        return false;
                    }
                    
                    $colorVariant = ProductColorVariant::where('product_id', $product->id)
                        ->where('primary_color_id', $color->id)
                        ->first();
                    
                    if (!$colorVariant && $import->strategy === 'create_update') {
                        $colorVariant = new ProductColorVariant();
                        $colorVariant->product_id = $product->id;
                        $colorVariant->primary_color_id = $color->id;
                        $colorVariant->sku = $sku . '_' . Str::slug($colorName);
                        $colorVariant->save();
                    }
                }
                
                // Gérer les variantes de taille
                $sizeVariant = null;
                if ($sizeName) {
                    $size = Size::where('name', $sizeName)->first();
                    if (!$size) {
                        $import->addLog('error', "Taille '{$sizeName}' non trouvée", $row, $rowNumber, $sku);
                        return false;
                    }
                    
                    $sizeVariant = ProductSizeVariant::where('product_id', $product->id)
                        ->where('product_color_variant_id', $colorVariant?->id)
                        ->where('size_id', $size->id)
                        ->first();
                    
                    if (!$sizeVariant && $import->strategy === 'create_update') {
                        $sizeVariant = new ProductSizeVariant();
                        $sizeVariant->product_id = $product->id;
                        $sizeVariant->product_color_variant_id = $colorVariant?->id;
                        $sizeVariant->size_id = $size->id;
                        $sizeVariant->sku = ($colorVariant?->sku ?? $sku) . '_' . Str::slug($sizeName);
                        $sizeVariant->save();
                    }
                }
                
                // Gérer les prix et stock (mode distributeur uniquement)
                if ($import->mode === 'distributor' && $skuDistributor && $distributorName) {
                    $distributor = Distributor::where('name', $distributorName)->first();
                    if (!$distributor) {
                        $import->addLog('error', "Distributeur '{$distributorName}' non trouvé", $row, $rowNumber, $sku);
                        return false;
                    }
                    
                    $variantPrice = ProductVariantPrice::where('product_id', $product->id)
                        ->where('product_color_variant_id', $colorVariant?->id)
                        ->where('product_size_variant_id', $sizeVariant?->id)
                        ->where('distributor_id', $distributor->id)
                        ->where('sku_distributor', $skuDistributor)
                        ->first();
                    
                    if (!$variantPrice && $import->strategy === 'create_update') {
                        $variantPrice = new ProductVariantPrice();
                        $variantPrice->product_id = $product->id;
                        $variantPrice->product_color_variant_id = $colorVariant?->id;
                        $variantPrice->product_size_variant_id = $sizeVariant?->id;
                        $variantPrice->distributor_id = $distributor->id;
                        $variantPrice->sku_distributor = $skuDistributor;
                        $variantPrice->is_active = true;
                        $variantPrice->last_updated_at = now();
                        $variantPrice->save();
                    }
                }
                
                // Gérer les images (jusqu'à 8)
                $this->processImages($import, $product, $colorVariant, $row, $rowNumber);
                
                $import->incrementSuccessful();
                return true;
                
            } catch (\Exception $e) {
                $import->addLog('error', 'Erreur lors du traitement: ' . $e->getMessage(), $row, $rowNumber);
                return false;
            }
        });
    }

    protected function processImages(CsvImport $import, Product $product, ?ProductColorVariant $colorVariant, array $row, int $rowNumber): void
    {
        $imageUrls = [];
        
        // Collecter toutes les URLs d'images (jusqu'à 8)
        for ($i = 1; $i <= 8; $i++) {
            $imageKey = "image_{$i}_url";
            if (!empty($row[$imageKey])) {
                $imageUrls[] = [
                    'url' => $row[$imageKey],
                    'position' => $i,
                ];
            }
        }
        
        if (empty($imageUrls)) {
            return;
        }
        
        // Télécharger et créer les images
        foreach ($imageUrls as $imageData) {
            try {
                $imagePath = $this->downloadImage($imageData['url'], $product->sku);
                if (!$imagePath) {
                    $import->addLog('warning', "Impossible de télécharger l'image: {$imageData['url']}", $row, $rowNumber);
                    continue;
                }
                
                // Créer l'entrée ProductImage
                $productImage = new ProductImage();
                $productImage->product_id = $product->id;
                $productImage->s3_url = $imagePath;
                $productImage->position = $this->getPositionName($imageData['position']);
                $productImage->is_default = $imageData['position'] === 1;
                $productImage->status = 'active';
                $productImage->save();
                
                // Associer à la variante de couleur si elle existe
                if ($colorVariant) {
                    // La relation est gérée via la table pivot product_image_color_variant
                    $productImage->colorVariants()->syncWithoutDetaching([$colorVariant->id]);
                }
                
            } catch (\Exception $e) {
                $import->addLog('warning', "Erreur lors du traitement de l'image: " . $e->getMessage(), $row, $rowNumber);
            }
        }
    }

    protected function downloadImage(string $url, string $productSku): ?string
    {
        try {
            $contents = file_get_contents($url);
            if ($contents === false) {
                return null;
            }
            
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = Str::slug($productSku) . '_' . time() . '_' . Str::random(8) . '.' . $extension;
            $path = "products/images/{$filename}";
            
            Storage::disk('s3')->put($path, $contents);
            
            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getPositionName(int $position): string
    {
        $positions = [
            1 => 'front',
            2 => 'back',
            3 => 'left',
            4 => 'right',
            5 => 'top',
            6 => 'bottom',
            7 => 'detail',
            8 => 'detail',
        ];
        
        return $positions[$position] ?? 'detail';
    }

    public function getMatchingValues(array $rows): array
    {
        $values = [];
        
        foreach ($rows as $row) {
            if (!empty($row['category_name'])) {
                $values['category'][] = $row['category_name'];
            }
            if (!empty($row['manufacturer_name'])) {
                $values['manufacturer'][] = $row['manufacturer_name'];
            }
            if (!empty($row['distributor_name'])) {
                $values['distributor'][] = $row['distributor_name'];
            }
            if (!empty($row['color_name'])) {
                $values['primary_color'][] = $row['color_name'];
            }
            if (!empty($row['size_name'])) {
                $values['size'][] = $row['size_name'];
            }
        }
        
        // Dédupliquer
        foreach ($values as $key => $val) {
            $values[$key] = array_unique($val);
        }
        
        return $values;
    }
}
