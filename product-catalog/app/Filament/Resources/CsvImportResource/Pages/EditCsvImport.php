<?php

namespace App\Filament\Resources\CsvImportResource\Pages;

use App\Filament\Resources\CsvImportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCsvImport extends EditRecord
{
    protected static string $resource = CsvImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
