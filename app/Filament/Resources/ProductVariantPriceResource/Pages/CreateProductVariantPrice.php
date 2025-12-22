<?php

namespace App\Filament\Resources\ProductVariantPriceResource\Pages;

use App\Filament\Resources\ProductVariantPriceResource;
use App\Models\ProductSizeVariant;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateProductVariantPrice extends CreateRecord
{
    protected static string $resource = ProductVariantPriceResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Vérifier la contrainte : si la couleur a des tailles, on ne peut pas créer de prix sans taille
        if ($data['product_color_variant_id'] && !$data['product_size_variant_id']) {
            $hasSizes = ProductSizeVariant::where('product_color_variant_id', $data['product_color_variant_id'])
                ->exists();
            
            if ($hasSizes) {
                throw ValidationException::withMessages([
                    'product_size_variant_id' => 'Cette variante de couleur possède des variantes de taille. Vous devez sélectionner une taille.',
                ]);
            }
        }
        
        return $data;
    }
}
