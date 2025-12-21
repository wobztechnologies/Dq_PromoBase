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
use Illuminate\Support\Facades\Storage;

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
        return parent::getEloquentQuery()->whereNull('parent_id')->whereNull('manufacturer_id');
    }

    public static function form(Form $form): Form
    {
        $locales = ['fr' => 'Français', 'en' => 'English', 'es' => 'Español', 'de' => 'Deutsch', 'it' => 'Italiano'];
        
        return $form
            ->schema([
                Forms\Components\Tabs::make('translations')
                    ->tabs(array_map(function ($code, $label) {
                        return Forms\Components\Tabs\Tab::make($code)
                            ->label($label)
                            ->schema([
                                Forms\Components\TextInput::make("translations.{$code}")
                                    ->label("Nom ({$label})")
                                    ->required($code === 'fr') // Français requis par défaut
                                    ->maxLength(255)
                                    ->placeholder('Ex: Bleu, Rouge, Vert'),
                            ]);
                    }, array_keys($locales), $locales))
                    ->columnSpanFull(),
                
                Forms\Components\ColorPicker::make('hex_code')
                    ->label('Couleur')
                    ->helperText('Sélectionnez la couleur ou saisissez le code hexadécimal'),
                
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
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('hex_code')
                    ->label('Couleur')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->getStateUsing(function ($record) {
                        if ($record->image_s3_url) {
                            try {
                                $imageUrl = Storage::disk('s3')->temporaryUrl($record->image_s3_url, now()->addHours(24));
                            } catch (\Exception $e) {
                                $imageUrl = Storage::disk('s3')->url($record->image_s3_url);
                            }
                            return '<div class="flex items-center gap-2"><img src="' . $imageUrl . '" alt="' . htmlspecialchars($record->name) . '" class="w-6 h-6 rounded border border-gray-300 object-cover" /><span>' . ($record->hex_code ?? '-') . '</span></div>';
                        }
                        return $record->hex_code 
                            ? '<div class="flex items-center gap-2"><div class="w-6 h-6 rounded border border-gray-300" style="background-color: ' . $record->hex_code . '"></div><span>' . $record->hex_code . '</span></div>'
                            : '-';
                    }),
                Tables\Columns\TextColumn::make('children_count')
                    ->label('Couleurs fabricant')
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
