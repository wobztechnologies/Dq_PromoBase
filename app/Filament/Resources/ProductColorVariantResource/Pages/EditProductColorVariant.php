<?php

namespace App\Filament\Resources\ProductColorVariantResource\Pages;

use App\Filament\Resources\ProductColorVariantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductColorVariant extends EditRecord
{
    protected static string $resource = ProductColorVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
