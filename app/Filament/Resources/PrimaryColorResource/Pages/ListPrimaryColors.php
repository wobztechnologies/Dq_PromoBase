<?php

namespace App\Filament\Resources\PrimaryColorResource\Pages;

use App\Filament\Resources\PrimaryColorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrimaryColors extends ListRecords
{
    protected static string $resource = PrimaryColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
