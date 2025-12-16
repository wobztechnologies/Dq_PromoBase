<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

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
                                    ->maxLength(255),
                            ]);
                    }, array_keys($locales), $locales))
                    ->columnSpanFull(),
                Forms\Components\Select::make('parent_id')
                    ->label('Catégorie parente')
                    ->relationship('parent', 'name',
                        fn ($query) => $query->orderBy('path')
                    )
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        // Calculer le niveau de profondeur basé sur le path ltree
                        $depth = substr_count($record->path, '.');
                        
                        if ($depth === 0) {
                            // Niveau racine
                            return '📁 ' . $record->name;
                        }
                        
                        // Construire le préfixe avec des caractères d'arbre
                        $prefix = '';
                        $pathParts = explode('.', $record->path);
                        
                        // Pour chaque niveau, ajouter l'indentation appropriée
                        for ($i = 0; $i < $depth; $i++) {
                            if ($i === $depth - 1) {
                                // Dernier niveau : branche finale
                                $prefix .= '└─ ';
                            } else {
                                // Niveaux intermédiaires : branche continue
                                $prefix .= '│  ';
                            }
                        }
                        
                        return $prefix . $record->name;
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('Aucune (catégorie racine)')
                    ->helperText('Sélectionnez une catégorie parente pour créer une sous-catégorie. Laissez vide pour créer une catégorie racine. Le path ltree sera calculé automatiquement.'),
                Forms\Components\Placeholder::make('path_info')
                    ->label('Path (ltree)')
                    ->content(fn ($record) => $record ? $record->path ?? 'Sera calculé automatiquement' : 'Sera calculé automatiquement')
                    ->helperText('Le path ltree est calculé automatiquement en fonction de la catégorie parente sélectionnée.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->getStateUsing(function ($record) {
                        // Calculer le niveau de profondeur basé sur le path ltree
                        $depth = substr_count($record->path, '.');
                        $name = htmlspecialchars($record->name);
                        
                        // Construire l'indentation visuelle avec HTML et lignes de connexion
                        $indentation = '';
                        $icon = '';
                        $iconColor = '';
                        $bgColor = '';
                        
                        if ($depth === 0) {
                            // Catégorie racine
                            $icon = '📁';
                            $iconColor = 'text-blue-600 dark:text-blue-400';
                            $bgColor = 'bg-blue-50 dark:bg-blue-900/20';
                        } else {
                            // Sous-catégorie - créer des lignes de connexion visuelles
                            $lines = '';
                            for ($i = 0; $i < $depth; $i++) {
                                $lines .= '<span class="inline-block w-6 border-l-2 border-gray-300 dark:border-gray-600"></span>';
                            }
                            $indentation = $lines;
                            $icon = '└─';
                            $iconColor = 'text-gray-500 dark:text-gray-400';
                            // Couleur de fond alternée selon la profondeur
                            $bgColor = $depth % 2 === 1 ? 'bg-gray-50 dark:bg-gray-800/30' : '';
                        }
                        
                        return sprintf(
                            '<div class="flex items-center gap-2 %s px-2 py-1 rounded">
                                <span class="%s text-sm font-semibold">%s</span>
                                %s<span class="font-medium %s">%s</span>
                            </div>',
                            $bgColor,
                            $iconColor,
                            $icon,
                            $indentation,
                            $depth > 0 ? 'text-gray-700 dark:text-gray-300' : 'text-gray-900 dark:text-gray-100',
                            $name
                        );
                    }),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Catégorie parente')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('children_count')
                    ->label('Sous-catégories')
                    ->counts('children')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('path')
                    ->label('Path (ltree)')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->defaultSort('path', 'asc') // Trier par path pour respecter la hiérarchie
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Catégorie parente')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('root_categories')
                    ->label('Catégories racines uniquement')
                    ->query(fn ($query) => $query->whereNull('parent_id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
