<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Jobs\ProcessMeshy3DGeneration;
use App\Jobs\ProcessFal3DGeneration;
use App\Services\MeshyService;
use App\Services\FalService;
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
                    ->directory(function ($record, RelationManager $livewire) {
                        $product = $livewire->getOwnerRecord();
                        return $product->getAssetsBasePath();
                    })
                    ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file, RelationManager $livewire) {
                        $product = $livewire->getOwnerRecord();
                        $extension = 'glb';
                        
                        // Déterminer si le modèle est associé à une variante
                        $variantSku = null;
                        // Pour les modèles 3D uploadés manuellement, on ne peut pas déterminer la variante à ce stade
                        // Le nom sera généré sans variante
                        
                        return $product->generateAssetFilename($extension, $variantSku);
                    })
                    ->visibility('public')
                    ->acceptedFileTypes(['model/gltf-binary', 'application/octet-stream'])
                    ->maxSize(51200)
                    ->helperText(fn (callable $get) => $get('status') === \App\Models\ProductModel3D::STATUS_REQUESTED 
                        ? 'Le fichier sera disponible une fois la génération terminée.' 
                        : 'Téléchargez un modèle 3D au format GLB (max 50MB)')
                    ->required(fn (callable $get) => $get('status') !== \App\Models\ProductModel3D::STATUS_REQUESTED)
                    ->deletable(true),
                Forms\Components\Select::make('status')
                    ->label('Statut')
                    ->options([
                        \App\Models\ProductModel3D::STATUS_REQUESTED => 'Requested',
                        \App\Models\ProductModel3D::STATUS_ERROR => 'Error',
                        \App\Models\ProductModel3D::STATUS_IN_REVIEW => 'In Review',
                        \App\Models\ProductModel3D::STATUS_PUBLISHED => 'Published',
                    ])
                    ->default(\App\Models\ProductModel3D::STATUS_PUBLISHED)
                    ->required()
                    ->disabled(fn ($record) => $record && $record->status === \App\Models\ProductModel3D::STATUS_REQUESTED)
                    ->helperText('Statut du modèle 3D'),
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
                                ->with('primaryColor.manufacturer');
                        }
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        $color = $record->primaryColor;
                        $manufacturer = $color?->manufacturer?->name ?? '';
                        $label = $manufacturer ? "{$color->name} ({$manufacturer})" : ($color->name ?? 'N/A');
                        return $record->sku . ' - ' . $label;
                    })
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
            ->modifyQueryUsing(fn ($query) => $query->with(['colorVariants.primaryColor.manufacturer']))
            ->recordTitleAttribute('s3_url')
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        \App\Models\ProductModel3D::STATUS_REQUESTED => 'warning',
                        \App\Models\ProductModel3D::STATUS_ERROR => 'danger',
                        \App\Models\ProductModel3D::STATUS_IN_REVIEW => 'info',
                        \App\Models\ProductModel3D::STATUS_PUBLISHED => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        \App\Models\ProductModel3D::STATUS_REQUESTED => 'Requested',
                        \App\Models\ProductModel3D::STATUS_ERROR => 'Error',
                        \App\Models\ProductModel3D::STATUS_IN_REVIEW => 'In Review',
                        \App\Models\ProductModel3D::STATUS_PUBLISHED => 'Published',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('s3_url')
                    ->label('Fichier')
                    ->getStateUsing(fn ($record) => $record->s3_url ? basename($record->s3_url) : 'N/A')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Pas encore disponible'),
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
                                $color = $variant->primaryColor->hex_code ?? '#fbbf24';
                                $textColor = $this->getContrastColor($color);
                                $colorName = $variant->primaryColor->name ?? 'N/A';
                                $manufacturer = $variant->primaryColor->manufacturer?->name ?? '';
                                $label = $variant->sku . ' (' . ($manufacturer ? "{$colorName} ({$manufacturer})" : $colorName) . ')';
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
                                            ->with('primaryColor.manufacturer')
                                            ->get()
                                            ->mapWithKeys(function ($variant) {
                                                $color = $variant->primaryColor;
                                                $manufacturer = $color?->manufacturer?->name ?? '';
                                                $label = $manufacturer ? "{$color->name} ({$manufacturer})" : ($color->name ?? 'N/A');
                                                $label = $variant->sku . ' - ' . $label;
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
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        \App\Models\ProductModel3D::STATUS_REQUESTED => 'Requested',
                        \App\Models\ProductModel3D::STATUS_ERROR => 'Error',
                        \App\Models\ProductModel3D::STATUS_IN_REVIEW => 'In Review',
                        \App\Models\ProductModel3D::STATUS_PUBLISHED => 'Published',
                    ])
                    ->multiple(),
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
                                    ->with('primaryColor.manufacturer')
                                    ->get()
                                    ->mapWithKeys(function ($variant) {
                                        $color = $variant->primaryColor;
                                        $manufacturer = $color?->manufacturer?->name ?? '';
                                        $label = $manufacturer ? "{$color->name} ({$manufacturer})" : ($color->name ?? 'N/A');
                                        $label = $variant->sku . ' - ' . $label;
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
                        
                        Forms\Components\Select::make('ai_model')
                            ->label('AI Model')
                            ->options([
                                'fal' => 'Standard Multi image',
                                'fal-mono' => 'Standard mono image',
                                'meshy-5' => 'Max',
                            ])
                            ->default('fal')
                            ->required()
                            ->helperText('Choisissez le modèle AI à utiliser pour la génération'),
                        
                        Forms\Components\Placeholder::make('info_selection')
                            ->label('')
                            ->content(function (callable $get) {
                                $selectedImages = $get('selected_images') ?? [];
                                $aiModel = $get('ai_model') ?? 'fal';
                                
                                if (is_string($selectedImages)) {
                                    $decoded = json_decode($selectedImages, true);
                                    $selectedImages = is_array($decoded) ? $decoded : [];
                                }
                                
                                $count = is_array($selectedImages) ? count($selectedImages) : 0;
                                
                                // Vérifier les images requises selon le modèle AI
                                $hasFront = false;
                                $hasBack = false;
                                $hasLeftOrRight = false;
                                
                                if ($count > 0 && is_array($selectedImages)) {
                                    $images = \App\Models\ProductImage::whereIn('id', $selectedImages)->get();
                                    $hasFront = $images->where('position', 'Front')->isNotEmpty();
                                    $hasBack = $images->where('position', 'Back')->isNotEmpty();
                                    $hasLeftOrRight = $images->whereIn('position', ['Left', 'Right'])->isNotEmpty();
                                }
                                
                                $warnings = [];
                                
                                if ($count > 0 && !$hasFront) {
                                    $warnings[] = '⚠️ Au moins une image Front est requise';
                                }
                                
                                if ($aiModel === 'fal' && $count > 0) {
                                    if (!$hasBack) {
                                        $warnings[] = '⚠️ Une image Back est requise pour Standard Multi image';
                                    }
                                    if (!$hasLeftOrRight) {
                                        $warnings[] = '⚠️ Une image Left ou Right est requise pour Standard Multi image';
                                    }
                                }
                                
                                $warningHtml = !empty($warnings) 
                                    ? '<span class="text-red-600 dark:text-red-400 ml-2 font-medium">' . implode('<br>', $warnings) . '</span>' 
                                    : '';
                                
                                $modelInfo = match($aiModel) {
                                    'fal' => ' (Standard Multi image: Front, Back, Left/Right requis)',
                                    'fal-mono' => ' (Standard mono image: Front requis uniquement)',
                                    default => ' (Max: 1-4 images)',
                                };
                                
                                return new \Illuminate\Support\HtmlString(
                                    '<p class="text-sm text-gray-600 dark:text-gray-400">
                                        Images sélectionnées : <strong>' . $count . '</strong> / 6' . $modelInfo . $warningHtml . '
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
                        
                        // Récupérer le modèle AI choisi
                        $aiModel = $data['ai_model'] ?? 'fal';
                        
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
                        
                        // 3. Vérifications spécifiques pour Standard Multi image
                        if ($aiModel === 'fal') {
                            $hasBack = $images->where('position', 'Back')->isNotEmpty();
                            $hasLeft = $images->where('position', 'Left')->isNotEmpty();
                            $hasRight = $images->where('position', 'Right')->isNotEmpty();
                            
                            if (!$hasBack) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Erreur')
                                    ->body('Pour le modèle Standard Multi image, vous devez sélectionner une image Back.')
                                    ->danger()
                                    ->send();
                                return;
                            }
                            
                            if (!$hasLeft && !$hasRight) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Erreur')
                                    ->body('Pour le modèle Standard Multi image, vous devez sélectionner une image Left ou Right.')
                                    ->danger()
                                    ->send();
                                return;
                            }
                        }
                        
                        // Vérification pour fal-mono (Standard mono image) - seulement Front requis
                        if ($aiModel === 'fal-mono') {
                            // Pas de vérification supplémentaire, Front est déjà vérifié plus haut
                        }
                        
                        // 4. Vérifier qu'il n'y a pas déjà un modèle avec un status bloquant
                        // La vérification dépend du mode sélectionné
                        $product = $livewire->getOwnerRecord();
                        $blockingStatuses = \App\Models\ProductModel3D::getBlockingStatuses();
                        $mode = $data['mode'] ?? 'general';
                        $colorVariantId = $data['color_variant_id'] ?? null;
                        
                        if ($mode === 'general') {
                            // Mode général : vérifier s'il existe un modèle 3D "général" (sans variante associée)
                            $existingModel = \App\Models\ProductModel3D::where('product_id', $product->id)
                                ->whereIn('status', $blockingStatuses)
                                ->whereDoesntHave('colorVariants') // Modèle sans variante = modèle général
                                ->first();
                            
                            if ($existingModel) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Génération impossible')
                                    ->body('Un modèle 3D général existe déjà avec le statut "' . $existingModel->status . '". Vous ne pouvez pas lancer une nouvelle génération en mode général.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                        } else {
                            // Mode variant : vérifier s'il existe un modèle 3D pour cette variante spécifique
                            $existingModel = \App\Models\ProductModel3D::where('product_id', $product->id)
                                ->whereIn('status', $blockingStatuses)
                                ->whereHas('colorVariants', function ($query) use ($colorVariantId) {
                                    $query->where('product_color_variants.id', $colorVariantId);
                                })
                                ->first();
                            
                            if ($existingModel) {
                                $variant = \App\Models\ProductColorVariant::find($colorVariantId);
                                \Filament\Notifications\Notification::make()
                                    ->title('Génération impossible')
                                    ->body('Un modèle 3D existe déjà pour la variante "' . ($variant->sku ?? 'N/A') . '" avec le statut "' . $existingModel->status . '". Vous ne pouvez pas lancer une nouvelle génération pour cette variante.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                        }
                        
                        // 5. Préparer les données pour l'API
                        try {
                            $bucket = config('filesystems.disks.s3.bucket');
                            
                            // Récupérer le mode et la variante AVANT de générer le chemin
                            $mode = $data['mode'] ?? 'general';
                            $colorVariantId = $data['color_variant_id'] ?? null;
                            
                            // Générer un chemin de sortie selon la nouvelle structure : /ManufacturerName/SKU/assets/SKU-variant-numero.glb
                            $variantSku = null;
                            if ($mode === 'variant' && $colorVariantId) {
                                $variant = \App\Models\ProductColorVariant::find($colorVariantId);
                                if ($variant) {
                                    $variantSku = $variant->sku;
                                }
                            }
                            
                            $s3Path = $product->generateAssetPath('glb', $variantSku);
                            $outputPath = 's3://' . $bucket . '/' . $s3Path;
                            
                            // Créer l'enregistrement avec status "Requested" AVANT l'appel API
                            $model3D = \App\Models\ProductModel3D::create([
                                'product_id' => $product->id,
                                's3_url' => null, // Pas encore de fichier
                                'status' => \App\Models\ProductModel3D::STATUS_REQUESTED,
                                'is_default' => false,
                            ]);
                            
                            // Si mode variant, associer la variante au modèle 3D
                            if ($mode === 'variant' && $colorVariantId) {
                                $model3D->colorVariants()->attach($colorVariantId);
                                \Log::info('Variante associée au modèle 3D', [
                                    'model_3d_id' => $model3D->id,
                                    'variant_id' => $colorVariantId,
                                ]);
                            }
                            
                            // Préparer les URLs publiques des images
                            $imageUrlsMap = [];
                            foreach ($images as $image) {
                                $s3Path = $image->s3_url;
                                
                                // Générer une URL temporaire présignée valide 24h
                                try {
                                    $publicUrl = Storage::disk('s3')->temporaryUrl($s3Path, now()->addHours(24));
                                    $imageUrlsMap[$image->position] = $publicUrl;
                                    \Log::info('URL publique générée', [
                                        'image_id' => $image->id,
                                        'position' => $image->position,
                                        's3_path' => $s3Path,
                                        'public_url' => $publicUrl,
                                    ]);
                                } catch (\Exception $e) {
                                    // Si échec avec temporaryUrl, essayer l'URL publique directe
                                    try {
                                        $publicUrl = Storage::disk('s3')->url($s3Path);
                                        $imageUrlsMap[$image->position] = $publicUrl;
                                        \Log::warning('Utilisation de l\'URL publique directe (fallback)', [
                                            'image_id' => $image->id,
                                            'position' => $image->position,
                                            's3_path' => $s3Path,
                                            'public_url' => $publicUrl,
                                            'error' => $e->getMessage(),
                                        ]);
                                    } catch (\Exception $e2) {
                                        \Log::error('Impossible de générer une URL publique pour l\'image', [
                                            'image_id' => $image->id,
                                            's3_path' => $s3Path,
                                            'error' => $e2->getMessage(),
                                        ]);
                                        throw new \Exception('Impossible de générer une URL publique pour l\'image: ' . $s3Path);
                                    }
                                }
                            }
                            
                            // Appeler le service approprié selon le modèle AI choisi
                            if ($aiModel === 'fal') {
                                // Utiliser Standard Multi image avec Front, Back, et Left/Right
                                $frontUrl = $imageUrlsMap['Front'] ?? null;
                                $backUrl = $imageUrlsMap['Back'] ?? null;
                                $sideUrl = $imageUrlsMap['Left'] ?? $imageUrlsMap['Right'] ?? null;
                                
                                if (!$frontUrl || !$backUrl || !$sideUrl) {
                                    throw new \Exception('URLs manquantes (Front, Back, Left/Right requis)');
                                }
                                
                                $falService = new FalService();
                                $result = $falService->generate3D($frontUrl, $backUrl, $sideUrl, $outputPath);
                                
                                \Log::info('Génération 3D lancée avec fal.ai', [
                                    'model_3d_id' => $model3D->id,
                                    'request_id' => $result['request_id'] ?? null,
                                    'status' => $result['status'] ?? null,
                                ]);
                                
                                if ($result['success']) {
                                    // Si le modèle est déjà disponible (status completed), télécharger immédiatement
                                    if ($result['status'] === 'completed' && $result['model_mesh_url']) {
                                        // Dispatcher le job pour télécharger et traiter le modèle
                                        ProcessFal3DGeneration::dispatch($model3D->id, $result['model_mesh_url'], $outputPath);
                                    } else {
                                        // Sinon, stocker le request_id pour vérification ultérieure
                                        $model3D->update([
                                            'meshy_task_id' => $result['request_id'], // Réutiliser ce champ pour fal.ai request_id
                                        ]);
                                        
                                        // Dispatcher le job pour vérifier le statut et télécharger le modèle
                                        ProcessFal3DGeneration::dispatch($model3D->id, null, $outputPath)
                                            ->delay(now()->addSeconds(30));
                                    }
                                    
                                    $message = 'La génération du modèle 3D a été lancée avec succès (Standard Multi image)';
                                    if ($mode === 'variant' && $colorVariantId) {
                                        $variant = \App\Models\ProductColorVariant::find($colorVariantId);
                                        $message .= ' (Variante: ' . ($variant->sku ?? 'N/A') . ')';
                                    }
                                    
                                    \Filament\Notifications\Notification::make()
                                        ->title('Génération lancée')
                                        ->body($message)
                                        ->success()
                                        ->send();
                                } else {
                                    throw new \Exception($result['error'] ?? 'Erreur inconnue lors de la génération');
                                }
                            } elseif ($aiModel === 'fal-mono') {
                                // Utiliser Standard mono image avec une seule image Front
                                $frontUrl = $imageUrlsMap['Front'] ?? null;
                                
                                if (!$frontUrl) {
                                    throw new \Exception('URL manquante (Front requis)');
                                }
                                
                                $falService = new FalService();
                                $result = $falService->generate3DFromSingleImage($frontUrl, $outputPath);
                                
                                \Log::info('Génération 3D lancée avec fal.ai (mono)', [
                                    'model_3d_id' => $model3D->id,
                                    'request_id' => $result['request_id'] ?? null,
                                    'status' => $result['status'] ?? null,
                                ]);
                                
                                if ($result['success']) {
                                    // Si le modèle est déjà disponible (status completed), télécharger immédiatement
                                    if ($result['status'] === 'completed' && $result['model_mesh_url']) {
                                        // Dispatcher le job pour télécharger et traiter le modèle
                                        ProcessFal3DGeneration::dispatch($model3D->id, $result['model_mesh_url'], $outputPath);
                                    } else {
                                        // Sinon, stocker le request_id pour vérification ultérieure
                                        $model3D->update([
                                            'meshy_task_id' => $result['request_id'], // Réutiliser ce champ pour fal.ai request_id
                                        ]);
                                        
                                        // Dispatcher le job pour vérifier le statut et télécharger le modèle
                                        ProcessFal3DGeneration::dispatch($model3D->id, null, $outputPath)
                                            ->delay(now()->addSeconds(30));
                                    }
                                    
                                    $message = 'La génération du modèle 3D a été lancée avec succès (Standard mono image)';
                                    if ($mode === 'variant' && $colorVariantId) {
                                        $variant = \App\Models\ProductColorVariant::find($colorVariantId);
                                        $message .= ' (Variante: ' . ($variant->sku ?? 'N/A') . ')';
                                    }
                                    
                                    \Filament\Notifications\Notification::make()
                                        ->title('Génération lancée')
                                        ->body($message)
                                        ->success()
                                        ->send();
                                } else {
                                    throw new \Exception($result['error'] ?? 'Erreur inconnue lors de la génération mono');
                                }
                            } else {
                                // Utiliser Max (meshy-5)
                                $imageUrls = array_values($imageUrlsMap);
                                
                                \Log::info('URLs préparées pour Meshy', [
                                    'count' => count($imageUrls),
                                    'urls' => $imageUrls,
                                ]);
                                
                                $meshyService = new MeshyService();
                                $result = $meshyService->generate3D($imageUrls, $outputPath, 'meshy-5');
                                
                                \Log::info('Génération 3D lancée avec Meshy', [
                                    'model_3d_id' => $model3D->id,
                                    'meshy_model' => 'meshy-5',
                                    'task_id' => $result['task_id'] ?? null,
                                ]);
                                
                                if ($result['success']) {
                                    // Mettre à jour avec le task_id
                                    $model3D->update([
                                        'meshy_task_id' => $result['task_id'],
                                    ]);
                                    
                                    // Dispatcher le job pour vérifier le statut et télécharger le modèle
                                    \App\Jobs\ProcessMeshy3DGeneration::dispatch($model3D->id, $result['task_id'], $outputPath)
                                        ->delay(now()->addSeconds(30));
                                    
                                    $message = 'La génération du modèle 3D a été lancée avec succès (Max). Task ID: ' . $result['task_id'];
                                    if ($mode === 'variant' && $colorVariantId) {
                                        $variant = \App\Models\ProductColorVariant::find($colorVariantId);
                                        $message .= ' (Variante: ' . ($variant->sku ?? 'N/A') . ')';
                                    }
                                    
                                    \Filament\Notifications\Notification::make()
                                        ->title('Génération lancée')
                                        ->body($message)
                                        ->success()
                                        ->send();
                                } else {
                                    throw new \Exception($result['error'] ?? 'Erreur inconnue lors de la génération en mode Max');
                                }
                            }
                        } catch (\Exception $e) {
                            // En cas d'exception, mettre à jour le status en "Error"
                            if (isset($model3D)) {
                                $model3D->update([
                                    'status' => \App\Models\ProductModel3D::STATUS_ERROR,
                                ]);
                            }
                            
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
                        try {
                            $modelUrl = Storage::disk('s3')->temporaryUrl($record->s3_url, now()->addHours(24));
                            \Log::info('URL présignée générée pour la modal 3D', [
                                'model_id' => $record->id,
                                's3_path' => $record->s3_url,
                                'presigned_url' => $modelUrl,
                            ]);
                        } catch (\Exception $e) {
                            \Log::error('Erreur lors de la génération de l\'URL présignée pour la modal 3D', [
                                'model_id' => $record->id,
                                's3_path' => $record->s3_url,
                                'error' => $e->getMessage(),
                            ]);
                            $modelUrl = null;
                        }
                        
                        $modalId = str_replace('-', '_', $record->id);
                        return view('filament.components.threejs-preview-modal', [
                            'modelUrl' => $modelUrl,
                            'modalId' => $modalId,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
                Tables\Actions\Action::make('download')
                    ->label('Télécharger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn ($record) => $record->s3_url)
                    ->action(function ($record) {
                        if (!$record->s3_url) {
                            \Filament\Notifications\Notification::make()
                                ->title('Erreur')
                                ->body('Aucun fichier disponible pour le téléchargement.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // Générer une URL présignée valide 1 heure
                        $url = Storage::disk('s3')->temporaryUrl($record->s3_url, now()->addHours(1));
                        
                        // Rediriger vers l'URL présignée pour télécharger le fichier
                        return redirect($url);
                    })
                    ->requiresConfirmation(false),
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
