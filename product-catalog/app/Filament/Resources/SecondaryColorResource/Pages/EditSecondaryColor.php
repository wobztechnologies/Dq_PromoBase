<?php

namespace App\Filament\Resources\SecondaryColorResource\Pages;

use App\Filament\Resources\SecondaryColorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSecondaryColor extends EditRecord
{
    protected static string $resource = SecondaryColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

