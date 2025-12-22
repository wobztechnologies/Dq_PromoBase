<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductVariantPriceResource\Pages;
use App\Filament\Resources\ProductVariantPriceResource\RelationManagers;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\ProductColorVariant;
use App\Models\ProductSizeVariant;
use App\Models\ProductVariantPrice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductVariantPriceResource extends Resource
{
    protected static ?string $model = ProductVariantPrice::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Prix & Stock';

    protected static ?string $modelLabel = 'Prix de variation';

    protected static ?string $pluralModelLabel = 'Prix de variations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Validation avant sauvegarde
                Forms\Components\Hidden::make('validation_check')
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $state, $get) {
                        // Cette logique sera exécutée lors de la validation
                    }),
                Forms\Components\Section::make('Variation de produit')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Produit')
                            ->relationship('product', 'name')
                            ->searchable(['sku', 'name'])
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('product_color_variant_id', null))
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('product_size_variant_id', null)),
                        Forms\Components\Select::make('product_color_variant_id')
                            ->label('Variante de couleur')
                            ->relationship(
                                'colorVariant',
                                'sku',
                                fn (Builder $query, Forms\Get $get) => $query->where('product_id', $get('product_id'))
                                    ->with(['primaryColor.manufacturer'])
                            )
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                $record->loadMissing('primaryColor.manufacturer');
                                $color = $record->primaryColor;
                                $manufacturer = $color?->manufacturer?->name ?? '';
                                $label = $manufacturer ? "{$color->name} ({$manufacturer})" : ($color->name ?? 'Inconnu');
                                return $record->sku . ' - ' . $label;
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->reactive()
                            ->visible(fn (Forms\Get $get) => $get('product_id') !== null)
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('product_size_variant_id', null))
                            ->helperText('Optionnel : sélectionnez une variante de couleur si le produit en a'),
                        Forms\Components\Select::make('product_size_variant_id')
                            ->label('Variante de taille')
                            ->options(function (Forms\Get $get) {
                                $productId = $get('product_id');
                                $colorVariantId = $get('product_color_variant_id');
                                
                                if (!$productId) {
                                    return [];
                                }
                                
                                $query = ProductSizeVariant::query()
                                    ->where('product_id', $productId)
                                    ->orWhereHas('colorVariant', function ($q) use ($productId) {
                                        $q->where('product_id', $productId);
                                    })
                                    ->with(['size', 'colorVariant']);
                                
                                if ($colorVariantId) {
                                    $query->where('product_color_variant_id', $colorVariantId);
                                } else {
                                    $query->whereNull('product_color_variant_id');
                                }
                                
                                return $query->get()->mapWithKeys(function ($sizeVariant) {
                                    $label = $sizeVariant->size->name;
                                    if ($sizeVariant->colorVariant) {
                                        $label .= ' (' . $sizeVariant->colorVariant->sku . ')';
                                    }
                                    return [$sizeVariant->id => $sizeVariant->sku . ' - ' . $label];
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->visible(fn (Forms\Get $get) => $get('product_id') !== null)
                            ->helperText('Optionnel : sélectionnez une variante de taille si disponible'),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Distributeur')
                    ->schema([
                        Forms\Components\Select::make('distributor_id')
                            ->label('Distributeur')
                            ->relationship('distributor', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('sku_distributor')
                            ->label('SKU Distributeur')
                            ->maxLength(255)
                            ->helperText('SKU utilisé par le distributeur pour cette variation'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Stock & Statut')
                    ->schema([
                        Forms\Components\TextInput::make('stock')
                            ->label('Stock')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->minValue(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true)
                            ->helperText('Désactiver pour masquer cette variation du distributeur'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU Produit')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('colorVariant.sku')
                    ->label('Variante couleur')
                    ->default('-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sizeVariant.sku')
                    ->label('Variante taille')
                    ->default('-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('distributor.name')
                    ->label('Distributeur')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku_distributor')
                    ->label('SKU Distributeur')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($record) => $record->stock > 0 ? 'success' : 'danger'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_tiers_count')
                    ->label('Paliers de prix')
                    ->counts('priceTiers')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_updated_at')
                    ->label('Dernière MAJ')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('distributor_id')
                    ->label('Distributeur')
                    ->relationship('distributor', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Produit')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Inactifs uniquement'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PriceTiersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductVariantPrices::route('/'),
            'create' => Pages\CreateProductVariantPrice::route('/create'),
            'edit' => Pages\EditProductVariantPrice::route('/{record}/edit'),
        ];
    }
}
