<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations de base')
                    ->schema([
                        Forms\Components\TextInput::make('sku')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('category_id')
                            ->label('Catégorie')
                            ->relationship('category', 'name', 
                                fn ($query) => $query->orderBy('path')
                            )
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                // Calculer le niveau de profondeur basé sur le path ltree
                                $depth = substr_count($record->path, '.');
                                
                                if ($depth === 0) {
                                    // Niveau racine
                                    return '📁 ' . $record->name;
                                }
                                
                                // Construire le préfixe avec des caractères d'arbre
                                $prefix = '';
                                $pathParts = explode('.', $record->path);
                                
                                // Pour chaque niveau, ajouter l'indentation appropriée
                                for ($i = 0; $i < $depth; $i++) {
                                    if ($i === $depth - 1) {
                                        // Dernier niveau : branche finale
                                        $prefix .= '└─ ';
                                    } else {
                                        // Niveaux intermédiaires : branche continue
                                        $prefix .= '│  ';
                                    }
                                }
                                
                                return $prefix . $record->name;
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Sélectionnez une catégorie dans l\'arbre hiérarchique'),
                        Forms\Components\Select::make('manufacturer_id')
                            ->label('Manufacturer')
                            ->relationship('manufacturer', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set) {
                                // Réinitialiser les couleurs quand le fabricant change
                                $set('primary_color_parent_id', null);
                                $set('primary_color_id', null);
                            }),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Type de produit')
                    ->schema([
                        Forms\Components\Radio::make('product_type')
                            ->label('Type de produit')
                            ->options([
                                'simple' => 'Produit simple (avec couleur fabricant)',
                                'variant' => 'Produit variant (avec variantes de couleur)',
                            ])
                            ->default('simple')
                            ->live()
                            ->required()
                            ->helperText('Choisissez le type de produit : simple (une seule couleur fabricant) ou variant (plusieurs variantes de couleur)')
                            ->visible(fn ($record) => !$record) // Visible uniquement lors de la création
                            ->dehydrated(false), // Ne pas sauvegarder ce champ dans la base de données
                Forms\Components\Select::make('primary_color_parent_id')
                    ->label('Couleur principale')
                    ->options(function () {
                        return \App\Models\PrimaryColor::whereNull('parent_id')
                            ->whereNull('manufacturer_id')
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->primaryColor && $record->primaryColor->parent_id) {
                            $component->state($record->primaryColor->parent_id);
                        }
                    })
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        // Réinitialiser la couleur fabricant quand la couleur principale change
                        $set('primary_color_id', null);
                    })
                    ->visible(function ($record, callable $get) {
                        // Lors de la création : visible si le toggle est sur "simple"
                        if (!$record) {
                            return $get('product_type') === 'simple';
                        }
                        // Lors de l'édition : visible si le produit n'a pas de variantes de couleur
                        return $record->colorVariants()->count() === 0;
                    })
                    ->required(fn ($record, callable $get) => 
                        (!$record && $get('product_type') === 'simple') || 
                        ($record && $record->colorVariants()->count() === 0)
                    )
                    ->helperText('Sélectionnez d\'abord une couleur principale'),
                
                Forms\Components\Select::make('primary_color_id')
                    ->label('Couleur fabricant')
                    ->options(function ($get, $record) {
                        $manufacturerId = $get('manufacturer_id');
                        $parentId = $get('primary_color_parent_id');
                        
                        // Si on édite et qu'il n'y a pas de valeurs dans le state, utiliser les valeurs du record
                        if ($record) {
                            if (!$manufacturerId) {
                                $manufacturerId = $record->manufacturer_id;
                            }
                            if (!$parentId && $record->primaryColor) {
                                $parentId = $record->primaryColor->parent_id;
                            }
                        }
                        
                        if (!$parentId) {
                            return [];
                        }
                        
                        $query = \App\Models\PrimaryColor::where('parent_id', $parentId)
                            ->whereNotNull('manufacturer_id');
                        
                        if ($manufacturerId) {
                            $query->where('manufacturer_id', $manufacturerId);
                        }
                        
                        return $query->orderBy('name')
                            ->with('manufacturer')
                            ->get()
                            ->mapWithKeys(function ($color) {
                                $manufacturer = $color->manufacturer?->name ?? '';
                                $label = $manufacturer ? "{$color->name} ({$manufacturer})" : $color->name;
                                return [$color->id => $label];
                            });
                    })
                    ->searchable()
                    ->live()
                    ->afterStateHydrated(function ($component, $record) {
                        // S'assurer que la valeur est correctement hydratée lors de l'édition
                        if ($record && $record->primary_color_id) {
                            $component->state($record->primary_color_id);
                        }
                    })
                    ->dehydratedWhenHidden() // Préserver la valeur même si le champ est caché
                    ->visible(function ($get, $record) {
                        $parentId = $get('primary_color_parent_id');
                        
                        // En mode édition : vérifier si le produit a des variantes de couleur
                        if ($record) {
                            // Ne pas afficher si le produit a des variantes de couleur
                            if ($record->colorVariants()->count() > 0) {
                                return false;
                            }
                            // Afficher si on a un parent sélectionné OU si le produit a déjà une couleur
                            return !empty($parentId) || ($record->primaryColor && $record->primaryColor->parent_id);
                        }
                        
                        // En mode création : afficher si type simple et parent sélectionné
                        return $get('product_type') === 'simple' && !empty($parentId);
                    })
                    ->disabled(fn ($record) => $record && $record->colorVariants()->count() > 0)
                    ->helperText('Sélectionnez ensuite la couleur fabricant correspondant à la couleur principale et au fabricant du produit'),
                        Forms\Components\Placeholder::make('variant_info')
                            ->label('')
                            ->content(function (callable $get) {
                                if ($get('product_type') === 'variant') {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                            <p class="text-sm text-blue-800 dark:text-blue-200 font-medium mb-2">
                                                📋 Produit variant sélectionné
                                            </p>
                                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                                Après la création du produit, vous pourrez ajouter des variantes de couleur dans l\'onglet "Variantes de couleurs". 
                                                Chaque variante de couleur pourra ensuite avoir ses propres variantes de taille.
                                            </p>
                                        </div>'
                                    );
                                }
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                                        <p class="text-sm text-green-800 dark:text-green-200 font-medium mb-2">
                                            ✅ Produit simple sélectionné
                                        </p>
                                        <p class="text-sm text-green-700 dark:text-green-300">
                                            Définissez une couleur fabricant ci-dessus. Vous pourrez ensuite ajouter des variantes de taille dans l\'onglet "Variantes de taille".
                                        </p>
                                    </div>'
                                );
                            })
                            ->visible(fn ($record) => !$record), // Visible uniquement lors de la création
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('defaultImage.thumbnail_s3_url')
                    ->label('Image')
                    ->disk('s3')
                    ->size(50)
                    ->square()
                    ->getStateUsing(function ($record) {
                        if ($record->defaultImage && $record->defaultImage->thumbnail_s3_url) {
                            return $record->defaultImage->thumbnail_signed_url;
                        }
                        return null;
                    })
                    ->defaultImageUrl('data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50"><rect width="50" height="50" fill="#e5e7eb"/><text x="25" y="25" font-family="Arial" font-size="20" fill="#9ca3af" text-anchor="middle" dominant-baseline="middle">?</text></svg>'))
                    ->placeholder('Aucune image'),
                Tables\Columns\TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Manufacturer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_images')
                    ->label('Images')
                    ->getStateUsing(function ($record) {
                        // Compter uniquement les images supplémentaires (via la relation images)
                        return $record->images_count ?? $record->images()->count();
                    })
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_3d_models')
                    ->label('3D')
                    ->getStateUsing(function ($record) {
                        // Compter les modèles 3D du produit
                        return $record->models3d_count ?? $record->models3d()->count();
                    })
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->sortable()
                    ->alignCenter(),
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
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('manufacturer_id')
                    ->label('Manufacturer')
                    ->relationship('manufacturer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('has_color')
                    ->label('Has Color Variant')
                    ->query(fn (Builder $query): Builder => $query->has('colorVariants')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ExportAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\ImportAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ColorVariantsRelationManager::class,
            RelationManagers\SizeVariantsRelationManager::class, // Visible uniquement pour les produits simples
            RelationManagers\DistributorsRelationManager::class,
            RelationManagers\StockAndPricesRelationManager::class,
            RelationManagers\ImagesRelationManager::class,
            RelationManagers\Models3DRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
