<?php

namespace App\Filament\Resources\CsvImportResource\Pages;

use App\Filament\Resources\CsvImportResource;
use App\Models\CsvImport;
use App\Services\CsvImport\CsvAnalysisService;
use App\Services\CsvImport\CsvColumnMappingService;
use App\Services\CsvImport\MatchingService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ImportWizard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = CsvImportResource::class;
    protected static string $view = 'filament.resources.csv-import-resource.pages.import-wizard';
    protected static ?string $title = 'Assistant d\'import CSV';

    // Données du formulaire
    public ?array $data = [];
    
    // État du wizard
    public int $currentStep = 1;
    
    // Données de fichier
    public ?string $uploadedFile = null;
    public ?string $uploadedFilePath = null;
    public ?array $fileData = null;
    
    // Mapping des colonnes
    public array $columnMapping = [];
    
    // Résultat de l'analyse
    public ?array $analysisResult = null;
    
    // Mappings de valeurs manquantes
    public array $valueMappings = [];
    
    // Résultat de la validation finale
    public ?array $validationResult = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ÉTAPE 1: Configuration
                Forms\Components\Section::make('Étape 1 : Configuration de l\'import')
                    ->description('Sélectionnez le type d\'import et uploadez votre fichier CSV')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom de l\'import')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Import produits Kariban 2024'),
                        
                        Forms\Components\Select::make('type')
                            ->label('Type d\'import')
                            ->options([
                                'product' => 'Produits',
                                'manufacturer_color' => 'Couleurs fabricant',
                                'category' => 'Catégories',
                                'distributor' => 'Distributeurs',
                                'manufacturer' => 'Fabricants',
                                'stock' => 'Stocks',
                                'price' => 'Prix',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->onTypeChanged()),
                        
                        Forms\Components\Select::make('mode')
                            ->label('Mode d\'import')
                            ->options([
                                'full' => 'Import complet (avec variantes)',
                                'simple' => 'Import simple (produits uniquement)',
                            ])
                            ->default('full')
                            ->visible(fn ($get) => $get('type') === 'product'),
                        
                        Forms\Components\Select::make('strategy')
                            ->label('Stratégie')
                            ->options([
                                'create_only' => 'Créer uniquement (ignorer les existants)',
                                'update_only' => 'Mettre à jour uniquement (ignorer les nouveaux)',
                                'create_update' => 'Créer et mettre à jour',
                            ])
                            ->default('create_update')
                            ->required(),
                        
                        Forms\Components\FileUpload::make('file')
                            ->label('Fichier CSV')
                            ->helperText('Formats acceptés: CSV, TXT (séparateur auto-détecté: virgule, point-virgule, tabulation)')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->disk('local')
                            ->directory('csv-imports')
                            ->visibility('private')
                            ->required()
                            ->maxSize(10240)
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->onFileUploaded($state)),
                    ])
                    ->visible(fn () => $this->currentStep === 1)
                    ->columns(2),
                
                // ÉTAPE 2: Aperçu du fichier
                Forms\Components\Section::make('Aperçu du fichier')
                    ->schema([
                        Forms\Components\Placeholder::make('file_preview')
                            ->label('')
                            ->content(fn () => $this->renderFilePreview()),
                    ])
                    ->visible(fn () => $this->currentStep === 2 && $this->fileData !== null)
                    ->collapsible()
                    ->collapsed(false),
                
                // ÉTAPE 2: Mapping des colonnes
                Forms\Components\Section::make('Étape 2 : Mapping des colonnes')
                    ->description('Associez les colonnes de votre CSV aux champs attendus')
                    ->schema(fn () => $this->buildMappingFields())
                    ->visible(fn () => $this->currentStep === 2)
                    ->columns(2),
                
                // ÉTAPE 3: Valeurs mappées et manquantes
                Forms\Components\Section::make('Étape 3 : Analyse des valeurs')
                    ->description('Vérifiez les correspondances automatiques et gérez les valeurs manquantes')
                    ->schema([
                        // Section des valeurs automatiquement mappées
                        Forms\Components\Placeholder::make('mapped_values_info')
                            ->label('')
                            ->content(fn () => $this->renderMappedValues())
                            ->visible(fn () => !empty($this->analysisResult['mapped_values'] ?? [])),
                        
                        // Section des valeurs manquantes
                        Forms\Components\Placeholder::make('missing_values_info')
                            ->label('')
                            ->content(fn () => $this->renderMissingValues()),
                        
                        Forms\Components\Repeater::make('value_mappings')
                            ->label('Actions sur les valeurs manquantes')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Type')
                                    ->options(fn () => $this->getMissingValueTypes())
                                    ->required()
                                    ->live(),
                                
                                Forms\Components\Select::make('source_value')
                                    ->label('Valeur CSV')
                                    ->options(fn ($get) => $this->getMissingValuesForType($get('type')))
                                    ->required()
                                    ->live(),
                                
                                Forms\Components\Select::make('action')
                                    ->label('Action')
                                    ->options([
                                        'create' => 'Créer une nouvelle valeur',
                                        'map' => 'Mapper vers une valeur existante',
                                    ])
                                    ->required()
                                    ->live()
                                    ->default('map'),
                                
                                Forms\Components\Select::make('target_id')
                                    ->label('Valeur existante')
                                    ->options(fn ($get) => $this->getExistingValuesForType($get('type')))
                                    ->visible(fn ($get) => $get('action') === 'map')
                                    ->required(fn ($get) => $get('action') === 'map')
                                    ->searchable(),
                                
                                Forms\Components\TextInput::make('new_value')
                                    ->label('Nom de la nouvelle valeur')
                                    ->visible(fn ($get) => $get('action') === 'create' && $get('type') !== 'manufacturer_colors')
                                    ->required(fn ($get) => $get('action') === 'create' && $get('type') !== 'manufacturer_colors'),
                                
                                // Champs spécifiques pour la création de couleur fabricant
                                Forms\Components\TextInput::make('manufacturer_color_name')
                                    ->label('Nom de la couleur')
                                    ->helperText('Laissez vide pour utiliser le nom du CSV')
                                    ->visible(fn ($get) => $get('action') === 'create' && $get('type') === 'manufacturer_colors'),
                                
                                Forms\Components\Select::make('parent_color_id')
                                    ->label('Couleur principale parente')
                                    ->options(fn () => \App\Models\PrimaryColor::whereNull('parent_id')
                                        ->whereNull('manufacturer_id')
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray())
                                    ->searchable()
                                    ->visible(fn ($get) => $get('action') === 'create' && $get('type') === 'manufacturer_colors' && !$this->hasParentColorInContext($get('source_value')))
                                    ->required(fn ($get) => $get('action') === 'create' && $get('type') === 'manufacturer_colors' && !$this->hasParentColorInContext($get('source_value')))
                                    ->helperText('Sélectionnez la couleur principale parente pour cette couleur fabricant'),
                                
                                Forms\Components\Placeholder::make('parent_color_info')
                                    ->label('')
                                    ->content(fn ($get) => $this->getParentColorInfo($get('source_value')))
                                    ->visible(fn ($get) => $get('action') === 'create' && $get('type') === 'manufacturer_colors' && $this->hasParentColorInContext($get('source_value'))),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Ajouter un mapping')
                            ->visible(fn () => !empty($this->analysisResult['missing_values'] ?? [])),
                    ])
                    ->visible(fn () => $this->currentStep === 3),
                
                // ÉTAPE 4: Validation finale
                Forms\Components\Section::make('Étape 4 : Validation finale')
                    ->description('Vérifiez les informations avant de lancer l\'import')
                    ->schema([
                        Forms\Components\Placeholder::make('validation_summary')
                            ->label('')
                            ->content(fn () => $this->renderValidationSummary()),
                    ])
                    ->visible(fn () => $this->currentStep === 4),
            ])
            ->statePath('data');
    }

    public function nextStep(): void
    {
        if ($this->currentStep === 1) {
            if (!$this->validateStep1()) {
                return;
            }
            if (!$this->readUploadedFile()) {
                return;
            }
            $this->initializeColumnMapping();
            $this->currentStep = 2;
        } elseif ($this->currentStep === 2) {
            if (!$this->validateStep2()) {
                return;
            }
            $this->runAnalysis();
            $this->currentStep = 3;
        } elseif ($this->currentStep === 3) {
            if (!$this->validateStep3()) {
                return;
            }
            $this->runFinalValidation();
            $this->currentStep = 4;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    protected function validateStep1(): bool
    {
        $data = $this->data;
        
        if (empty($data['name'])) {
            Notification::make()->title('Erreur')->body('Veuillez saisir un nom pour l\'import.')->danger()->send();
            return false;
        }
        
        if (empty($data['type'])) {
            Notification::make()->title('Erreur')->body('Veuillez sélectionner un type d\'import.')->danger()->send();
            return false;
        }
        
        if (empty($this->uploadedFile) && empty($data['file'])) {
            Notification::make()->title('Erreur')->body('Veuillez sélectionner un fichier CSV.')->danger()->send();
            return false;
        }
        
        return true;
    }

    protected function validateStep2(): bool
    {
        $type = $this->data['type'] ?? null;
        $mode = $this->data['mode'] ?? null;
        
        if (!$type) {
            return false;
        }
        
        // Synchroniser le columnMapping avec les données du formulaire
        // Les champs Filament stockent les valeurs dans $this->data['column_mapping']
        if (!empty($this->data['column_mapping'])) {
            $this->columnMapping = array_merge($this->columnMapping, $this->data['column_mapping']);
        }
        
        $mappingService = app(CsvColumnMappingService::class);
        $expectedFields = $mappingService->getExpectedFields($type, $mode);
        $errors = $mappingService->validateMapping($this->columnMapping, $expectedFields);
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                Notification::make()->title('Erreur de mapping')->body($error)->danger()->send();
            }
            return false;
        }
        
        return true;
    }

    protected function validateStep3(): bool
    {
        $missingValues = $this->analysisResult['missing_values'] ?? [];
        
        if (empty($missingValues)) {
            return true;
        }
        
        $valueMappings = $this->data['value_mappings'] ?? [];
        $handledValues = [];
        
        foreach ($valueMappings as $mapping) {
            $type = $mapping['type'] ?? null;
            $sourceValue = $mapping['source_value'] ?? null;
            if ($type && $sourceValue) {
                $handledValues[$type][] = $sourceValue;
            }
        }
        
        $unhandled = [];
        foreach ($missingValues as $type => $values) {
            foreach ($values as $value) {
                if (!isset($handledValues[$type]) || !in_array($value, $handledValues[$type])) {
                    $unhandled[$type][] = $value;
                }
            }
        }
        
        if (!empty($unhandled)) {
            $count = array_sum(array_map('count', $unhandled));
            Notification::make()
                ->title('Valeurs non traitées')
                ->body("Il reste {$count} valeur(s) manquante(s) à traiter.")
                ->warning()
                ->send();
        }
        
        return true;
    }

    protected function onFileUploaded($state): void
    {
        if (empty($state)) {
            $this->fileData = null;
            $this->uploadedFile = null;
            $this->uploadedFilePath = null;
            return;
        }
        
        $this->fileData = null;
        $this->uploadedFilePath = null;
        
        $file = is_array($state) ? ($state[0] ?? null) : $state;
        
        if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $this->uploadedFile = $file->getFilename();
            $this->uploadedFilePath = $file->getRealPath();
        } elseif (is_string($file)) {
            $this->uploadedFile = $file;
        } else {
            $this->uploadedFile = null;
        }
    }

    protected function readUploadedFile(): bool
    {
        if ($this->uploadedFilePath && file_exists($this->uploadedFilePath) && filesize($this->uploadedFilePath) > 0) {
            $filePath = $this->uploadedFilePath;
        } elseif (!$this->uploadedFile) {
            Notification::make()
                ->title('Erreur')
                ->body('Veuillez d\'abord sélectionner un fichier CSV.')
                ->danger()
                ->send();
            return false;
        } else {
            $fileName = $this->uploadedFile;
            $baseName = basename($fileName);
            
            $possiblePaths = [
                $fileName,
                'csv-imports/' . $baseName,
                'livewire-tmp/' . $baseName,
                $baseName,
            ];
            
            $filePath = null;
            foreach ($possiblePaths as $relativePath) {
                if (Storage::disk('local')->exists($relativePath)) {
                    $fullPath = Storage::disk('local')->path($relativePath);
                    if (file_exists($fullPath) && filesize($fullPath) > 0) {
                        $filePath = $fullPath;
                        break;
                    }
                }
            }
            
            if (!$filePath) {
                $directPaths = [
                    storage_path('app/private/' . $fileName),
                    storage_path('app/private/csv-imports/' . $baseName),
                    storage_path('app/private/livewire-tmp/' . $baseName),
                    storage_path('app/' . $fileName),
                ];
                
                foreach ($directPaths as $path) {
                    if (file_exists($path) && filesize($path) > 0) {
                        $filePath = $path;
                        break;
                    }
                }
            }
            
            if (!$filePath || !file_exists($filePath)) {
                Notification::make()
                    ->title('Erreur')
                    ->body("Le fichier n'a pas été trouvé. Veuillez réessayer l'upload.")
                    ->danger()
                    ->send();
                return false;
            }
        }
        
        $fileSize = filesize($filePath);
        if ($fileSize === 0 || $fileSize === false) {
            Notification::make()
                ->title('Erreur')
                ->body('Le fichier est vide ou en cours d\'upload. Veuillez patienter.')
                ->warning()
                ->send();
            return false;
        }
        
        $this->uploadedFilePath = $filePath;
        
        try {
            $analysisService = app(CsvAnalysisService::class);
            $this->fileData = $analysisService->readFile($filePath);
            
            $delimiterName = match($this->fileData['delimiter'] ?? ',') {
                ',' => 'virgule (,)',
                ';' => 'point-virgule (;)',
                "\t" => 'tabulation',
                '|' => 'pipe (|)',
                default => $this->fileData['delimiter'] ?? ','
            };
            
            Notification::make()
                ->title('Fichier chargé')
                ->body("Fichier lu: {$this->fileData['total_rows']} lignes, " . count($this->fileData['headers']) . " colonnes. Séparateur: {$delimiterName}")
                ->success()
                ->send();
            
            return true;
                
        } catch (\Exception $e) {
            $this->fileData = null;
            Notification::make()
                ->title('Erreur de lecture')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return false;
        }
    }

    protected function onTypeChanged(): void
    {
        $this->columnMapping = [];
        $this->analysisResult = null;
    }

    protected function initializeColumnMapping(): void
    {
        if (!$this->fileData) {
            return;
        }
        
        $type = $this->data['type'] ?? null;
        $mode = $this->data['mode'] ?? null;
        
        if (!$type) {
            return;
        }
        
        $mappingService = app(CsvColumnMappingService::class);
        $expectedFields = $mappingService->getExpectedFields($type, $mode);
        $this->columnMapping = $mappingService->suggestMapping($this->fileData['headers'], $expectedFields);
    }

    protected function buildMappingFields(): array
    {
        if (!$this->fileData) {
            return [
                Forms\Components\Placeholder::make('no_file')
                    ->content('Veuillez d\'abord uploader un fichier CSV.')
            ];
        }
        
        $type = $this->data['type'] ?? null;
        $mode = $this->data['mode'] ?? null;
        
        if (!$type) {
            return [
                Forms\Components\Placeholder::make('no_type')
                    ->content('Veuillez d\'abord sélectionner un type d\'import.')
            ];
        }
        
        $mappingService = app(CsvColumnMappingService::class);
        $expectedFields = $mappingService->getExpectedFields($type, $mode);
        
        $csvOptions = ['' => '-- Non mappé --'] + array_combine($this->fileData['headers'], $this->fileData['headers']);
        
        $components = [];
        
        foreach ($expectedFields as $field => $config) {
            $components[] = Forms\Components\Select::make("column_mapping.{$field}")
                ->label($config['label'] . ($config['required'] ? ' *' : ''))
                ->options($csvOptions)
                ->default($this->columnMapping[$field] ?? null)
                ->required($config['required'])
                ->live()
                ->afterStateUpdated(function ($state) use ($field) {
                    $this->columnMapping[$field] = $state;
                });
        }
        
        return $components;
    }

    protected function runAnalysis(): void
    {
        if (!$this->fileData || !$this->uploadedFilePath) {
            return;
        }
        
        try {
            $type = $this->data['type'] ?? null;
            
            // Synchroniser le columnMapping avec les données du formulaire
            if (!empty($this->data['column_mapping'])) {
                $this->columnMapping = array_merge($this->columnMapping, $this->data['column_mapping']);
            }
            
            $analysisService = app(CsvAnalysisService::class);
            $this->analysisResult = $analysisService->analyzeWithMapping($this->uploadedFilePath, $type, $this->columnMapping);
            
            $missingCount = 0;
            foreach ($this->analysisResult['missing_values'] ?? [] as $values) {
                $missingCount += count($values);
            }
            
            if ($missingCount > 0) {
                Notification::make()
                    ->title('Analyse terminée')
                    ->body("{$missingCount} valeur(s) manquante(s) détectée(s). Veuillez les traiter.")
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title('Analyse terminée')
                    ->body('Toutes les valeurs existent déjà en base de données.')
                    ->success()
                    ->send();
            }
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur d\'analyse')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function runFinalValidation(): void
    {
        $this->validationResult = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'summary' => [
                'name' => $this->data['name'] ?? '',
                'type' => $this->data['type'] ?? '',
                'strategy' => $this->data['strategy'] ?? '',
                'total_rows' => $this->fileData['total_rows'] ?? 0,
                'mapped_columns' => count(array_filter($this->columnMapping)),
                'missing_values_handled' => count($this->data['value_mappings'] ?? []),
            ],
        ];
    }

    protected function renderFilePreview(): \Illuminate\Support\HtmlString
    {
        if (!$this->uploadedFilePath || !file_exists($this->uploadedFilePath)) {
            if (!$this->fileData) {
                return new \Illuminate\Support\HtmlString('<p class="text-gray-500">Aucun fichier chargé.</p>');
            }
            $headers = $this->fileData['headers'] ?? [];
            $preview = $this->fileData['preview'] ?? [];
            $delimiter = $this->fileData['delimiter'] ?? ',';
        } else {
            try {
                $analysisService = app(CsvAnalysisService::class);
                $freshData = $analysisService->readFile($this->uploadedFilePath);
                $headers = $freshData['headers'] ?? [];
                $preview = $freshData['preview'] ?? [];
                $delimiter = $freshData['delimiter'] ?? ',';
            } catch (\Exception $e) {
                return new \Illuminate\Support\HtmlString(
                    '<p class="text-red-500">Erreur lors de la lecture: ' . htmlspecialchars($e->getMessage()) . '</p>'
                );
            }
        }
        
        if (empty($headers)) {
            return new \Illuminate\Support\HtmlString('<p class="text-gray-500">Aucune donnée à afficher.</p>');
        }
        
        $html = '<div class="space-y-4">';
        
        $delimiterName = match($delimiter) {
            ',' => 'virgule',
            ';' => 'point-virgule',
            "\t" => 'tabulation',
            '|' => 'pipe',
            default => $delimiter
        };
        $totalRows = $this->fileData['total_rows'] ?? count($preview);
        $html .= '<div class="flex gap-4 text-sm flex-wrap">';
        $html .= '<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full">' . $totalRows . ' lignes</span>';
        $html .= '<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full">' . count($headers) . ' colonnes</span>';
        $html .= '<span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full">Séparateur: ' . $delimiterName . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="overflow-x-auto border rounded-lg max-h-96">';
        $html .= '<table class="min-w-full divide-y divide-gray-200">';
        
        $html .= '<thead class="bg-gray-100 sticky top-0"><tr>';
        $html .= '<th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">#</th>';
        foreach ($headers as $header) {
            $html .= '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead>';
        
        $html .= '<tbody class="bg-white divide-y divide-gray-200">';
        $rowNum = 1;
        $previewSlice = array_slice($preview, 0, 5);
        
        foreach ($previewSlice as $row) {
            $html .= '<tr class="hover:bg-gray-50">';
            $html .= '<td class="px-3 py-2 text-xs text-gray-500 font-medium">' . $rowNum . '</td>';
            
            if (!is_array($row)) {
                $row = (array) $row;
            }
            
            $rowKeys = array_keys($row);
            $isNumeric = isset($rowKeys[0]) && is_int($rowKeys[0]);
            
            foreach ($headers as $headerIndex => $header) {
                if (isset($row[$header])) {
                    $value = $row[$header];
                } elseif ($isNumeric && isset($row[$headerIndex])) {
                    $value = $row[$headerIndex];
                } else {
                    $value = '';
                }
                
                $value = trim((string) $value);
                $truncated = mb_strlen($value) > 40 ? mb_substr($value, 0, 40) . '...' : $value;
                $cellClass = $value === '' ? 'text-gray-300 italic' : 'text-gray-600';
                $displayValue = $value === '' ? '(vide)' : htmlspecialchars($truncated);
                
                $html .= '<td class="px-4 py-2 text-sm ' . $cellClass . ' whitespace-nowrap" title="' . htmlspecialchars($value) . '">' . $displayValue . '</td>';
            }
            $html .= '</tr>';
            $rowNum++;
        }
        $html .= '</tbody></table>';
        $html .= '</div>';
        
        if ($totalRows > 5) {
            $html .= '<p class="text-xs text-gray-500 mt-2">Affichage des 5 premières lignes sur ' . $totalRows . '</p>';
        }
        
        $html .= '</div>';
        
        return new \Illuminate\Support\HtmlString($html);
    }
    
    /**
     * Afficher les valeurs automatiquement mappées (correspondances insensibles à la casse)
     */
    protected function renderMappedValues(): \Illuminate\Support\HtmlString
    {
        if (!$this->analysisResult) {
            return new \Illuminate\Support\HtmlString('');
        }
        
        $mappedValues = $this->analysisResult['mapped_values'] ?? [];
        
        if (empty($mappedValues)) {
            return new \Illuminate\Support\HtmlString('');
        }
        
        $typeLabels = [
            'categories' => 'Catégories',
            'manufacturers' => 'Fabricants',
            'primary_colors' => 'Couleurs principales',
            'manufacturer_colors' => 'Couleurs fabricant',
            'sizes' => 'Tailles',
            'parent_categories' => 'Catégories parentes',
        ];
        
        $html = '<div class="mb-6">';
        $html .= '<div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">';
        $html .= '<h3 class="font-semibold text-blue-800 mb-3">✓ Valeurs automatiquement mappées</h3>';
        $html .= '<p class="text-sm text-blue-600 mb-4">Ces valeurs du CSV ont été automatiquement associées à des valeurs existantes en base de données (correspondance insensible à la casse).</p>';
        
        $html .= '<div class="space-y-3">';
        foreach ($mappedValues as $type => $values) {
            if (empty($values)) continue;
            
            $label = $typeLabels[$type] ?? $type;
            $html .= '<div class="border-l-4 border-blue-400 pl-3">';
            $html .= '<p class="font-medium text-blue-700 text-sm">' . $label . ' (' . count($values) . '):</p>';
            $html .= '<div class="mt-1 space-y-1">';
            
            foreach ($values as $mapping) {
                $csvValue = $mapping['csv_value'] ?? '';
                $dbValue = $mapping['db_value'] ?? '';
                
                // Pour les couleurs fabricant, afficher de manière plus lisible
                if ($type === 'manufacturer_colors' && str_contains($csvValue, '|')) {
                    [$manufacturer, $color] = explode('|', $csvValue, 2);
                    $csvDisplay = htmlspecialchars($color) . ' (' . htmlspecialchars($manufacturer) . ')';
                    $dbDisplay = htmlspecialchars($dbValue) . ' (' . htmlspecialchars($mapping['manufacturer_name'] ?? $manufacturer) . ')';
                } else {
                    $csvDisplay = htmlspecialchars($csvValue);
                    $dbDisplay = htmlspecialchars($dbValue);
                }
                
                $html .= '<div class="flex items-center gap-2 text-sm">';
                $html .= '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded">' . $csvDisplay . '</span>';
                $html .= '<span class="text-blue-400">→</span>';
                $html .= '<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded">' . $dbDisplay . '</span>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        return new \Illuminate\Support\HtmlString($html);
    }

    protected function renderMissingValues(): \Illuminate\Support\HtmlString
    {
        if (!$this->analysisResult) {
            return new \Illuminate\Support\HtmlString('<p class="text-gray-500">Analyse en cours...</p>');
        }
        
        $missingValues = $this->analysisResult['missing_values'] ?? [];
        
        if (empty($missingValues)) {
            return new \Illuminate\Support\HtmlString(
                '<div class="p-4 bg-green-50 border border-green-200 rounded-lg">' .
                '<p class="text-green-800 font-medium">✓ Toutes les valeurs existent déjà en base de données.</p>' .
                '<p class="text-green-600 text-sm mt-1">Vous pouvez passer à l\'étape suivante.</p>' .
                '</div>'
            );
        }
        
        $html = '<div class="space-y-4">';
        
        $typeLabels = [
            'categories' => 'Catégories',
            'manufacturers' => 'Fabricants',
            'primary_colors' => 'Couleurs principales',
            'manufacturer_colors' => 'Couleurs fabricant',
            'sizes' => 'Tailles',
            'parent_categories' => 'Catégories parentes',
        ];
        
        foreach ($missingValues as $type => $values) {
            if (empty($values)) continue;
            
            $label = $typeLabels[$type] ?? $type;
            $html .= '<div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">';
            $html .= '<p class="font-medium text-yellow-800">' . $label . ' manquant(e)s (' . count($values) . '):</p>';
            $html .= '<div class="flex flex-wrap gap-2 mt-2">';
            foreach ($values as $value) {
                // Pour les couleurs fabricant, afficher de manière plus lisible
                if ($type === 'manufacturer_colors' && str_contains($value, '|')) {
                    [$manufacturer, $color] = explode('|', $value, 2);
                    $displayValue = htmlspecialchars($color) . ' <span class="text-yellow-600">(' . htmlspecialchars($manufacturer) . ')</span>';
                    $html .= '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-sm rounded">' . $displayValue . '</span>';
                } else {
                    $html .= '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-sm rounded">' . htmlspecialchars($value) . '</span>';
                }
            }
            $html .= '</div>';
            
            // Ajouter une note explicative pour les couleurs fabricant
            if ($type === 'manufacturer_colors') {
                $html .= '<p class="mt-2 text-sm text-yellow-600">';
                $html .= '<strong>Note:</strong> Pour créer une couleur fabricant, assurez-vous que "primary_color_name" est renseigné dans le CSV pour définir la couleur principale parente.';
                $html .= '</p>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return new \Illuminate\Support\HtmlString($html);
    }

    protected function renderValidationSummary(): \Illuminate\Support\HtmlString
    {
        if (!$this->validationResult) {
            return new \Illuminate\Support\HtmlString('<p class="text-gray-500">Validation en cours...</p>');
        }
        
        $summary = $this->validationResult['summary'] ?? [];
        
        $typeLabels = [
            'product' => 'Produits',
            'manufacturer_color' => 'Couleurs fabricant',
            'category' => 'Catégories',
            'distributor' => 'Distributeurs',
            'manufacturer' => 'Fabricants',
            'stock' => 'Stocks',
            'price' => 'Prix',
        ];
        
        $strategyLabels = [
            'create_only' => 'Créer uniquement',
            'update_only' => 'Mettre à jour uniquement',
            'create_update' => 'Créer et mettre à jour',
        ];
        
        $html = '<div class="space-y-4">';
        
        $html .= '<div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">';
        $html .= '<h3 class="font-semibold text-blue-800 mb-3">Récapitulatif de l\'import</h3>';
        $html .= '<dl class="grid grid-cols-2 gap-4 text-sm">';
        $html .= '<div><dt class="text-gray-600">Nom</dt><dd class="font-medium text-gray-900">' . htmlspecialchars($summary['name'] ?? '') . '</dd></div>';
        $html .= '<div><dt class="text-gray-600">Type</dt><dd class="font-medium text-gray-900">' . ($typeLabels[$summary['type'] ?? ''] ?? $summary['type'] ?? '') . '</dd></div>';
        $html .= '<div><dt class="text-gray-600">Stratégie</dt><dd class="font-medium text-gray-900">' . ($strategyLabels[$summary['strategy'] ?? ''] ?? $summary['strategy'] ?? '') . '</dd></div>';
        $html .= '<div><dt class="text-gray-600">Lignes à traiter</dt><dd class="font-medium text-gray-900">' . ($summary['total_rows'] ?? 0) . '</dd></div>';
        $html .= '<div><dt class="text-gray-600">Colonnes mappées</dt><dd class="font-medium text-gray-900">' . ($summary['mapped_columns'] ?? 0) . '</dd></div>';
        $html .= '<div><dt class="text-gray-600">Valeurs créées/mappées</dt><dd class="font-medium text-gray-900">' . ($summary['missing_values_handled'] ?? 0) . '</dd></div>';
        $html .= '</dl>';
        $html .= '</div>';
        
        if ($this->validationResult['valid']) {
            $html .= '<div class="p-4 bg-green-50 border border-green-200 rounded-lg">';
            $html .= '<p class="text-green-800 font-medium">✓ Prêt pour l\'import</p>';
            $html .= '<p class="text-green-600 text-sm mt-1">Cliquez sur "Lancer l\'import" pour démarrer le traitement.</p>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return new \Illuminate\Support\HtmlString($html);
    }

    protected function getMissingValueTypes(): array
    {
        $missingValues = $this->analysisResult['missing_values'] ?? [];
        
        $typeLabels = [
            'categories' => 'Catégories',
            'manufacturers' => 'Fabricants',
            'primary_colors' => 'Couleurs principales',
            'manufacturer_colors' => 'Couleurs fabricant',
            'sizes' => 'Tailles',
            'parent_categories' => 'Catégories parentes',
        ];
        
        $options = [];
        foreach ($missingValues as $type => $values) {
            if (!empty($values)) {
                $options[$type] = $typeLabels[$type] ?? $type;
            }
        }
        
        return $options;
    }

    protected function getMissingValuesForType(?string $type): array
    {
        if (!$type) {
            return [];
        }
        
        $values = $this->analysisResult['missing_values'][$type] ?? [];
        
        // Pour les couleurs fabricant, afficher de manière plus lisible
        if ($type === 'manufacturer_colors') {
            $options = [];
            foreach ($values as $value) {
                if (str_contains($value, '|')) {
                    [$manufacturer, $color] = explode('|', $value, 2);
                    $options[$value] = "{$color} ({$manufacturer})";
                } else {
                    $options[$value] = $value;
                }
            }
            return $options;
        }
        
        return array_combine($values, $values);
    }
    
    /**
     * Vérifier si le contexte contient une couleur principale parente pour une couleur fabricant
     */
    protected function hasParentColorInContext(?string $sourceValue): bool
    {
        if (!$sourceValue) {
            return false;
        }
        
        $context = $this->analysisResult['manufacturer_color_context'][$sourceValue] ?? null;
        if (!$context || empty($context['primary_color_name'])) {
            return false;
        }
        
        // Vérifier que la couleur principale existe en base
        return \App\Models\PrimaryColor::where('name', $context['primary_color_name'])
            ->whereNull('parent_id')
            ->whereNull('manufacturer_id')
            ->exists();
    }
    
    /**
     * Obtenir les informations sur la couleur principale parente depuis le contexte
     */
    protected function getParentColorInfo(?string $sourceValue): \Illuminate\Support\HtmlString
    {
        if (!$sourceValue) {
            return new \Illuminate\Support\HtmlString('');
        }
        
        $context = $this->analysisResult['manufacturer_color_context'][$sourceValue] ?? null;
        if (!$context || empty($context['primary_color_name'])) {
            return new \Illuminate\Support\HtmlString('');
        }
        
        return new \Illuminate\Support\HtmlString(
            '<span class="text-sm text-green-600">✓ Couleur parente: <strong>' . 
            htmlspecialchars($context['primary_color_name']) . 
            '</strong> (définie dans le CSV)</span>'
        );
    }

    protected function getExistingValuesForType(?string $type): array
    {
        if (!$type) {
            return [];
        }
        
        return match($type) {
            'categories', 'parent_categories' => \App\Models\Category::pluck('name', 'id')->toArray(),
            'manufacturers' => \App\Models\Manufacturer::pluck('name', 'id')->toArray(),
            'primary_colors' => \App\Models\PrimaryColor::whereNull('parent_id')->whereNull('manufacturer_id')->pluck('name', 'id')->toArray(),
            'manufacturer_colors' => $this->getManufacturerColorsOptions(),
            'sizes' => \App\Models\Size::pluck('name', 'id')->toArray(),
            default => [],
        };
    }
    
    /**
     * Obtenir les options des couleurs fabricant avec le nom du fabricant entre parenthèses
     */
    protected function getManufacturerColorsOptions(): array
    {
        return \App\Models\PrimaryColor::whereNotNull('manufacturer_id')
            ->with('manufacturer')
            ->get()
            ->mapWithKeys(function ($color) {
                $manufacturerName = $color->manufacturer?->name ?? 'Sans fabricant';
                return [$color->id => "{$color->name} ({$manufacturerName})"];
            })
            ->toArray();
    }

    public function executeImport()
    {
        if (!$this->validationResult || !$this->validationResult['valid']) {
            Notification::make()
                ->title('Validation requise')
                ->body('Veuillez corriger les erreurs avant de lancer l\'import.')
                ->danger()
                ->send();
            return null;
        }
        
        try {
            $this->processValueMappings();
            
            // Synchroniser le columnMapping avec les données du formulaire une dernière fois
            if (!empty($this->data['column_mapping'])) {
                $this->columnMapping = array_merge($this->columnMapping, $this->data['column_mapping']);
            }
            
            // Le mode n'est applicable que pour les imports de type "product"
            $mode = null;
            if ($this->data['type'] === 'product') {
                $mode = $this->data['mode'] ?? 'full';
            }
            
            $import = CsvImport::create([
                'name' => $this->data['name'],
                'type' => $this->data['type'],
                'mode' => $mode,
                'strategy' => $this->data['strategy'] ?? 'create_update',
                'file_path' => $this->uploadedFilePath,
                'column_mapping' => $this->columnMapping,
                'value_mappings' => $this->data['value_mappings'] ?? [],
                'status' => 'matching_completed',
                'total_rows' => $this->fileData['total_rows'] ?? 0,
                'created_by' => Auth::id(),
            ]);
            
            \App\Jobs\ProcessCsvImport::dispatch($import);
            
            Notification::make()
                ->title('Import lancé')
                ->body('L\'import est en cours de traitement en arrière-plan.')
                ->success()
                ->send();
            
            return redirect()->to(CsvImportResource::getUrl('index'));
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Erreur lors du lancement de l\'import: ' . $e->getMessage())
                ->danger()
                ->send();
            return null;
        }
    }

    protected function processValueMappings(): void
    {
        $valueMappings = $this->data['value_mappings'] ?? [];
        $matchingService = app(MatchingService::class);
        
        foreach ($valueMappings as $mapping) {
            $type = $mapping['type'] ?? null;
            $sourceValue = $mapping['source_value'] ?? null;
            $action = $mapping['action'] ?? null;
            
            if (!$type || !$sourceValue || !$action) {
                continue;
            }
            
            if ($action === 'create') {
                $this->createNewValue($type, $sourceValue, $mapping);
            } elseif ($action === 'map') {
                $targetId = $mapping['target_id'] ?? null;
                if ($targetId) {
                    $matchingService->createMapping($type, $sourceValue, $targetId, Auth::id());
                }
            }
        }
    }

    protected function createNewValue(string $type, string $sourceValue, array $mappingData): void
    {
        $matchingService = app(MatchingService::class);
        $newValue = $mappingData['new_value'] ?? $sourceValue;
        
        $entity = match($type) {
            'categories', 'parent_categories' => \App\Models\Category::create(['name' => $newValue]),
            'manufacturers' => \App\Models\Manufacturer::create(['name' => $newValue]),
            'primary_colors' => \App\Models\PrimaryColor::create(['name' => $newValue]),
            'manufacturer_colors' => $this->createManufacturerColor($sourceValue, $mappingData),
            'sizes' => \App\Models\Size::create(['name' => $newValue]),
            default => null,
        };
        
        if ($entity) {
            $matchingService->createMapping($type, $sourceValue, get_class($entity), $entity->id, $entity->name, Auth::id());
        }
    }
    
    /**
     * Créer une couleur fabricant avec le contexte approprié
     * Le sourceValue est au format "manufacturer_name|color_name"
     */
    protected function createManufacturerColor(string $sourceValue, array $mappingData): ?\App\Models\PrimaryColor
    {
        // Extraire le contexte du sourceValue
        if (!str_contains($sourceValue, '|')) {
            Notification::make()
                ->title('Erreur')
                ->body('Format de couleur fabricant invalide')
                ->danger()
                ->send();
            return null;
        }
        
        [$manufacturerName, $colorName] = explode('|', $sourceValue, 2);
        
        // Trouver le fabricant
        $manufacturer = \App\Models\Manufacturer::where('name', $manufacturerName)->first();
        if (!$manufacturer) {
            Notification::make()
                ->title('Erreur')
                ->body("Fabricant '{$manufacturerName}' non trouvé. Créez-le d'abord.")
                ->danger()
                ->send();
            return null;
        }
        
        // Déterminer la couleur principale parente
        $parentColor = null;
        
        // 1. D'abord vérifier si parent_color_id a été fourni dans le formulaire
        if (!empty($mappingData['parent_color_id'])) {
            $parentColor = \App\Models\PrimaryColor::find($mappingData['parent_color_id']);
        }
        
        // 2. Sinon, chercher dans le contexte du CSV (primary_color_name)
        if (!$parentColor) {
            $context = $this->analysisResult['manufacturer_color_context'][$sourceValue] ?? null;
            $primaryColorName = $context['primary_color_name'] ?? null;
            
            if ($primaryColorName) {
                $parentColor = \App\Models\PrimaryColor::where('name', $primaryColorName)
                    ->whereNull('parent_id')
                    ->whereNull('manufacturer_id')
                    ->first();
            }
        }
        
        if (!$parentColor) {
            Notification::make()
                ->title('Erreur')
                ->body("Couleur principale parente non trouvée. Sélectionnez une couleur principale parente ou assurez-vous que 'primary_color_name' est renseigné dans le CSV.")
                ->danger()
                ->send();
            return null;
        }
        
        // Déterminer le nom de la couleur (priorité au champ du formulaire)
        $finalColorName = !empty($mappingData['manufacturer_color_name']) 
            ? $mappingData['manufacturer_color_name'] 
            : $colorName;
        
        // Créer la couleur fabricant
        return \App\Models\PrimaryColor::create([
            'name' => $finalColorName,
            'manufacturer_id' => $manufacturer->id,
            'parent_id' => $parentColor->id,
        ]);
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        
        if ($this->currentStep > 1) {
            $actions[] = Action::make('previous')
                ->label('Précédent')
                ->color('gray')
                ->action('previousStep');
        }
        
        if ($this->currentStep < 4) {
            $actions[] = Action::make('next')
                ->label('Suivant')
                ->action('nextStep');
        }
        
        if ($this->currentStep === 4) {
            $actions[] = Action::make('import')
                ->label('Lancer l\'import')
                ->color('success')
                ->action('executeImport');
        }
        
        return $actions;
    }
}
