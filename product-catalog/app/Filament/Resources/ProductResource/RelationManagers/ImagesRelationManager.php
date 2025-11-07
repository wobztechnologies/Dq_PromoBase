<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Images du produit';

    protected static ?string $modelLabel = 'Image';

    protected static ?string $pluralModelLabel = 'Images';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('s3_url')
                    ->label('Image')
                    ->disk('s3')
                    ->directory('products/images')
                    ->visibility('public')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        null,
                        '16:9',
                        '4:3',
                        '1:1',
                    ])
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->helperText('Téléchargez une image du produit (max 5MB, formats: JPG, PNG, WebP)')
                    ->required()
                    ->deletable(true)
                    ->downloadable(),
                Forms\Components\Select::make('position')
                    ->label('Position')
                    ->options([
                        'Front' => 'Front',
                        'Back' => 'Back',
                        'Left' => 'Left',
                        'Right' => 'Right',
                        'Top' => 'Top',
                        'Bottom' => 'Bottom',
                    ])
                    ->placeholder('Sélectionnez la position de l\'image')
                    ->searchable()
                    ->helperText('Position/vue de l\'image du produit'),
                Forms\Components\Toggle::make('neutral_background')
                    ->label('Neutral background')
                    ->helperText('Cocher si l\'image a un fond neutre')
                    ->default(false),
                Forms\Components\Toggle::make('is_default')
                    ->label('Image par défaut')
                    ->helperText('Cocher pour définir cette image comme image par défaut du produit. Une seule image peut être par défaut.')
                    ->default(false)
                    ->afterStateUpdated(function ($state, $record, $set) {
                        if ($state && $record) {
                            // Désactiver toutes les autres images par défaut pour ce produit
                            \App\Models\ProductImage::where('product_id', $record->product_id)
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
                    ->helperText('Sélectionnez une ou plusieurs variantes de couleur pour cette image'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['colorVariants.primaryColor.parent']))
            ->recordTitleAttribute('s3_url')
            ->columns([
                Tables\Columns\ImageColumn::make('signed_url')
                    ->label('Image')
                    ->size(100)
                    ->square()
                    ->getStateUsing(fn ($record) => $record->signed_url)
                    ->action(
                        Tables\Actions\Action::make('view')
                            ->modalHeading('Image du produit')
                            ->modalContent(fn ($record) => view('filament.components.image-modal', [
                                'imageUrl' => $record->signed_url,
                                'alt' => 'Image du produit',
                            ]))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Fermer')
                    ),
                Tables\Columns\SelectColumn::make('position')
                    ->label('Position')
                    ->options([
                        'Front' => 'Front',
                        'Back' => 'Back',
                        'Left' => 'Left',
                        'Right' => 'Right',
                        'Top' => 'Top',
                        'Bottom' => 'Bottom',
                    ])
                    ->searchable()
                    ->sortable()
                    ->placeholder('Non défini')
                    ->selectablePlaceholder(false),
                Tables\Columns\ToggleColumn::make('neutral_background')
                    ->label('Neutral background')
                    ->sortable()
                    ->onColor('success')
                    ->offColor('gray'),
                Tables\Columns\ToggleColumn::make('is_default')
                    ->label('Par défaut')
                    ->sortable()
                    ->onColor('warning')
                    ->offColor('gray')
                    ->afterStateUpdated(function ($state, $record) {
                        if ($state && $record->s3_url) {
                            // Générer la miniature si elle n'existe pas
                            if (!$record->thumbnail_s3_url) {
                                $record->generateThumbnail();
                                $record->updateQuietly(['thumbnail_s3_url' => $record->thumbnail_s3_url]);
                            }
                        }
                    }),
                Tables\Columns\TextColumn::make('colorVariants')
                    ->label('Variantes associées')
                    ->html()
                    ->getStateUsing(function ($record) {
                        if ($record->colorVariants->isEmpty()) {
                            return null;
                        }
                        return '<div style="display: flex; flex-direction: column; gap: 0.25rem;">' . 
                            $record->colorVariants->map(function ($variant) {
                                $color = $variant->primaryColor->hex_code ?? $variant->primaryColor->parent?->hex_code ?? '#fbbf24'; // Jaune par défaut
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
                                    ->helperText('Sélectionnez une ou plusieurs variantes de couleur pour cette image'),
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
                Tables\Filters\SelectFilter::make('position')
                    ->label('Position')
                    ->options([
                        'Front' => 'Front',
                        'Back' => 'Back',
                        'Left' => 'Left',
                        'Right' => 'Right',
                        'Top' => 'Top',
                        'Bottom' => 'Bottom',
                    ])
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('neutral_background')
                    ->label('Neutral background')
                    ->placeholder('Tous')
                    ->trueLabel('Avec fond neutre')
                    ->falseLabel('Sans fond neutre'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_image')
                    ->label('Voir en grand')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading('Image du produit')
                    ->modalContent(fn ($record) => view('filament.components.image-modal', [
                        'imageUrl' => $record->signed_url,
                        'alt' => 'Image du produit',
                    ]))
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

    /**
     * Obtenir une couleur de texte contrastée (noir ou blanc) selon la couleur de fond
     */
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
