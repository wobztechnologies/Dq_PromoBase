<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Services\Ai3DService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Models3DRelationManager extends RelationManager
{
    protected static string $relationship = 'models3d';

    protected static ?string $title = 'Modèles 3D';

    protected static ?string $modelLabel = 'Modèle 3D';

    protected static ?string $pluralModelLabel = 'Modèles 3D';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('s3_url')
                    ->label('Modèle 3D (GLB)')
                    ->disk('s3')
                    ->directory('products/3d-models')
                    ->visibility('public')
                    ->acceptedFileTypes(['model/gltf-binary', 'application/octet-stream'])
                    ->maxSize(51200)
                    ->helperText('Téléchargez un modèle 3D au format GLB (max 50MB)')
                    ->required()
                    ->deletable(true)
                    ->downloadable(),
                Forms\Components\Toggle::make('is_default')
                    ->label('Modèle par défaut')
                    ->helperText('Cocher pour définir ce modèle comme modèle par défaut du produit. Un seul modèle peut être par défaut.')
                    ->default(false)
                    ->afterStateUpdated(function ($state, $record, $set) {
                        if ($state && $record) {
                            // Désactiver tous les autres modèles par défaut pour ce produit
                            \App\Models\ProductModel3D::where('product_id', $record->product_id)
                                ->where('id', '!=', $record->id)
                                ->update(['is_default' => false]);
                        }
                    }),
                Forms\Components\Select::make('colorVariants')
                    ->label('Variantes de couleur associées')
                    ->relationship('colorVariants', 'sku',
                        function ($query) {
                            $productId = $this->getOwnerRecord()->id;
                            return $query->where('product_id', $productId)
                                ->with('primaryColor.parent');
                        }
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) =>
                        $record->sku . ' - ' . ($record->primaryColor->full_name ?? $record->primaryColor->name ?? 'N/A')
                    )
                    ->helperText('Sélectionnez une ou plusieurs variantes de couleur pour ce modèle 3D'),
                Forms\Components\ViewField::make('preview_3d_model')
                    ->label('Aperçu du modèle 3D')
                    ->view('filament.components.threejs-preview-button')
                    ->visible(fn ($record) => $record && $record->s3_url)
                    ->viewData(fn ($record) => [
                        'modelUrl' => $record ? Storage::disk('s3')->temporaryUrl($record->s3_url, now()->addHours(24)) : null,
                        'productId' => $record?->id ?? 'new',
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['colorVariants.primaryColor.parent']))
            ->recordTitleAttribute('s3_url')
            ->columns([
                Tables\Columns\TextColumn::make('s3_url')
                    ->label('Fichier')
                    ->getStateUsing(fn ($record) => basename($record->s3_url))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_default')
                    ->label('Par défaut')
                    ->sortable()
                    ->onColor('warning')
                    ->offColor('gray'),
                Tables\Columns\TextColumn::make('colorVariants')
                    ->label('Variantes associées')
                    ->html()
                    ->getStateUsing(function ($record) {
                        if ($record->colorVariants->isEmpty()) {
                            return null;
                        }
                        return '<div style="display: flex; flex-direction: column; gap: 0.25rem;">' .
                            $record->colorVariants->map(function ($variant) {
                                $color = $variant->primaryColor->hex_code ?? $variant->primaryColor->parent?->hex_code ?? '#fbbf24';
                                $textColor = $this->getContrastColor($color);
                                $label = $variant->sku . ' (' . ($variant->primaryColor->full_name ?? $variant->primaryColor->name ?? 'N/A') . ')';
                                return '<span style="background-color: ' . $color . '; color: ' . $textColor . '; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500; display: inline-block; width: fit-content;">' . htmlspecialchars($label) . '</span>';
                            })->join('') .
                            '</div>';
                    })
                    ->placeholder('Aucune variante')
                    ->action(
                        Tables\Actions\Action::make('edit_variants')
                            ->label('Modifier les variantes')
                            ->form([
                                Forms\Components\Select::make('colorVariants')
                                    ->label('Variantes de couleur associées')
                                    ->options(function () {
                                        $productId = $this->getOwnerRecord()->id;
                                        return \App\Models\ProductColorVariant::where('product_id', $productId)
                                            ->with('primaryColor.parent')
                                            ->get()
                                            ->mapWithKeys(function ($variant) {
                                                $label = $variant->sku . ' - ' . ($variant->primaryColor->full_name ?? $variant->primaryColor->name ?? 'N/A');
                                                return [$variant->id => $label];
                                            })
                                            ->toArray();
                                    })
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Sélectionnez une ou plusieurs variantes de couleur pour ce modèle 3D'),
                            ])
                            ->fillForm(fn ($record) => [
                                'colorVariants' => $record->colorVariants->pluck('id')->toArray(),
                            ])
                            ->action(function ($record, array $data) {
                                $record->colorVariants()->sync($data['colorVariants'] ?? []);
                            })
                            ->successNotificationTitle('Variantes mises à jour')
                            ->modalSubmitActionLabel('Enregistrer')
                            ->modalCancelActionLabel('Annuler')
                    ),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Modèle par défaut')
                    ->placeholder('Tous')
                    ->trueLabel('Par défaut')
                    ->falseLabel('Non par défaut'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('3d_by_decq_ai')
                    ->label('3DbyDecqAi')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->modalHeading('Génération 3D par IA')
                    ->modalWidth('7xl')
                    ->form([
                        Forms\Components\Select::make('mode')
                            ->label('Mode')
                            ->options([
                                'general' => 'Mode Général',
                                'variant' => 'Mode Variant',
                            ])
                            ->required()
                            ->default('general')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('color_variant_id', null);
                                $set('selected_images', []);
                            }),
                        
                        Forms\Components\Select::make('color_variant_id')
                            ->label('Variante de couleur')
                            ->options(function (RelationManager $livewire) {
                                $productId = $livewire->getOwnerRecord()->id;
                                return \App\Models\ProductColorVariant::where('product_id', $productId)
                                    ->with('primaryColor.parent')
                                    ->get()
                                    ->mapWithKeys(function ($variant) {
                                        $label = $variant->sku . ' - ' . ($variant->primaryColor->full_name ?? $variant->primaryColor->name ?? 'N/A');
                                        return [$variant->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->visible(fn (callable $get) => $get('mode') === 'variant')
                            ->required(fn (callable $get) => $get('mode') === 'variant')
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('selected_images', [])),
                        
                        Forms\Components\Placeholder::make('info_selection')
                            ->label('')
                            ->content(function (callable $get) {
                                $selectedImages = $get('selected_images') ?? [];
                                if (is_string($selectedImages)) {
                                    $decoded = json_decode($selectedImages, true);
                                    $selectedImages = is_array($decoded) ? $decoded : [];
                                }
                                
                                $count = is_array($selectedImages) ? count($selectedImages) : 0;
                                
                                // Vérifier si une image Front est sélectionnée
                                $hasFront = false;
                                if ($count > 0 && is_array($selectedImages)) {
                                    $images = \App\Models\ProductImage::whereIn('id', $selectedImages)->get();
                                    $hasFront = $images->where('position', 'Front')->isNotEmpty();
                                }
                                
                                $warning = ($count > 0 && !$hasFront) 
                                    ? '<span class="text-red-600 dark:text-red-400 ml-2 font-medium">⚠️ Au moins une image Front est requise</span>' 
                                    : '';
                                
                                return new \Illuminate\Support\HtmlString(
                                    '<p class="text-sm text-gray-600 dark:text-gray-400">
                                        Images sélectionnées : <strong>' . $count . '</strong> / 6' . $warning . '
                                    </p>'
                                );
                            })
                            ->visible(fn (callable $get) => $get('mode') !== null && ($get('mode') === 'general' || ($get('mode') === 'variant' && $get('color_variant_id'))))
                            ->reactive(),
                        
                        Forms\Components\CheckboxList::make('selected_images')
                            ->label('Sélectionnez les images')
                            ->options(function (RelationManager $livewire, callable $get) {
                                $productId = $livewire->getOwnerRecord()->id;
                                $mode = $get('mode');
                                $variantId = $get('color_variant_id');
                                
                                // Filtrer les images : neutral_background = true ET product_only = true
                                $query = \App\Models\ProductImage::where('product_id', $productId)
                                    ->where('neutral_background', true)
                                    ->where('product_only', true)
                                    ->whereNotNull('position');
                                
                                // Si mode variant, filtrer par variante
                                if ($mode === 'variant' && $variantId) {
                                    $query->whereHas('colorVariants', function ($q) use ($variantId) {
                                        $q->where('product_color_variants.id', $variantId);
                                    });
                                }
                                
                                $images = $query->get();
                                
                                $options = [];
                                foreach ($images as $image) {
                                    $imageUrl = $image->signed_url;
                                    $position = $image->position;
                                    
                                    $label = '<div class="flex items-center gap-3">
                                        <img src="' . $imageUrl . '" class="w-16 h-16 object-contain rounded border" alt="' . $position . '">
                                        <span class="font-medium">' . $position . '</span>
                                    </div>';
                                    
                                    $options[$image->id] = new \Illuminate\Support\HtmlString($label);
                                }
                                
                                return $options;
                            })
                            ->columns(2)
                            ->gridDirection('row')
                            ->visible(fn (callable $get) => $get('mode') !== null && ($get('mode') === 'general' || ($get('mode') === 'variant' && $get('color_variant_id'))))
                            ->live()
                            ->dehydrated()
                            ->validationAttribute('images sélectionnées')
                            ->helperText('Maximum 6 images, une seule par position'),
                    ])
                    ->action(function (array $data, RelationManager $livewire) {
                        // Log pour debug
                        \Log::info('3D AI - Action appelée', [
                            'data_keys' => array_keys($data),
                            'selected_images_raw' => $data['selected_images'] ?? 'non défini',
                            'all_data' => $data,
                        ]);
                        
                        // Récupérer les images sélectionnées (c'est maintenant un tableau directement)
                        $selectedImages = $data['selected_images'] ?? [];
                        
                        // Les IDs sont des UUIDs (strings), ne pas les convertir en entiers
                        // Filtrer uniquement les valeurs vides
                        $selectedImages = array_filter((array) $selectedImages, function($id) {
                            return !empty($id);
                        });
                        $selectedImages = array_values($selectedImages);
                        
                        if (empty($selectedImages) || count($selectedImages) === 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Erreur')
                                ->body('Veuillez sélectionner au moins une image.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        if (count($selectedImages) > 6) {
                            \Filament\Notifications\Notification::make()
                                ->title('Erreur')
                                ->body('Vous ne pouvez sélectionner que 6 images maximum.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // Récupérer les images avec leurs positions
                        $images = \App\Models\ProductImage::whereIn('id', $selectedImages)->get();
                        
                        // 1. Vérifier qu'il y a au moins une image Front
                        $hasFront = $images->where('position', 'Front')->isNotEmpty();
                        
                        if (!$hasFront) {
                            \Filament\Notifications\Notification::make()
                                ->title('Erreur')
                                ->body('Vous devez sélectionner au moins une image de type Front.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // 2. Vérifier qu'il n'y a qu'une seule image par position
                        $positions = $images->pluck('position')->toArray();
                        if (count($positions) !== count(array_unique($positions))) {
                            \Filament\Notifications\Notification::make()
                                ->title('Erreur')
                                ->body('Vous ne pouvez sélectionner qu\'une seule image par position.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // 3. Préparer les données pour l'API
                        try {
                            $product = $livewire->getOwnerRecord();
                            $bucket = config('filesystems.disks.s3.bucket');
                            
                            // Mapper les positions aux clés attendues par l'API
                            $positionMapping = [
                                'Front' => 'front',
                                'Back' => 'back',
                                'Side' => 'left', // Par défaut, Side = left
                                'Top' => 'top',
                                'Bottom' => 'bottom',
                                'Part Zoom' => null, // Ignoré
                            ];
                            
                            $views = [];
                            foreach ($images as $image) {
                                $position = $image->position;
                                $apiPosition = $positionMapping[$position] ?? null;
                                
                                if ($apiPosition) {
                                    // Construire l'URL S3
                                    $s3Path = $image->s3_url;
                                    $s3Url = 's3://' . $bucket . '/' . $s3Path;
                                    $views[$apiPosition] = $s3Url;
                                }
                            }
                            
                            // Générer un chemin de sortie unique
                            $timestamp = now()->format('Y-m-d_H-i-s');
                            $uniqueId = Str::random(8);
                            $outputPath = 's3://' . $bucket . '/models/ai-generated/' . $product->sku . '_' . $timestamp . '_' . $uniqueId . '.glb';
                            
                            // Appeler le service AI
                            $aiService = new Ai3DService();
                            $result = $aiService->generate3D($views, $outputPath);
                            
                            if ($result['success']) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Génération lancée')
                                    ->body('La génération du modèle 3D a été lancée avec succès. Job ID: ' . ($result['job_id'] ?? 'N/A'))
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Erreur')
                                    ->body('Erreur lors de la génération : ' . ($result['error'] ?? 'Erreur inconnue'))
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Erreur')
                                ->body('Erreur lors de la génération : ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalSubmitActionLabel('Request 3D by Ai')
                    ->modalCancelActionLabel('Annuler'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn ($record) => $record->s3_url)
                    ->modalHeading(fn ($record) => 'Aperçu du modèle 3D')
                    ->modalContent(function ($record) {
                        $modelUrl = Storage::disk('s3')->temporaryUrl($record->s3_url, now()->addHours(24));
                        $modalId = str_replace('-', '_', $record->id);
                        return view('filament.components.threejs-preview-modal', [
                            'modelUrl' => $modelUrl,
                            'modalId' => $modalId,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function getContrastColor($hexColor): string
    {
        // Retirer le # si présent
        $hexColor = ltrim($hexColor, '#');

        // Convertir en RGB
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));

        // Calculer la luminosité relative
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        // Retourner noir ou blanc selon la luminosité
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
}
