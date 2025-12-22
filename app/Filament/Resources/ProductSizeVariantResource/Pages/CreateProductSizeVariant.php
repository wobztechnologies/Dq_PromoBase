<?php

namespace App\Filament\Resources\ProductSizeVariantResource\Pages;

use App\Filament\Resources\ProductSizeVariantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductSizeVariant extends CreateRecord
{
    protected static string $resource = ProductSizeVariantResource::class;
    
    public function mount(): void
    {
        parent::mount();
        
        // Si un product_id est passé en paramètre, le pré-remplir
        $productId = request()->get('product_id');
        if ($productId) {
            $this->form->fill([
                'product_id' => $productId,
                'product_color_variant_id' => null,
            ]);
        }
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
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
    
    protected function getRedirectUrl(): string
    {
        // Rediriger vers le produit si un product_id est défini
        if ($this->record && $this->record->product_id) {
            // Trouver l'index du RelationManager SizeVariantsRelationManager
            $relations = \App\Filament\Resources\ProductResource::getRelations();
            $relationIndex = array_search(
                \App\Filament\Resources\ProductResource\RelationManagers\SizeVariantsRelationManager::class,
                $relations
            );
            
            return \App\Filament\Resources\ProductResource::getUrl('edit', [
                'record' => $this->record->product_id,
                'activeRelationManager' => $relationIndex !== false ? $relationIndex : null,
            ]);
        }
        
        // Sinon, rediriger vers la liste des variantes de taille
        return $this->getResource()::getUrl('index');
    }
}
