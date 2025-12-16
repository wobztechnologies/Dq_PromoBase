<?php

namespace App\Filament\Resources\ProductSizeVariantResource\Pages;

use App\Filament\Resources\ProductSizeVariantResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProductSizeVariant extends ViewRecord
{
    protected static string $resource = ProductSizeVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
