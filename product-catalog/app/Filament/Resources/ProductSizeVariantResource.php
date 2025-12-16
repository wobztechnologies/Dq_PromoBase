<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductSizeVariantResource\Pages;
use App\Filament\Resources\ProductSizeVariantResource\RelationManagers;
use App\Models\ProductSizeVariant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductSizeVariantResource extends Resource
{
    protected static ?string $model = ProductSizeVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    
    protected static bool $shouldRegisterNavigation = false; // Masquer de la navigation principale (accessible via ProductResource)

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Association')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Produit (pour produit simple)')
                            ->relationship('product', 'name', 
                                fn ($query) => $query->whereDoesntHave('colorVariants')
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Sélectionnez un produit simple (sans variantes de couleur)')
                            ->visible(function ($record, callable $get) {
                                // Lors de la création ou si pas de variante de couleur associée
                                if (!$record) {
                                    return true;
                                }
                                return !$record->product_color_variant_id;
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('product_color_variant_id', null);
                                }
                            }),
                        Forms\Components\Select::make('product_color_variant_id')
                            ->label('Variante de couleur (pour produit variant)')
                            ->relationship('colorVariant', 'sku', 
                                fn ($query) => $query->with(['product', 'primaryColor.parent'])
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->product->name . ' - ' . $record->sku . ' (' . ($record->primaryColor->full_name ?? '-') . ')')
                            ->searchable()
                            ->preload()
                            ->helperText('Sélectionnez une variante de couleur (pour produit variant)')
                            ->visible(function ($record, callable $get) {
                                // Lors de la création ou si pas de produit direct associé
                                if (!$record) {
                                    return true;
                                }
                                return !$record->product_id;
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('product_id', null);
                                }
                            }),
                        Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content(function (callable $get) {
                                if (!$get('product_id') && !$get('product_color_variant_id')) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                                ⚠️ Vous devez sélectionner soit un produit simple, soit une variante de couleur.
                                            </p>
                                        </div>'
                                    );
                                }
                                return null;
                            })
                            ->reactive(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Informations de la variante')
                    ->schema([
                        Forms\Components\Select::make('size_id')
                            ->label('Taille')
                            ->relationship('size', 'name', 
                                fn ($query) => $query->orderBy('order')->orderBy('name')
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Sélectionnez une taille depuis la liste des tailles disponibles'),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU de la variante')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Ex: PROD-000001-S ou PROD-000001-BLE-S')
                            ->helperText('SKU unique pour cette variante de taille'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['product', 'colorVariant.primaryColor.parent', 'size']))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produit')
                    ->getStateUsing(fn ($record) => $record->product ? $record->product->name : ($record->colorVariant ? $record->colorVariant->product->name : '-'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('colorVariant.sku')
                    ->label('Variante de couleur')
                    ->getStateUsing(fn ($record) => $record->colorVariant ? $record->colorVariant->sku . ' (' . ($record->colorVariant->primaryColor->full_name ?? '-') . ')' : '-')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('size.name')
                    ->label('Taille')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
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
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Produit')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('type')
                    ->label('Type')
                    ->form([
                        Forms\Components\Radio::make('type')
                            ->options([
                                'simple' => 'Produit simple',
                                'variant' => 'Produit variant',
                            ])
                            ->reactive(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['type'] === 'simple') {
                            return $query->whereNotNull('product_id')->whereNull('product_color_variant_id');
                        }
                        if ($data['type'] === 'variant') {
                            return $query->whereNotNull('product_color_variant_id')->whereNull('product_id');
                        }
                        return $query;
                    }),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductSizeVariants::route('/'),
            'create' => Pages\CreateProductSizeVariant::route('/create'),
            'view' => Pages\ViewProductSizeVariant::route('/{record}'),
            'edit' => Pages\EditProductSizeVariant::route('/{record}/edit'),
        ];
    }
}
