<?php

namespace App\Filament\Resources\ProductVariantPriceResource\Pages;

use App\Filament\Resources\ProductVariantPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductVariantPrices extends ListRecords
{
    protected static string $resource = ProductVariantPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
