<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SecondaryColorResource\Pages;
use App\Models\PrimaryColor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SecondaryColorResource extends Resource
{
    protected static ?string $model = PrimaryColor::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationLabel = 'Couleurs fabricant';

    protected static ?string $modelLabel = 'Couleur fabricant';

    protected static ?string $pluralModelLabel = 'Couleurs fabricant';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('parent_id');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('parent_id')
                    ->label('Couleur principale')
                    ->relationship('parent', 'name', 
                        fn ($query) => $query->whereNull('parent_id')
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Sélectionnez la couleur principale à laquelle cette couleur fabricant appartient'),
                Forms\Components\Select::make('manufacturer_id')
                    ->label('Fabricant')
                    ->relationship('manufacturer', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Sélectionnez le fabricant associé à cette couleur fabricant'),
                Forms\Components\TextInput::make('name')
                    ->label('Nom de la variante')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ex: Hawaii, Ciel, Marine')
                    ->helperText('Nom de la variante uniquement (ex: "Hawaii" pour "Bleu Hawaii")'),
                Forms\Components\ColorPicker::make('hex_code')
                    ->label('Couleur')
                    ->helperText('Sélectionnez la couleur ou saisissez le code hexadécimal. Si vide, hérite du code hex de la couleur principale.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom de la variante')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Couleur principale')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Fabricant')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('hex_code')
                    ->label('Couleur')
                    ->html()
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        $hexCode = $record->hex_code ?? $record->parent?->hex_code;
                        return $hexCode 
                            ? '<div class="flex items-center gap-2"><div class="w-6 h-6 rounded border border-gray-300" style="background-color: ' . $hexCode . '"></div><span>' . $hexCode . '</span></div>'
                            : '-';
                    }),
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
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Couleur principale')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('manufacturer_id')
                    ->label('Fabricant')
                    ->relationship('manufacturer', 'name')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListSecondaryColors::route('/'),
            'create' => Pages\CreateSecondaryColor::route('/create'),
            'edit' => Pages\EditSecondaryColor::route('/{record}/edit'),
        ];
    }
}

