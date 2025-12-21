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
use Illuminate\Support\Facades\Storage;

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
        return parent::getEloquentQuery()->whereNotNull('parent_id')->whereNotNull('manufacturer_id');
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
                Forms\Components\TextInput::make('color_sku_code')
                    ->label('Color SKU code')
                    ->maxLength(255)
                    ->placeholder('Ex: ROU-HAW, BLU-OCE')
                    ->helperText('Code SKU unique pour cette couleur fabricant'),
                Forms\Components\TextInput::make('rgb')
                    ->label('RGB')
                    ->maxLength(255)
                    ->placeholder('Ex: 255,0,0 ou rgb(255,0,0)')
                    ->helperText('Code couleur RGB'),
                Forms\Components\TextInput::make('pantone_c')
                    ->label('Pantone C')
                    ->maxLength(255)
                    ->placeholder('Ex: Pantone 186 C')
                    ->helperText('Code Pantone C'),
                Forms\Components\TextInput::make('pantone_tcx')
                    ->label('Pantone TCX')
                    ->maxLength(255)
                    ->placeholder('Ex: Pantone 18-1664 TCX')
                    ->helperText('Code Pantone TCX'),
                Forms\Components\TextInput::make('pms')
                    ->label('PMS')
                    ->maxLength(255)
                    ->placeholder('Ex: PMS 186')
                    ->helperText('Code PMS (Pantone Matching System)'),
                Forms\Components\ColorPicker::make('hex_code')
                    ->label('Couleur')
                    ->helperText('Sélectionnez la couleur ou saisissez le code hexadécimal. Si vide, hérite du code hex de la couleur principale.'),
                Forms\Components\FileUpload::make('image_s3_url')
                    ->label('Image de couleur')
                    ->disk('s3')
                    ->directory('colors')
                    ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file) {
                        return \Illuminate\Support\Str::uuid() . '.webp';
                    })
                    ->visibility('public')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        '1:1',
                    ])
                    ->maxSize(2048)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->helperText('Téléchargez une image pour remplacer la pastille de couleur (max 2MB, formats: JPG, PNG, WebP). L\'image sera redimensionnée en 100x100 en WebP.')
                    ->deletable(true)
                    ->downloadable(),
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
                Tables\Columns\TextColumn::make('color_sku_code')
                    ->label('Color SKU code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rgb')
                    ->label('RGB')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pantone_c')
                    ->label('Pantone C')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pantone_tcx')
                    ->label('Pantone TCX')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pms')
                    ->label('PMS')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                        // Vérifier d'abord l'image de la couleur fabricant, puis celle du parent, puis le hex_code
                        $imageUrl = null;
                        $imagePath = null;
                        if ($record->image_s3_url) {
                            $imagePath = $record->image_s3_url;
                        } elseif ($record->parent?->image_s3_url) {
                            $imagePath = $record->parent->image_s3_url;
                        }
                        
                        if ($imagePath) {
                            try {
                                $imageUrl = Storage::disk('s3')->temporaryUrl($imagePath, now()->addHours(24));
                            } catch (\Exception $e) {
                                $imageUrl = Storage::disk('s3')->url($imagePath);
                            }
                        }
                        
                        if ($imageUrl) {
                            $hexCode = $record->hex_code ?? $record->parent?->hex_code;
                            return '<div class="flex items-center gap-2"><img src="' . $imageUrl . '" alt="' . htmlspecialchars($record->name) . '" class="w-6 h-6 rounded border border-gray-300 object-cover" /><span>' . ($hexCode ?? '-') . '</span></div>';
                        }
                        
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

