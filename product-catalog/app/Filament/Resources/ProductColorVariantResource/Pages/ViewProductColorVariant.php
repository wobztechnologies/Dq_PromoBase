<?php

namespace App\Filament\Resources\ProductColorVariantResource\Pages;

use App\Filament\Resources\ProductColorVariantResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProductColorVariant extends ViewRecord
{
    protected static string $resource = ProductColorVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
