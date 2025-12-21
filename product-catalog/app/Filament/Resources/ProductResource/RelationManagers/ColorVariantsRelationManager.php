<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Size;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ColorVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'colorVariants';

    protected static ?string $title = 'Variantes de couleurs';

    protected static ?string $modelLabel = 'Variante de couleur';

    protected static ?string $pluralModelLabel = 'Variantes de couleurs';

    public function form(Form $form): Form
    {
        $product = $this->getOwnerRecord();
        $manufacturerId = $product->manufacturer_id;
        
        return $form
            ->schema([
                Forms\Components\Select::make('primary_color_parent_id')
                    ->label('Couleur principale')
                    ->options(function ($record) {
                        // Si on édite, récupérer la couleur principale de la couleur fabricant existante
                        if ($record && $record->primaryColor && $record->primaryColor->parent_id) {
                            $parent = \App\Models\PrimaryColor::find($record->primaryColor->parent_id);
                            if ($parent) {
                                return [$parent->id => $parent->name];
                            }
                        }
                        return \App\Models\PrimaryColor::whereNull('parent_id')
                            ->whereNull('manufacturer_id')
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->required()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->primaryColor && $record->primaryColor->parent_id) {
                            $component->state($record->primaryColor->parent_id);
                        }
                    })
                    ->helperText('Sélectionnez d\'abord une couleur principale'),
                
                Forms\Components\Select::make('primary_color_id')
                    ->label('Couleur fabricant')
                    ->options(function ($get, $state, $record) use ($manufacturerId) {
                        $parentId = $get('primary_color_parent_id');
                        
                        // Si on édite et qu'il n'y a pas de parent sélectionné, utiliser celui de la couleur existante
                        if (!$parentId && $record && $record->primaryColor && $record->primaryColor->parent_id) {
                            $parentId = $record->primaryColor->parent_id;
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
                            ->get()
                            ->mapWithKeys(function ($color) {
                                $manufacturer = $color->manufacturer?->name ?? '';
                                $label = $manufacturer ? "{$color->name} ({$manufacturer})" : $color->name;
                                return [$color->id => $label];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->visible(function ($get, $record) {
                        $parentId = $get('primary_color_parent_id');
                        if (!$parentId && $record && $record->primaryColor && $record->primaryColor->parent_id) {
                            return true;
                        }
                        return !empty($parentId);
                    })
                    ->helperText('Sélectionnez ensuite la couleur fabricant correspondant à la couleur principale et au fabricant du produit'),
                
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
            ->modifyQueryUsing(fn ($query) => $query->with(['primaryColor.parent', 'primaryColor.manufacturer', 'productImages', 'sizeVariants.size']))
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
                Tables\Columns\TextColumn::make('primaryColor.parent.name')
                    ->label('Couleur')
                    ->getStateUsing(function ($record) {
                        $color = $record->primaryColor;
                        if (!$color) {
                            return '-';
                        }
                        // Si c'est une couleur fabricant (a un parent), afficher le parent
                        if ($color->parent_id) {
                            return $color->parent?->name ?? '-';
                        }
                        // Si c'est une couleur principale (pas de parent), afficher son nom
                        return $color->name ?? '-';
                    })
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('primaryColor', function ($q) use ($search) {
                            $q->where(function ($subQ) use ($search) {
                                $subQ->where('name', 'like', "%{$search}%")
                                    ->orWhereHas('parent', function ($parentQ) use ($search) {
                                        $parentQ->where('name', 'like', "%{$search}%");
                                    });
                            });
                        });
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('primaryColor.name')
                    ->label('Couleur fabricant')
                    ->getStateUsing(function ($record) {
                        $color = $record->primaryColor;
                        if (!$color) {
                            return '-';
                        }
                        // Si c'est une couleur fabricant (a un parent), afficher son nom
                        if ($color->parent_id) {
                            return $color->name ?? '-';
                        }
                        // Si c'est une couleur principale (pas de parent), afficher "-"
                        return '-';
                    })
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('primaryColor', function ($q) use ($search) {
                            $q->whereNotNull('parent_id')
                                ->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('primaryColor.hex_code')
                    ->label('Aperçu')
                    ->html()
                    ->getStateUsing(function ($record) {
                        $primaryColor = $record->primaryColor;
                        
                        if (!$primaryColor) {
                            return '-';
                        }
                        
                        // Vérifier l'image de la couleur fabricant, puis celle du parent
                        $imageUrl = null;
                        $imagePath = null;
                        if ($primaryColor->image_s3_url) {
                            $imagePath = $primaryColor->image_s3_url;
                        } elseif ($primaryColor->parent?->image_s3_url) {
                            $imagePath = $primaryColor->parent->image_s3_url;
                        }
                        
                        if ($imagePath) {
                            try {
                                $imageUrl = Storage::disk('s3')->temporaryUrl($imagePath, now()->addHours(24));
                            } catch (\Exception $e) {
                                $imageUrl = Storage::disk('s3')->url($imagePath);
                            }
                        }
                        
                        $hexCode = $primaryColor->hex_code ?? $primaryColor->parent?->hex_code;
                        
                        if ($imageUrl) {
                            return '<img src="' . $imageUrl . '" alt="' . htmlspecialchars($primaryColor->name ?? '') . '" class="w-6 h-6 rounded border border-gray-300 object-cover" />';
                        }
                        
                        if ($hexCode) {
                            return '<div class="w-6 h-6 rounded border border-gray-300" style="background-color: ' . $hexCode . '"></div>';
                        }
                        
                        return '-';
                    })
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
                        // Si on crée une variante de couleur, supprimer la couleur fabricant du produit
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
                Tables\Actions\Action::make('edit_color')
                    ->label('Modifier couleur')
                    ->icon('heroicon-o-paint-brush')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Modifier la couleur')
                    ->form([
                        Forms\Components\Select::make('primary_color_parent_id')
                            ->label('Couleur principale')
                            ->options(function ($record) {
                                // Récupérer la couleur principale actuelle si elle existe
                                if ($record && $record->primaryColor && $record->primaryColor->parent_id) {
                                    $parent = \App\Models\PrimaryColor::find($record->primaryColor->parent_id);
                                    if ($parent) {
                                        return [$parent->id => $parent->name];
                                    }
                                }
                                return \App\Models\PrimaryColor::whereNull('parent_id')
                                    ->whereNull('manufacturer_id')
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->required()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record && $record->primaryColor && $record->primaryColor->parent_id) {
                                    $component->state($record->primaryColor->parent_id);
                                }
                            })
                            ->helperText('Sélectionnez d\'abord une couleur principale'),
                        
                        Forms\Components\Select::make('primary_color_id')
                            ->label('Couleur fabricant')
                            ->options(function (callable $get, $record) {
                                $product = $this->getOwnerRecord();
                                $manufacturerId = $product->manufacturer_id;
                                $parentId = $get('primary_color_parent_id');
                                
                                // Si on édite et qu'il n'y a pas de parent sélectionné, utiliser celui de la couleur existante
                                if (!$parentId && $record && $record->primaryColor && $record->primaryColor->parent_id) {
                                    $parentId = $record->primaryColor->parent_id;
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
                                    ->get()
                                    ->mapWithKeys(function ($color) {
                                        $manufacturer = $color->manufacturer?->name ?? '';
                                        $label = $manufacturer ? "{$color->name} ({$manufacturer})" : $color->name;
                                        return [$color->id => $label];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->visible(function ($get, $record) {
                                $parentId = $get('primary_color_parent_id');
                                if (!$parentId && $record && $record->primaryColor && $record->primaryColor->parent_id) {
                                    return true;
                                }
                                return !empty($parentId);
                            })
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record && $record->primaryColor) {
                                    $component->state($record->primaryColor->id);
                                }
                            })
                            ->helperText('Sélectionnez ensuite la couleur fabricant correspondant à la couleur principale et au fabricant du produit'),
                    ])
                    ->action(function (array $data, $record) {
                        if (isset($data['primary_color_id'])) {
                            $record->primary_color_id = $data['primary_color_id'];
                            $record->save();
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Couleur modifiée')
                                ->body('La couleur de la variante a été mise à jour avec succès.')
                                ->success()
                                ->send();
                        }
                    }),
                
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, $record): array {
                        // Pré-remplir la couleur principale si elle existe
                        if ($record && $record->primaryColor && $record->primaryColor->parent_id) {
                            $data['primary_color_parent_id'] = $record->primaryColor->parent_id;
                        }
                        return $data;
                    })
                    ->after(function ($record, array $data) {
                        // S'assurer que primary_color_id est bien défini
                        if (isset($data['primary_color_id'])) {
                            $record->primary_color_id = $data['primary_color_id'];
                            $record->save();
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($livewire) {
                        // Si toutes les variantes de couleur sont supprimées, on peut définir une couleur principale
                        $product = $livewire->getOwnerRecord();
                        if ($product->colorVariants()->count() === 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Information')
                                ->body('Vous pouvez maintenant définir une couleur fabricant pour ce produit simple.')
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
