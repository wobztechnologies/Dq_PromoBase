<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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
