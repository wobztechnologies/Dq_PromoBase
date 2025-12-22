<?php

namespace App\Services\CsvExport\Handlers;

use App\Models\Product;
use App\Models\ProductColorVariant;
use App\Models\ProductSizeVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductImage;
use App\Services\CsvExport\ExportHandlerInterface;
use Illuminate\Support\Facades\Storage;

class ProductExportHandler implements ExportHandlerInterface
{
    public function getHeaders(?string $mode = null): array
    {
        $baseHeaders = [
            'sku',
            'name',
            'category_name',
            'manufacturer_name',
            'color_name',
            'size_name',
            'primary_color_name',
        ];

        // Ajouter les champs distributeur si mode distributeur
        if ($mode === 'distributor') {
            $baseHeaders = array_merge($baseHeaders, [
                'distributor_name',
                'distributor_sku',
            ]);
        }

        // Ajouter les champs d'images (jusqu'à 8)
        for ($i = 1; $i <= 8; $i++) {
            $baseHeaders[] = "image_{$i}_url";
        }

        return $baseHeaders;
    }

    public function getData(?string $mode = null, array $filters = []): array
    {
        $query = Product::with([
            'category',
            'manufacturer',
            'primaryColor.parent',
            'colorVariants.primaryColor.parent',
            'colorVariants.sizeVariants.size',
            'colorVariants.sizeVariants.variantPrices.distributor',
            'colorVariants.sizeVariants.variantPrices',
            'colorVariants.variantPrices.distributor',
            'colorVariants.variantPrices',
            'images',
        ]);

        // Appliquer les filtres si nécessaire
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['manufacturer_id'])) {
            $query->where('manufacturer_id', $filters['manufacturer_id']);
        }

        if (isset($filters['sku'])) {
            $query->where('sku', 'like', '%' . $filters['sku'] . '%');
        }

        $products = $query->get();
        $rows = [];

        foreach ($products as $product) {
            // Si le produit a une couleur fabricant (produit simple)
            if ($product->primary_color_id) {
                $row = $this->buildProductRow($product, null, null, $mode);
                $rows[] = $row;
            } else {
                // Si le produit a des variantes de couleur
                $colorVariants = $product->colorVariants;
                
                if ($colorVariants->isEmpty()) {
                    // Produit sans variantes
                    $row = $this->buildProductRow($product, null, null, $mode);
                    $rows[] = $row;
                } else {
                    foreach ($colorVariants as $colorVariant) {
                        $sizeVariants = $colorVariant->sizeVariants;
                        
                        if ($sizeVariants->isEmpty()) {
                            // Variante de couleur sans variantes de taille
                            $row = $this->buildProductRow($product, $colorVariant, null, $mode);
                            $rows[] = $row;
                        } else {
                            // Variantes de couleur avec variantes de taille
                            foreach ($sizeVariants as $sizeVariant) {
                                $row = $this->buildProductRow($product, $colorVariant, $sizeVariant, $mode);
                                $rows[] = $row;
                            }
                        }
                    }
                }
            }
        }

        return $rows;
    }

    protected function buildProductRow(
        Product $product,
        ?ProductColorVariant $colorVariant = null,
        ?ProductSizeVariant $sizeVariant = null,
        ?string $mode = null
    ): array {
        // Déterminer la couleur fabricant et la couleur principale
        $manufacturerColor = null;
        $primaryColor = null;
        
        if ($colorVariant && $colorVariant->primaryColor) {
            // Si c'est une couleur fabricant (a un parent)
            if ($colorVariant->primaryColor->parent_id) {
                $manufacturerColor = $colorVariant->primaryColor->name;
                $primaryColor = $colorVariant->primaryColor->parent?->name ?? '';
            } else {
                // Si c'est une couleur principale directement
                $manufacturerColor = '';
                $primaryColor = $colorVariant->primaryColor->name;
            }
        } elseif ($product->primaryColor) {
            // Produit simple avec couleur
            if ($product->primaryColor->parent_id) {
                // C'est une couleur fabricant
                $manufacturerColor = $product->primaryColor->name;
                $primaryColor = $product->primaryColor->parent?->name ?? '';
            } else {
                // C'est une couleur principale
                $manufacturerColor = '';
                $primaryColor = $product->primaryColor->name;
            }
        }
        
        $row = [
            'sku' => $product->sku,
            'name' => $product->name,
            'category_name' => $product->category?->name ?? '',
            'manufacturer_name' => $product->manufacturer?->name ?? '',
            'color_name' => $manufacturerColor ?? '',
            'size_name' => $sizeVariant?->size?->name ?? '',
            'primary_color_name' => $primaryColor ?? '',
        ];

        // Ajouter les champs distributeur si mode distributeur
        if ($mode === 'distributor') {
            $variantPrice = null;
            
            if ($sizeVariant) {
                $variantPrice = $sizeVariant->variantPrices->first();
            } elseif ($colorVariant) {
                $variantPrice = $colorVariant->variantPrices->first();
            }

            $row['distributor_name'] = $variantPrice?->distributor?->name ?? '';
            $row['distributor_sku'] = $variantPrice?->sku_distributor ?? '';
        }

        // Ajouter les URLs d'images (jusqu'à 8)
        $images = $this->getProductImages($product, $colorVariant);
        for ($i = 1; $i <= 8; $i++) {
            $imageUrl = '';
            $image = $images->get($i - 1);
            if ($image && $image->s3_url) {
                try {
                    $imageUrl = Storage::disk('s3')->temporaryUrl($image->s3_url, now()->addHours(24));
                } catch (\Exception $e) {
                    $imageUrl = Storage::disk('s3')->url($image->s3_url);
                }
            }
            $row["image_{$i}_url"] = $imageUrl;
        }

        return $row;
    }

    protected function getProductImages(Product $product, ?ProductColorVariant $colorVariant = null): \Illuminate\Support\Collection
    {
        if ($colorVariant) {
            // Récupérer les images associées à la variante de couleur
            return ProductImage::where('product_id', $product->id)
                ->whereHas('colorVariants', function ($query) use ($colorVariant) {
                    $query->where('product_color_variant_id', $colorVariant->id);
                })
                ->orderBy('position')
                ->limit(8)
                ->get();
        }

        // Récupérer les images du produit
        return ProductImage::where('product_id', $product->id)
            ->orderBy('position')
            ->limit(8)
            ->get();
    }
}
