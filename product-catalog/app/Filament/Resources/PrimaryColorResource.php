<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrimaryColorResource\Pages;
use App\Models\PrimaryColor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PrimaryColorResource extends Resource
{
    protected static ?string $model = PrimaryColor::class;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Couleurs principales';

    protected static ?string $modelLabel = 'Couleur principale';

    protected static ?string $pluralModelLabel = 'Couleurs principales';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('parent_id');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ex: Bleu, Rouge, Vert')
                    ->helperText('Nom de la couleur principale'),
                Forms\Components\ColorPicker::make('hex_code')
                    ->label('Couleur')
                    ->helperText('Sélectionnez la couleur ou saisissez le code hexadécimal'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('hex_code')
                    ->label('Couleur')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->getStateUsing(fn ($record) => 
                        $record->hex_code 
                            ? '<div class="flex items-center gap-2"><div class="w-6 h-6 rounded border border-gray-300" style="background-color: ' . $record->hex_code . '"></div><span>' . $record->hex_code . '</span></div>'
                            : '-'
                    ),
                Tables\Columns\TextColumn::make('children_count')
                    ->label('Couleurs secondaires')
                    ->counts('children')
                    ->sortable()
                    ->url(fn ($record) => 
                        $record->children_count > 0 
                            ? \App\Filament\Resources\SecondaryColorResource::getUrl('index') . '?tableFilters[parent_id][value]=' . $record->id
                            : null
                    )
                    ->openUrlInNewTab(false)
                    ->color('primary')
                    ->icon(fn ($record) => $record->children_count > 0 ? 'heroicon-o-arrow-right' : null),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrimaryColors::route('/'),
            'create' => Pages\CreatePrimaryColor::route('/create'),
            'edit' => Pages\EditPrimaryColor::route('/{record}/edit'),
        ];
    }
}
