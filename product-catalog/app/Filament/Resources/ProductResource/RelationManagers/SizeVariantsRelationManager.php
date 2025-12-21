<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Size;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SizeVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'sizeVariants';

    protected static ?string $title = 'Variantes de taille';

    protected static ?string $modelLabel = 'Variante de taille';

    protected static ?string $pluralModelLabel = 'Variantes de taille';
    
    /**
     * Toujours visible - les variantes de taille peuvent être ajoutées aux produits simples
     * Pour les produits variants, les variantes de taille sont gérées via les variantes de couleur
     */
    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        // Toujours visible pour permettre la gestion des variantes de taille
        return true;
    }

    public function form(Form $form): Form
    {
        return $form
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
                    ->unique(\App\Models\ProductSizeVariant::class, 'sku', ignoreRecord: true)
                    ->placeholder('Ex: PROD-000001-S')
                    ->helperText('SKU unique pour cette variante de taille'),
            ]);
    }

    public function table(Table $table): Table
    {
        $product = $this->getOwnerRecord();
        
        return $table
            ->modifyQueryUsing(function ($query) use ($product) {
                // Remplacer complètement la requête pour inclure toutes les variantes de taille
                // 1. Celles directement liées au produit (produits simples)
                // 2. Celles liées aux variantes de couleur du produit (produits variants)
                return \App\Models\ProductSizeVariant::query()
                    ->where(function ($q) use ($product) {
                        // Variantes directement sur le produit
                        $q->where('product_id', $product->id)
                          ->whereNull('product_color_variant_id');
                    })
                    ->orWhereHas('colorVariant', function ($q) use ($product) {
                        // Variantes liées aux variantes de couleur du produit
                        $q->where('product_id', $product->id);
                    })
                    ->with(['size', 'colorVariant.primaryColor.manufacturer'])
                    ->orderByRaw('CASE WHEN product_color_variant_id IS NULL THEN 0 ELSE 1 END')
                    ->orderBy('product_color_variant_id')
                    ->orderBy('size_id');
            })
            ->recordTitleAttribute('sku')
            ->groups([
                Tables\Grouping\Group::make('product_color_variant_id')
                    ->label('Variante de couleur')
                    ->getTitleFromRecordUsing(function ($record) {
                        if (!$record || !$record->colorVariant) {
                            return '📦 Produit simple (sans variante de couleur)';
                        }
                        $color = $record->colorVariant->primaryColor;
                        $manufacturer = $color?->manufacturer?->name ?? '';
                        $colorName = $manufacturer ? "{$color->name} ({$manufacturer})" : ($color->name ?? 'Sans couleur');
                        return $record->colorVariant->sku . ' - ' . $colorName;
                    })
                    ->getDescriptionFromRecordUsing(function ($record) {
                        if (!$record || !$record->colorVariant) {
                            return null;
                        }
                        $count = $record->colorVariant->sizeVariants()->count();
                        return $count . ' variante(s) de taille';
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('product_color_variant_id')
            ->columns([
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
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une variante')
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
                        $product = $livewire->getOwnerRecord();
                        
                        // Vérifier que le produit est simple (pas de variantes de couleur)
                        if ($product->colorVariants()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Information')
                                ->body('Ce produit a des variantes de couleur. Les variantes de taille doivent être ajoutées aux variantes de couleur individuelles dans l\'onglet "Variantes de couleurs".')
                                ->info()
                                ->send();
                            throw new \Exception('Ce produit a des variantes de couleur. Ajoutez les variantes de taille aux variantes de couleur individuelles.');
                        }
                        
                        // Définir automatiquement le product_id depuis le produit parent
                        $data['product_id'] = $product->id;
                        $data['product_color_variant_id'] = null; // Pas de variante de couleur
                        
                        // Générer le SKU automatiquement si size_id est défini
                        if (isset($data['size_id']) && !isset($data['sku'])) {
                            $size = \App\Models\Size::find($data['size_id']);
                            if ($size) {
                                $sku = $product->sku . '-' . $size->name;
                                
                                // Vérifier l'unicité du SKU
                                $skuExists = \App\Models\ProductSizeVariant::where('sku', $sku)->exists();
                                if ($skuExists) {
                                    // Si le SKU existe déjà, ajouter un numéro
                                    $counter = 1;
                                    $baseSku = $sku;
                                    do {
                                        $sku = $baseSku . '-' . $counter;
                                        $counter++;
                                    } while (\App\Models\ProductSizeVariant::where('sku', $sku)->exists());
                                }
                                
                                $data['sku'] = $sku;
                            }
                        }
                        
                        return $data;
                    })
                    ->visible(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->colorVariants()->count() === 0),
                Tables\Actions\Action::make('create_in_resource')
                    ->label('Créer dans le Resource dédié')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->url(fn (RelationManager $livewire) => \App\Filament\Resources\ProductSizeVariantResource::getUrl('create', [
                        'product_id' => $livewire->getOwnerRecord()->id,
                    ]))
                    ->openUrlInNewTab(false),
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
