<?php

namespace App\Filament\Resources\ProductColorVariantResource\Pages;

use App\Filament\Resources\ProductColorVariantResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductColorVariants extends ListRecords
{
    protected static string $resource = ProductColorVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
