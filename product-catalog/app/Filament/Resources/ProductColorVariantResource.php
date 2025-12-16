<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductColorVariantResource\Pages;
use App\Filament\Resources\ProductColorVariantResource\RelationManagers;
use App\Models\ProductColorVariant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductColorVariantResource extends Resource
{
    protected static ?string $model = ProductColorVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    
    protected static bool $shouldRegisterNavigation = false; // Masquer de la navigation principale (accessible via ProductResource)

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Produit')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('primary_color_id')
                    ->label('Couleur')
                    ->relationship('primaryColor', 'name', 
                        fn ($query) => $query->with('parent')->orderBy('parent_id')->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable(['name', 'parent.name'])
                    ->preload()
                    ->required()
                    ->helperText('Sélectionnez la couleur (sous-couleur) de cette variante'),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU de la variante')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->placeholder('Ex: PROD-000001-ROU')
                    ->helperText('SKU unique pour cette variante de couleur'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['primaryColor.parent', 'product']))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('primaryColor.full_name')
                    ->label('Couleur')
                    ->getStateUsing(fn ($record) => $record->primaryColor->full_name ?? '-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sizeVariants_count')
                    ->label('Variantes de taille')
                    ->counts('sizeVariants')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            RelationManagers\SizeVariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductColorVariants::route('/'),
            'create' => Pages\CreateProductColorVariant::route('/create'),
            'view' => Pages\ViewProductColorVariant::route('/{record}'),
            'edit' => Pages\EditProductColorVariant::route('/{record}/edit'),
        ];
    }
}
