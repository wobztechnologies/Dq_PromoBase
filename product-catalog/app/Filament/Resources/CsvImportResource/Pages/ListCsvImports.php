<?php

namespace App\Filament\Resources\CsvImportResource\Pages;

use App\Filament\Resources\CsvImportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCsvImports extends ListRecords
{
    protected static string $resource = CsvImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('wizard')
                ->label('Nouvel import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->url(CsvImportResource::getUrl('wizard')),
        ];
    }
}
