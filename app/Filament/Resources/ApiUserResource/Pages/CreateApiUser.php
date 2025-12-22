<?php

namespace App\Filament\Resources\ApiUserResource\Pages;

use App\Filament\Resources\ApiUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApiUser extends CreateRecord
{
    protected static string $resource = ApiUserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

