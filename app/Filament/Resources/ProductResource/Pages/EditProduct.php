<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Charger la relation primaryColor pour hydrater le champ virtuel primary_color_parent_id
        $this->record->load('primaryColor');
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Debug: voir les données avant sauvegarde
        \Illuminate\Support\Facades\Log::info('EditProduct mutateFormDataBeforeSave', [
            'data' => $data,
            'record_id' => $this->record->id,
            'current_primary_color_id' => $this->record->primary_color_id,
            'has_color_variants' => $this->record->colorVariants()->count(),
        ]);
        
        // Supprimer le champ temporaire s'il existe
        unset($data['primary_color_parent_id']);
        
        return $data;
    }
    
    protected function afterSave(): void
    {
        // Debug: vérifier après la sauvegarde
        $this->record->refresh();
        \Illuminate\Support\Facades\Log::info('EditProduct afterSave', [
            'record_id' => $this->record->id,
            'primary_color_id_after' => $this->record->primary_color_id,
        ]);
    }
}
