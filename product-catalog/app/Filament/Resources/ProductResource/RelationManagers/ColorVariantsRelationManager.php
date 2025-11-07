<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

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
            ->modifyQueryUsing(fn ($query) => $query->with(['primaryColor.parent', 'productImages']))
            ->recordTitleAttribute('sku')
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
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
                    ->label('Ajouter une variante'),
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
