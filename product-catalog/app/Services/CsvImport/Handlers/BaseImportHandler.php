<?php

namespace App\Services\CsvImport\Handlers;

use App\Models\CsvImport;
use App\Services\CsvImport\CsvAnalysisService;
use App\Services\CsvImport\ImportHandlerInterface;
use App\Services\CsvImport\MappingService;
use League\Csv\Reader;
use League\Csv\Statement;

abstract class BaseImportHandler implements ImportHandlerInterface
{
    protected MappingService $mappingService;

    public function __construct(MappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    /**
     * Lire le CSV et retourner les enregistrements
     */
    protected function readCsv(CsvImport $import): array
    {
        $filePath = $import->file_path;
        
        // Détecter automatiquement le format CSV (séparateur et enclosure)
        $analysisService = app(CsvAnalysisService::class);
        $format = $analysisService->detectCsvFormat($filePath);
        
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setDelimiter($format['delimiter']);
        $csv->setEnclosure($format['enclosure']);
        $csv->setHeaderOffset(0);
        
        $stmt = Statement::create();
        $records = $stmt->process($csv);
        
        return iterator_to_array($records);
    }

    /**
     * Traiter une ligne du CSV
     */
    abstract protected function processRow(CsvImport $import, array $row, int $rowNumber): void;

    /**
     * Traiter l'import
     */
    public function process(CsvImport $import): void
    {
        $rows = $this->readCsv($import);
        $rowNumber = 1;

        foreach ($rows as $row) {
            $rowNumber++;
            try {
                $this->processRow($import, $row, $rowNumber);
                $import->incrementProcessed();
                $import->incrementSuccessful();
            } catch (\Exception $e) {
                $import->addError(
                    $rowNumber,
                    'processing',
                    $e->getMessage(),
                    $row,
                    ['exception' => get_class($e)]
                );
                $import->incrementProcessed();
            }
        }
    }

    /**
     * Obtenir une valeur mappée ou la valeur source si pas de mapping
     */
    protected function getMappedValue(CsvImport $import, string $entityType, string $sourceValue, ?string $manufacturerName = null): ?string
    {
        // Chercher un mapping existant
        $mapping = \App\Models\CsvImportMapping::findExisting($entityType, $sourceValue);
        
        if ($mapping) {
            return $mapping->target_id;
        }

        // Si pas de mapping, chercher directement dans la base
        $entity = match($entityType) {
            'category' => $this->mappingService->findCategoryByName($sourceValue),
            'distributor' => $this->mappingService->findDistributorByName($sourceValue),
            'manufacturer' => $this->mappingService->findManufacturerByName($sourceValue),
            'manufacturer_color' => $this->mappingService->findManufacturerColorByName($sourceValue, $manufacturerName ?? ''),
            'size' => $this->mappingService->findSizeByName($sourceValue),
            'primary_color' => $this->mappingService->findPrimaryColorByName($sourceValue),
            default => null,
        };

        return $entity?->id;
    }
}
