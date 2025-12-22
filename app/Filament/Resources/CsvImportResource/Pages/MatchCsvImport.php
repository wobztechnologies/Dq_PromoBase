<?php

namespace App\Filament\Resources\CsvImportResource\Pages;

use App\Filament\Resources\CsvImportResource;
use App\Models\CsvImport;
use App\Services\CsvImport\CsvAnalysisService;
use App\Services\CsvImport\CsvImportService;
use App\Services\CsvImport\MatchingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class MatchCsvImport extends Page
{
    protected static string $resource = CsvImportResource::class;
    
    protected static string $view = 'filament.resources.csv-import-resource.pages.match-csv-import';
    
    public CsvImport $record;
    
    public array $unmappedValues = [];
    
    public array $mappings = [];
    
    protected $listeners = ['value-mapped' => 'handleValueMapped'];

    public function mount(int | string $record): void
    {
        $this->record = CsvImport::findOrFail($record);
        
        $this->loadUnmappedValues();
    }
    
    public function handleValueMapped(array $data): void
    {
        // Recharger les valeurs non mappées après un mapping
        $this->loadUnmappedValues();
    }
    
    protected function getMatchingService(): MatchingService
    {
        return app(MatchingService::class);
    }
    
    protected function getImportService(): CsvImportService
    {
        return app(CsvImportService::class);
    }

    protected function loadUnmappedValues(): void
    {
        try {
            // Détecter automatiquement le format CSV (séparateur et enclosure)
            $analysisService = app(CsvAnalysisService::class);
            $format = $analysisService->detectCsvFormat($this->record->file_path);
            
            $csv = Reader::createFromPath($this->record->file_path, 'r');
            $csv->setDelimiter($format['delimiter']);
            $csv->setEnclosure($format['enclosure']);
            $csv->setHeaderOffset(0);
            $records = iterator_to_array($csv->getRecords());
            
            $importService = app(\App\Services\CsvImport\CsvImportService::class);
            $handler = $importService->getHandler($this->record->type);
            $matchingValues = $handler->getMatchingValues($records);
            
            foreach ($matchingValues as $mappingType => $values) {
                $uniqueValues = array_unique($values);
                foreach ($uniqueValues as $value) {
                    $mapping = $this->getMatchingService()->findOrCreateMapping($mappingType, $value, $this->record->created_by);
                    if (!$mapping) {
                        $this->unmappedValues[$mappingType][] = $value;
                    } else {
                        $this->mappings[$mappingType][$value] = $mapping;
                    }
                }
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Erreur lors du chargement des valeurs: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function mapValue(string $mappingType, string $sourceValue, string $targetId): void
    {
        try {
            $mapping = $this->matchingService->createMapping(
                $mappingType,
                $sourceValue,
                $this->getTargetType($mappingType),
                $targetId,
                null,
                $this->record->created_by
            );
            
            // Retirer de unmappedValues et ajouter à mappings
            if (isset($this->unmappedValues[$mappingType])) {
                $this->unmappedValues[$mappingType] = array_filter(
                    $this->unmappedValues[$mappingType],
                    fn($v) => $v !== $sourceValue
                );
            }
            
            $this->mappings[$mappingType][$sourceValue] = [
                'id' => $targetId,
                'type' => $this->getTargetType($mappingType),
                'name' => $mapping->target_name,
                'mapped' => true,
            ];
            
            Notification::make()
                ->title('Mapping créé')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Erreur lors de la création du mapping: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function createEntity(string $mappingType, array $data): void
    {
        try {
            DB::beginTransaction();
            
            $entity = match($mappingType) {
                'category' => $this->createCategory($data),
                'distributor' => $this->createDistributor($data),
                'manufacturer' => $this->createManufacturer($data),
                'manufacturer_color' => $this->createManufacturerColor($data),
                'size' => $this->createSize($data),
                'primary_color' => $this->createPrimaryColor($data),
                default => throw new \Exception("Type non supporté: {$mappingType}"),
            };
            
            // Créer le mapping
            $this->mapValue($mappingType, $data['source_value'], $entity->id);
            
            DB::commit();
            
            Notification::make()
                ->title('Entité créée')
                ->body("L'entité a été créée et mappée.")
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Erreur')
                ->body('Erreur lors de la création: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function completeMatching()
    {
        $this->record->markMatchingCompleted();
        
        Notification::make()
            ->title('Matching terminé')
            ->body('Vous pouvez maintenant traiter l\'import.')
            ->success()
            ->send();
            
        return redirect()->to(CsvImportResource::getUrl('index'));
    }

    protected function getTargetType(string $mappingType): string
    {
        return match($mappingType) {
            'category' => \App\Models\Category::class,
            'distributor' => \App\Models\Distributor::class,
            'manufacturer' => \App\Models\Manufacturer::class,
            'manufacturer_color', 'primary_color' => \App\Models\PrimaryColor::class,
            'size' => \App\Models\Size::class,
            default => throw new \Exception("Type non supporté: {$mappingType}"),
        };
    }

    protected function createCategory(array $data): \App\Models\Category
    {
        $category = new \App\Models\Category();
        $category->name = $data['name'];
        if (!empty($data['parent_id'])) {
            $category->parent_id = $data['parent_id'];
        }
        $category->save();
        return $category;
    }

    protected function createDistributor(array $data): \App\Models\Distributor
    {
        $distributor = new \App\Models\Distributor();
        $distributor->name = $data['name'];
        $distributor->save();
        return $distributor;
    }

    protected function createManufacturer(array $data): \App\Models\Manufacturer
    {
        $manufacturer = new \App\Models\Manufacturer();
        $manufacturer->name = $data['name'];
        $manufacturer->save();
        return $manufacturer;
    }

    protected function createManufacturerColor(array $data): \App\Models\PrimaryColor
    {
        $color = new \App\Models\PrimaryColor();
        $color->name = $data['name'];
        $color->manufacturer_id = $data['manufacturer_id'];
        if (!empty($data['parent_id'])) {
            $color->parent_id = $data['parent_id'];
        }
        if (!empty($data['hex_code'])) {
            $color->hex_code = $data['hex_code'];
        }
        $color->save();
        return $color;
    }

    protected function createSize(array $data): \App\Models\Size
    {
        $size = new \App\Models\Size();
        $size->name = $data['name'];
        if (!empty($data['order'])) {
            $size->order = $data['order'];
        }
        $size->save();
        return $size;
    }

    protected function createPrimaryColor(array $data): \App\Models\PrimaryColor
    {
        $color = new \App\Models\PrimaryColor();
        $color->name = $data['name'];
        if (!empty($data['hex_code'])) {
            $color->hex_code = $data['hex_code'];
        }
        $color->save();
        return $color;
    }

    public function getSuggestions(string $mappingType, string $sourceValue): array
    {
        return $this->getMatchingService()->getSuggestions($mappingType, $sourceValue);
    }
}
