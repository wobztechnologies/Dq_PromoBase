<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Size;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Table;

class ColorVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'colorVariants';

    protected static ?string $title = 'Variantes de couleurs';

    protected static ?string $modelLabel = 'Variante de couleur';

    protected static ?string $pluralModelLabel = 'Variantes de couleurs';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('primary_color_id')
                    ->label('Couleur')
                    ->relationship('primaryColor', 'name', 
                        fn ($query) => $query->with('parent')->orderBy('parent_id')->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable(['name', 'parent.name'])
                    ->preload()
                    ->required()
                    ->helperText('Sélectionnez la couleur (sous-couleur) de cette variante. Les sous-couleurs affichent le nom complet (ex: "Bleu Hawaii")'),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU de la variante')
                    ->required()
                    ->maxLength(255)
                    ->unique(\App\Models\ProductColorVariant::class, 'sku', ignoreRecord: true)
                    ->placeholder('Ex: PROD-000001-ROU')
                    ->helperText('SKU unique pour cette variante de couleur'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['primaryColor.parent', 'productImages', 'sizeVariants.size']))
            ->recordTitleAttribute('sku')
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        $actionId = 'add-sizes-' . $record->id;
                        return new \Illuminate\Support\HtmlString(
                            '<div class="flex items-center gap-2">
                                <span>' . htmlspecialchars($state) . '</span>
                                <button 
                                    type="button"
                                    onclick="document.querySelector(\'[data-action-id=\'' . $actionId . '\']\')?.click()"
                                    class="inline-flex items-center justify-center w-6 h-6 rounded-md text-xs font-medium text-white bg-success-600 hover:bg-success-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-success-500 transition-colors"
                                    title="Ajouter des tailles"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>'
                        );
                    })
                    ->html(),
                Tables\Columns\TextColumn::make('primaryColor.full_name')
                    ->label('Couleur')
                    ->getStateUsing(fn ($record) => $record->primaryColor->full_name ?? '-')
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('primaryColor', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhereHas('parent', function ($qp) use ($search) {
                                  $qp->where('name', 'like', "%{$search}%");
                              });
                        });
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('primaryColor.parent.name')
                    ->label('Couleur principale')
                    ->placeholder('—'),
                Tables\Columns\ColorColumn::make('primaryColor.hex_code')
                    ->label('Aperçu')
                    ->getStateUsing(fn ($record) => $record->primaryColor->hex_code ?? $record->primaryColor->parent?->hex_code)
                    ->sortable(),
                Tables\Columns\TextColumn::make('productImages')
                    ->label('Images')
                    ->html()
                    ->getStateUsing(function ($record) {
                        $count = $record->productImages->count();
                        if ($count === 0) {
                            return '<span class="text-gray-400"><svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg></span>';
                        }
                        return '<span class="text-blue-600 cursor-pointer hover:text-blue-800"><svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg> (' . $count . ')</span>';
                    })
                    ->action(
                        Tables\Actions\Action::make('view_images')
                            ->label('Images de la variante')
                            ->modalHeading(fn ($record) => 'Images de la variante ' . $record->sku)
                            ->modalContent(fn ($record) => view('filament.components.variant-images-modal', [
                                'images' => $record->productImages,
                            ]))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Fermer')
                            ->requiresConfirmation(false)
                    ),
                Tables\Columns\TextColumn::make('sizeVariants')
                    ->label('Variantes de taille')
                    ->html()
                    ->getStateUsing(function ($record) {
                        $sizeVariants = $record->sizeVariants;
                        if ($sizeVariants->isEmpty()) {
                            return '<span class="text-gray-400 text-sm">Aucune variante de taille</span>';
                        }
                        
                        $items = $sizeVariants->map(function ($variant) {
                            return '<span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 mr-1 mb-1">' 
                                . htmlspecialchars($variant->size->name ?? '-') . ' (' . htmlspecialchars($variant->sku) . ')' 
                                . '</span>';
                        })->implode('');
                        
                        return '<div class="flex flex-wrap gap-1">' . $items . '</div>';
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
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
                        // Si on crée une variante de couleur, supprimer la couleur principale du produit
                        $product = $livewire->getOwnerRecord();
                        if ($product->primary_color_id) {
                            $product->primary_color_id = null;
                            $product->saveQuietly();
                        }
                        return $data;
                    })
                    ->after(function ($livewire) {
                        // S'assurer que la couleur principale est supprimée après création
                        $product = $livewire->getOwnerRecord();
                        if ($product->primary_color_id) {
                            $product->primary_color_id = null;
                            $product->saveQuietly();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('add_sizes')
                    ->label('Ajouter des tailles')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Ajouter des tailles')
                    ->extraAttributes(fn ($record) => ['data-action-id' => 'add-sizes-' . $record->id])
                    ->hiddenLabel()
                    ->form([
                        Forms\Components\CheckboxList::make('size_ids')
                            ->label('Sélectionnez les tailles à créer')
                            ->options(function () {
                                return Size::orderBy('order')->orderBy('name')->pluck('name', 'id');
                            })
                            ->required()
                            ->columns(3)
                            ->helperText('Cochez les tailles que vous souhaitez créer pour cette variante de couleur. Le SKU sera généré automatiquement au format : SKUvariante-taille'),
                    ])
                    ->action(function (array $data, $record): void {
                        $createdCount = 0;
                        $skippedCount = 0;
                        
                        foreach ($data['size_ids'] as $sizeId) {
                            $size = Size::find($sizeId);
                            if (!$size) {
                                continue;
                            }
                            
                            // Vérifier si cette combinaison existe déjà
                            $existing = \App\Models\ProductSizeVariant::where('product_color_variant_id', $record->id)
                                ->where('size_id', $sizeId)
                                ->first();
                            
                            if ($existing) {
                                $skippedCount++;
                                continue; // Skip si déjà existant
                            }
                            
                            // Générer le SKU automatiquement : SKUvariante-taille
                            $sku = $record->sku . '-' . $size->name;
                            
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
                                'product_color_variant_id' => $record->id,
                                'product_id' => null,
                                'size_id' => $sizeId,
                                'sku' => $sku,
                            ]);
                            
                            $createdCount++;
                        }
                        
                        // Recharger la relation pour mettre à jour l'affichage
                        $record->load('sizeVariants.size');
                        
                        if ($createdCount > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Succès')
                                ->body($createdCount . ' variante(s) de taille créée(s) avec succès.' . ($skippedCount > 0 ? ' (' . $skippedCount . ' déjà existante(s) ignorée(s))' : ''))
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Information')
                                ->body('Aucune nouvelle variante créée. Les tailles sélectionnées existent déjà.')
                                ->info()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($livewire) {
                        // Si toutes les variantes de couleur sont supprimées, on peut définir une couleur principale
                        $product = $livewire->getOwnerRecord();
                        if ($product->colorVariants()->count() === 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Information')
                                ->body('Vous pouvez maintenant définir une couleur principale pour ce produit simple.')
                                ->info()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
