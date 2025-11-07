<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DistributorResource\Pages;
use App\Models\Distributor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DistributorResource extends Resource
{
    protected static ?string $model = Distributor::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Distributeurs';

    protected static ?string $modelLabel = 'Distributeur';

    protected static ?string $pluralModelLabel = 'Distributeurs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                Forms\Components\FileUpload::make('logo_s3_url')
                    ->label('Logo')
                    ->disk('s3')
                    ->directory('distributors/logos')
                    ->visibility('public')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        null,
                        '16:9',
                        '4:3',
                        '1:1',
                    ])
                    ->maxSize(2048)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->helperText('Téléchargez le logo du distributeur (max 2MB, formats: JPG, PNG, WebP)')
                    ->deletable(true)
                    ->downloadable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_s3_url')
                    ->label('Logo')
                    ->disk('s3')
                    ->circular()
                    ->size(50)
                    ->defaultImageUrl('data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50"><rect width="50" height="50" fill="#e5e7eb"/><text x="25" y="25" font-family="Arial" font-size="20" fill="#9ca3af" text-anchor="middle" dominant-baseline="middle">?</text></svg>'))
                    ->placeholder('Aucun logo'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_distributors_count')
                    ->label('Produits')
                    ->counts('productDistributors')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDistributors::route('/'),
            'create' => Pages\CreateDistributor::route('/create'),
            'edit' => Pages\EditDistributor::route('/{record}/edit'),
        ];
    }
}
