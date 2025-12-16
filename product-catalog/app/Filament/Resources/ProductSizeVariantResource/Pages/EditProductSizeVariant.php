<?php

namespace App\Filament\Resources\ProductSizeVariantResource\Pages;

use App\Filament\Resources\ProductSizeVariantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductSizeVariant extends EditRecord
{
    protected static string $resource = ProductSizeVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validation : au moins un des deux doit être défini
        if (empty($data['product_id']) && empty($data['product_color_variant_id'])) {
            throw new \Exception('Vous devez sélectionner soit un produit simple, soit une variante de couleur.');
        }
        
        // S'assurer qu'un seul des deux est défini
        if (!empty($data['product_id']) && !empty($data['product_color_variant_id'])) {
            throw new \Exception('Vous ne pouvez pas sélectionner à la fois un produit et une variante de couleur.');
        }
        
        return $data;
    }
}
