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
        // S'assurer que primary_color_id est bien dans les données à sauvegarder
        // Le champ primary_color_parent_id est dehydrated(false) donc il n'est pas inclus
        \Illuminate\Support\Facades\Log::info('EditProduct mutateFormDataBeforeSave', [
            'data' => $data,
            'record_id' => $this->record->id,
        ]);
        
        return $data;
    }
}
