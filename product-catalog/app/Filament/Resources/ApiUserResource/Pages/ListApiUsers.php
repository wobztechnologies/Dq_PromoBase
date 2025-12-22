<?php

namespace App\Filament\Resources\ApiUserResource\Pages;

use App\Filament\Resources\ApiUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApiUsers extends ListRecords
{
    protected static string $resource = ApiUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvel utilisateur API'),
        ];
    }
}

