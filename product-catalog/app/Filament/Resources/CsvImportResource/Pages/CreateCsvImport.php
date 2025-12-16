<?php

namespace App\Filament\Resources\CsvImportResource\Pages;

use App\Filament\Resources\CsvImportResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateCsvImport extends CreateRecord
{
    protected static string $resource = CsvImportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['status'] = 'pending_validation';
        
        // Gérer le fichier uploadé
        if (isset($data['file_path']) && is_array($data['file_path'])) {
            $filePath = $data['file_path'][0] ?? null;
            if ($filePath) {
                // Le fichier est déjà dans storage/app/csv-imports
                $data['file_path'] = storage_path('app/csv-imports/' . $filePath);
            }
        }
        
        return $data;
    }
}
