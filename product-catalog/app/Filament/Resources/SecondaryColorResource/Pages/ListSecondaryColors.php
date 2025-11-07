<?php

namespace App\Filament\Resources\SecondaryColorResource\Pages;

use App\Filament\Resources\SecondaryColorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSecondaryColors extends ListRecords
{
    protected static string $resource = SecondaryColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return static::getResource()::getEloquentQuery()
            ->orderBy('parent_id')
            ->orderBy('name');
    }
}

