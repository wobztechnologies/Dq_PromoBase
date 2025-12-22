<?php

namespace App\Filament\Resources\PrimaryColorResource\Pages;

use App\Filament\Resources\PrimaryColorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPrimaryColor extends EditRecord
{
    protected static string $resource = PrimaryColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Charger les traductions existantes dans le formulaire
        $record = $this->record;
        $translations = $record->translations ?? [];
        
        // Si aucune traduction n'existe, utiliser le nom original pour le français
        if (empty($translations) && isset($data['name'])) {
            $translations['fr'] = $data['name'];
        }
        
        // Ajouter les traductions au tableau de données pour le formulaire
        foreach ($translations as $locale => $name) {
            $data['translations'][$locale] = $name;
        }
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Récupérer les traductions depuis le formulaire
        $translations = [];
        $locales = ['fr', 'en', 'es', 'de', 'it'];
        
        foreach ($locales as $locale) {
            if (isset($data['translations'][$locale]) && !empty($data['translations'][$locale])) {
                $translations[$locale] = $data['translations'][$locale];
            }
        }
        
        // Définir le nom par défaut (français) si disponible
        if (isset($translations['fr'])) {
            $data['name'] = $translations['fr'];
        }
        
        // Sauvegarder les traductions
        $data['translations'] = !empty($translations) ? $translations : null;
        
        return $data;
    }
}
