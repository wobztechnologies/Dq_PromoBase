<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CsvImportResource\Pages;
use App\Filament\Resources\CsvImportResource\Pages\MatchCsvImport;
use App\Models\CsvImport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class CsvImportResource extends Resource
{
    protected static ?string $model = CsvImport::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    
    protected static ?string $navigationLabel = 'Imports CSV';
    
    protected static ?string $navigationGroup = 'Gestion des données';
    
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuration générale')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nom de l\'import'),
                        
                        Forms\Components\Select::make('type')
                            ->required()
                            ->options([
                                'category' => 'Catégories',
                                'distributor' => 'Distributeurs',
                                'manufacturer' => 'Fabricants',
                                'manufacturer_color' => 'Couleurs Fabricant',
                                'stock' => 'Stock',
                                'price' => 'Prix',
                                'product' => 'Produits',
                            ])
                            ->reactive()
                            ->label('Type d\'import'),
                        
                        Forms\Components\Select::make('mode')
                            ->options([
                                'manufacturer' => 'Fabricant',
                                'distributor' => 'Distributeur',
                            ])
                            ->visible(fn ($get) => $get('type') === 'product')
                            ->required(fn ($get) => $get('type') === 'product')
                            ->label('Mode'),
                        
                        Forms\Components\Select::make('strategy')
                            ->required()
                            ->options([
                                'create_update' => 'Créer ou mettre à jour',
                                'update_only' => 'Mettre à jour uniquement',
                            ])
                            ->default('create_update')
                            ->label('Stratégie'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Fichier CSV')
                    ->schema([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Fichier CSV')
                            ->acceptedFileTypes(['text/csv', 'text/plain'])
                            ->disk('local')
                            ->directory('csv-imports')
                            ->visibility('private')
                            ->required()
                            ->maxSize(10240) // 10MB
                            ->helperText('Téléchargez votre fichier CSV. Un modèle est disponible ci-dessous.')
                            ->columnSpanFull(),
                        
                        Forms\Components\Placeholder::make('template_download')
                            ->label('Télécharger un modèle CSV')
                            ->content(function ($get) {
                                $type = $get('type');
                                if (!$type) {
                                    return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500">Sélectionnez un type d\'import pour télécharger le modèle</p>');
                                }
                                
                                $url = url('/csv-import/template/' . $type);
                                return new \Illuminate\Support\HtmlString(
                                    '<a href="' . $url . '" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700" download>
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Télécharger le modèle CSV pour ' . ucfirst($type) . '
                                    </a>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nom'),
                
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'category',
                        'success' => 'product',
                        'warning' => 'stock',
                        'info' => 'price',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'category' => 'Catégories',
                        'distributor' => 'Distributeurs',
                        'manufacturer' => 'Fabricants',
                        'manufacturer_color' => 'Couleurs Fabricant',
                        'stock' => 'Stock',
                        'price' => 'Prix',
                        'product' => 'Produits',
                        default => $state,
                    })
                    ->label('Type'),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending_validation',
                        'danger' => 'validation_failed',
                        'info' => 'pending_matching',
                        'primary' => 'matching_completed',
                        'secondary' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'pending_validation' => 'En attente de validation',
                        'validation_failed' => 'Validation échouée',
                        'pending_matching' => 'En attente de matching',
                        'matching_completed' => 'Matching terminé',
                        'processing' => 'En cours',
                        'completed' => 'Terminé',
                        'failed' => 'Échoué',
                        default => $state,
                    })
                    ->label('Statut'),
                
                Tables\Columns\TextColumn::make('total_rows')
                    ->label('Total lignes')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('successful_rows')
                    ->label('Succès')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('failed_rows')
                    ->label('Échecs')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Créé par')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Créé le'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'category' => 'Catégories',
                        'distributor' => 'Distributeurs',
                        'manufacturer' => 'Fabricants',
                        'manufacturer_color' => 'Couleurs Fabricant',
                        'stock' => 'Stock',
                        'price' => 'Prix',
                        'product' => 'Produits',
                    ]),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_validation' => 'En attente de validation',
                        'validation_failed' => 'Validation échouée',
                        'pending_matching' => 'En attente de matching',
                        'matching_completed' => 'Matching terminé',
                        'processing' => 'En cours',
                        'completed' => 'Terminé',
                        'failed' => 'Échoué',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('validate')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CsvImport $record) => $record->status === 'pending_validation')
                    ->action(function (CsvImport $record) {
                        try {
                            $service = app(\App\Services\CsvImport\CsvImportService::class);
                            $result = $service->validate($record);
                            
                            if (isset($result['errors']) && !empty($result['errors'])) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Erreurs de validation')
                                    ->body('Le CSV contient ' . count($result['errors']) . ' erreur(s). Consultez les détails dans l\'import.')
                                    ->danger()
                                    ->send();
                            } else {
                                $record->markPendingMatching();
                                \Filament\Notifications\Notification::make()
                                    ->title('Validation réussie')
                                    ->body('Le CSV est valide (' . ($result['total_rows'] ?? 0) . ' lignes). Vous pouvez maintenant faire le matching.')
                                    ->success()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Erreur de validation')
                                ->body('Erreur: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                
                Tables\Actions\Action::make('match')
                    ->label('Matching')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->url(fn (CsvImport $record) => Pages\MatchCsvImport::getUrl(['record' => $record]))
                    ->visible(fn (CsvImport $record) => $record->status === 'pending_matching'),
                
                Tables\Actions\Action::make('process')
                    ->label('Traiter')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CsvImport $record) => $record->status === 'matching_completed')
                    ->action(function (CsvImport $record) {
                        \App\Jobs\ProcessCsvImport::dispatch($record);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Import lancé')
                            ->body('L\'import est en cours de traitement en arrière-plan.')
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\Action::make('download_report')
                    ->label('Rapport')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (CsvImport $record) => $record->report_path ? Storage::disk('s3')->temporaryUrl($record->report_path, now()->addHours(1)) : null)
                    ->visible(fn (CsvImport $record) => !empty($record->report_path))
                    ->openUrlInNewTab(),
                
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
            'index' => Pages\ListCsvImports::route('/'),
            'create' => Pages\CreateCsvImport::route('/create'),
            'edit' => Pages\EditCsvImport::route('/{record}/edit'),
            'match' => Pages\MatchCsvImport::route('/{record}/match'),
        ];
    }
}
