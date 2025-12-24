<?php

namespace App\Services\CsvImport\Handlers;

use App\Models\CsvImport;
use App\Models\Product;
use App\Models\ProductColorVariant;
use App\Models\ProductSizeVariant;
use App\Models\ProductVariantPrice;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Distributor;
use App\Models\PrimaryColor;
use App\Models\Size;
use App\Services\CsvImport\ImportHandlerInterface;
use App\Services\CsvImport\MatchingService;
use App\Jobs\DownloadProductImageJob;
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
                $primaryColorName = $row['primary_color_name'] ?? null;
                $variantSku = $row['variant_sku'] ?? null;
                $sizeName = $row['size_name'] ?? null;
                $sizeSku = $row['size_sku'] ?? null;
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
                $color = null;
                
                // Si les deux colonnes sont fournies : couleur principale + couleur fabricant
                if ($primaryColorName && $colorName) {
                    // Chercher la couleur principale
                    $primaryColor = PrimaryColor::where('name', $primaryColorName)
                        ->whereNull('parent_id')
                        ->whereNull('manufacturer_id')
                        ->first();
                    
                    if (!$primaryColor) {
                        $import->addLog('error', "Couleur principale '{$primaryColorName}' non trouvée", $row, $rowNumber, $sku);
                        return false;
                    }
                    
                    // Chercher la couleur fabricant qui correspond à la couleur principale et au fabricant
                    $color = PrimaryColor::where('name', $colorName)
                        ->where('parent_id', $primaryColor->id)
                        ->where('manufacturer_id', $manufacturer->id)
                        ->first();
                    
                    if (!$color) {
                        $import->addLog('error', "Couleur fabricant '{$colorName}' non trouvée pour la couleur principale '{$primaryColorName}' et le fabricant '{$manufacturerName}'", $row, $rowNumber, $sku);
                        return false;
                    }
                }
                // Si seulement la couleur principale est fournie : produit simple avec couleur principale
                elseif ($primaryColorName && !$colorName) {
                    $color = PrimaryColor::where('name', $primaryColorName)
                        ->whereNull('parent_id')
                        ->whereNull('manufacturer_id')
                        ->first();
                    
                    if (!$color) {
                        $import->addLog('error', "Couleur principale '{$primaryColorName}' non trouvée", $row, $rowNumber, $sku);
                        return false;
                    }
                    
                    // Pour un produit simple, définir la couleur directement sur le produit
                    if ($import->strategy === 'create_update') {
                        $product->primary_color_id = $color->id;
                        $product->save();
                    }
                }
                // Si seulement color_name est fourni (compatibilité avec anciens imports)
                elseif ($colorName) {
                    // Chercher la couleur (peut être principale ou fabricant)
                    $color = PrimaryColor::where('name', $colorName)->first();
                    
                    if (!$color) {
                        $import->addLog('error', "Couleur '{$colorName}' non trouvée", $row, $rowNumber, $sku);
                        return false;
                    }
                    
                    // Si c'est une couleur principale, l'associer au produit
                    if (!$color->parent_id && !$color->manufacturer_id) {
                        if ($import->strategy === 'create_update') {
                            $product->primary_color_id = $color->id;
                            $product->save();
                        }
                    }
                }
                
                // Créer la variante de couleur si nécessaire (seulement si c'est une couleur fabricant)
                if ($color && $color->parent_id && $color->manufacturer_id) {
                    $colorVariant = ProductColorVariant::where('product_id', $product->id)
                        ->where('primary_color_id', $color->id)
                        ->first();
                    
                    if (!$colorVariant && $import->strategy === 'create_update') {
                        $colorVariant = new ProductColorVariant();
                        $colorVariant->product_id = $product->id;
                        $colorVariant->primary_color_id = $color->id;
                        // Utiliser le SKU fourni ou auto-générer
                        $colorVariant->sku = $variantSku ?: ($sku . '_' . Str::slug($colorName));
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
                        // Utiliser le SKU fourni ou auto-générer
                        $sizeVariant->sku = $sizeSku ?: (($colorVariant?->sku ?? $sku) . '_' . Str::slug($sizeName));
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
                
                return true;
                
            } catch (\Exception $e) {
                $import->addLog('error', 'Erreur lors du traitement: ' . $e->getMessage(), $row, $rowNumber);
                return false;
            }
        });
    }

    /**
     * Dispatch les jobs de téléchargement d'images vers la queue
     * Les images sont téléchargées de manière asynchrone avec retry automatique
     */
    protected function processImages(CsvImport $import, Product $product, ?ProductColorVariant $colorVariant, array $row, int $rowNumber): void
    {
        $imageCount = 0;
        
        // Dispatch un job pour chaque image (jusqu'à 8)
        for ($i = 1; $i <= 8; $i++) {
            $imageKey = "image_{$i}_url";
            if (!empty($row[$imageKey])) {
                $imageUrl = trim($row[$imageKey]);
                
                // Validation basique de l'URL
                if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    $import->addLog('warning', "URL d'image invalide ignorée: {$imageUrl}", $row, $rowNumber, $product->sku);
                    continue;
                }
                
                // Dispatch le job vers la queue 'images'
                DownloadProductImageJob::dispatch(
                    $product->id,
                    $colorVariant?->id,
                    $imageUrl,
                    $i,
                    $import->id
                );
                
                $imageCount++;
            }
        }
        
        if ($imageCount > 0) {
            $import->addLog('info', "{$imageCount} image(s) en cours de téléchargement (async)", $row, $rowNumber, $product->sku);
        }
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
            if (!empty($row['primary_color_name'])) {
                $values['primary_color'][] = $row['primary_color_name'];
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
