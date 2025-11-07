<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DistributorsRelationManager extends RelationManager
{
    protected static string $relationship = 'distributors';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('distributor_id')
                    ->label('Distributor')
                    ->relationship('distributor', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('sku_distributor')
                    ->label('Distributor SKU')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku_distributor')
            ->columns([
                Tables\Columns\TextColumn::make('distributor.name')
                    ->label('Distributor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sku_distributor')
                    ->label('SKU')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
