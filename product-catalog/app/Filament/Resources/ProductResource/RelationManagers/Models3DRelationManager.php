<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

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
