<?php

namespace App\Filament\Resources\ProductColorVariantResource\RelationManagers;

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
                    ->placeholder('Ex: PROD-000001-BLE-S')
                    ->helperText('SKU unique pour cette variante de taille'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('size'))
            ->recordTitleAttribute('sku')
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
                Tables\Actions\Action::make('create_multiple')
                    ->label('Ajouter des tailles')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\CheckboxList::make('size_ids')
                            ->label('Sélectionnez les tailles à créer')
                            ->options(function () {
                                return \App\Models\Size::orderBy('order')->orderBy('name')->pluck('name', 'id');
                            })
                            ->required()
                            ->columns(3)
                            ->helperText('Cochez les tailles que vous souhaitez créer pour cette variante de couleur. Le SKU sera généré automatiquement.'),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        $colorVariant = $livewire->getOwnerRecord();
                        $createdCount = 0;
                        
                        foreach ($data['size_ids'] as $sizeId) {
                            $size = \App\Models\Size::find($sizeId);
                            if (!$size) {
                                continue;
                            }
                            
                            // Vérifier si cette combinaison existe déjà
                            $existing = \App\Models\ProductSizeVariant::where('product_color_variant_id', $colorVariant->id)
                                ->where('size_id', $sizeId)
                                ->first();
                            
                            if ($existing) {
                                continue; // Skip si déjà existant
                            }
                            
                            // Générer le SKU automatiquement : SKUvariante-taille
                            $sku = $colorVariant->sku . '-' . $size->name;
                            
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
                            
                            \App\Models\ProductSizeVariant::create([
                                'product_color_variant_id' => $colorVariant->id,
                                'product_id' => null,
                                'size_id' => $sizeId,
                                'sku' => $sku,
                            ]);
                            
                            $createdCount++;
                        }
                        
                        if ($createdCount > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Succès')
                                ->body($createdCount . ' variante(s) de taille créée(s) avec succès.')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Information')
                                ->body('Aucune nouvelle variante créée. Les tailles sélectionnées existent peut-être déjà.')
                                ->info()
                                ->send();
                        }
                    }),
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une taille')
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
                        $colorVariant = $livewire->getOwnerRecord();
                        
                        // Définir automatiquement le product_color_variant_id depuis la variante de couleur parente
                        $data['product_color_variant_id'] = $colorVariant->id;
                        $data['product_id'] = null; // Pas de produit direct
                        
                        // Générer le SKU automatiquement si size_id est défini
                        if (isset($data['size_id']) && !isset($data['sku'])) {
                            $size = \App\Models\Size::find($data['size_id']);
                            if ($size) {
                                $sku = $colorVariant->sku . '-' . $size->name;
                                
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
                    }),
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
