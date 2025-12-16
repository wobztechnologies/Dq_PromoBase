<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Si le produit est de type "variant", s'assurer que primary_color_id est null
        if (isset($data['product_type']) && $data['product_type'] === 'variant') {
            $data['primary_color_id'] = null;
        }
        
        // Supprimer le champ product_type qui n'est pas dans la base de données
        unset($data['product_type']);
        
        return $data;
    }
}
