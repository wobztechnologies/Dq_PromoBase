<?php

namespace App\Services\CsvImport;

use App\Models\CsvImport;
use App\Services\CsvImport\Handlers\CategoryImportHandler;
use App\Services\CsvImport\Handlers\DistributorImportHandler;
use App\Services\CsvImport\Handlers\ManufacturerImportHandler;
use App\Services\CsvImport\Handlers\ManufacturerColorImportHandler;
use App\Services\CsvImport\Handlers\StockImportHandler;
use App\Services\CsvImport\Handlers\PriceImportHandler;
use App\Services\CsvImport\Handlers\ProductImportHandler;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use League\Csv\Reader;

class CsvImportService
{
    public function __construct(
        protected CsvValidator $validator,
        protected MatchingService $matchingService,
        protected CsvAnalysisService $analysisService
    ) {}

    /**
     * Valider un fichier CSV
     */
    public function validate(CsvImport $import): array
    {
        $filePath = $import->file_path;
        
        if (!file_exists($filePath)) {
            return ['errors' => ['Le fichier CSV n\'existe pas']];
        }
        
        try {
            // Détecter automatiquement le format CSV (séparateur et enclosure)
            $format = $this->analysisService->detectCsvFormat($filePath);
            $delimiter = $format['delimiter'];
            $enclosure = $format['enclosure'];
            
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setDelimiter($delimiter);
            $csv->setEnclosure($enclosure);
            $csv->setHeaderOffset(0);
            
            $headers = $csv->getHeader();
            $records = iterator_to_array($csv->getRecords());
            
            // Valider les en-têtes
            $headerErrors = $this->validator->validateHeaders($import->type, $headers);
            if (!empty($headerErrors)) {
                // Convertir les erreurs d'en-têtes en format structuré
                $structuredErrors = [];
                foreach ($headerErrors as $error) {
                    $structuredErrors[] = [
                        'row' => 1,
                        'field' => 'headers',
                        'message' => $error,
                        'data' => ['headers' => $headers],
                    ];
                }
                $import->markValidationFailed($structuredErrors);
                return ['errors' => $structuredErrors];
            }
            
            // Valider les données
            $dataErrors = $this->validator->validate($import->type, $records, $headers);
            
            $import->update([
                'total_rows' => count($records),
            ]);
            
            if (!empty($dataErrors)) {
                $import->markValidationFailed($dataErrors);
                return ['errors' => $dataErrors];
            }
            
            // Si validation OK, passer à l'étape de matching
            $matchingValues = $this->getMatchingValues($import->type, $records);
            
            return [
                'success' => true,
                'total_rows' => count($records),
                'matching_values' => $matchingValues,
            ];
            
        } catch (\Exception $e) {
            // Sauvegarder l'erreur de lecture
            $error = [
                [
                    'row' => 0,
                    'field' => 'file',
                    'message' => 'Erreur lors de la lecture du CSV: ' . $e->getMessage(),
                    'data' => [],
                ]
            ];
            $import->markValidationFailed($error);
            return ['errors' => $error];
        }
    }

    /**
     * Obtenir les valeurs nécessitant un matching
     */
    protected function getMatchingValues(string $type, array $records): array
    {
        $handler = $this->getHandler($type);
        return $handler->getMatchingValues($records);
    }

    /**
     * Traiter l'import
     */
    public function process(CsvImport $import): void
    {
        $import->start();
        
        // Réinitialiser le tracker d'images pour les imports de produits
        if ($import->type === 'product') {
            Handlers\ProductImportHandler::resetImageUrlTracker();
        }
        
        try {
            $filePath = $import->file_path;
            
            if (!file_exists($filePath)) {
                throw new \Exception('Le fichier CSV n\'existe pas');
            }
            
            // Détecter automatiquement le format CSV (séparateur et enclosure)
            $format = $this->analysisService->detectCsvFormat($filePath);
            $delimiter = $format['delimiter'];
            $enclosure = $format['enclosure'];
            
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setDelimiter($delimiter);
            $csv->setEnclosure($enclosure);
            $csv->setHeaderOffset(0);
            
            $records = iterator_to_array($csv->getRecords());
            $handler = $this->getHandler($import->type);
            
            // Appliquer les mappings de colonnes et valeurs si disponibles
            $rowMapper = app(\App\Services\CsvImport\CsvRowMapperService::class);
            
            foreach ($records as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // +2 car ligne 1 = headers
                
                // Appliquer le mapping de colonnes
                $mappedRow = $rowMapper->mapRow($row, $import);
                
                // Appliquer les mappings de valeurs
                $mappedRow = $rowMapper->applyValueMappings($mappedRow, $import);
                
                $success = $handler->processRow($import, $mappedRow, $rowNumber);
                
                if ($success) {
                    $import->incrementSuccessful();
                } else {
                    $import->incrementFailed();
                }
                
                $import->incrementProcessed();
            }
            
            // Archiver le fichier et générer le rapport
            $this->archiveAndGenerateReport($import);
            
            $import->complete();
            
        } catch (\Exception $e) {
            $import->addLog('error', 'Erreur lors du traitement: ' . $e->getMessage());
            $import->fail();
            throw $e;
        }
    }

    /**
     * Obtenir le handler approprié
     */
    public function getHandler(string $type): ImportHandlerInterface
    {
        return match($type) {
            'category' => app(\App\Services\CsvImport\Handlers\CategoryImportHandler::class),
            'distributor' => app(\App\Services\CsvImport\Handlers\DistributorImportHandler::class),
            'manufacturer' => app(\App\Services\CsvImport\Handlers\ManufacturerImportHandler::class),
            'manufacturer_color' => app(\App\Services\CsvImport\Handlers\ManufacturerColorImportHandler::class),
            'stock' => app(\App\Services\CsvImport\Handlers\StockImportHandler::class),
            'price' => app(\App\Services\CsvImport\Handlers\PriceImportHandler::class),
            'product' => app(\App\Services\CsvImport\Handlers\ProductImportHandler::class),
            default => throw new \Exception("Type d'import inconnu: {$type}"),
        };
    }

    /**
     * Archiver le fichier et générer le rapport
     */
    protected function archiveAndGenerateReport(CsvImport $import): void
    {
        try {
            $filePath = $import->file_path;
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = basename($filePath);
            
            // Archiver le fichier CSV dans S3
            $archivePath = "Imports/history/{$import->type}/{$timestamp}_{$filename}";
            $fileContents = File::get($filePath);
            Storage::disk('s3')->put($archivePath, $fileContents);
            
            $import->update(['s3_archive_path' => $archivePath]);
            
            // Générer le rapport
            $report = $this->generateReport($import);
            $reportPath = "Imports/history/{$import->type}/{$timestamp}_report.txt";
            Storage::disk('s3')->put($reportPath, $report);
            
            $import->update(['report_path' => $reportPath]);
            
        } catch (\Exception $e) {
            $import->addLog('warning', 'Erreur lors de l\'archivage: ' . $e->getMessage());
        }
    }

    /**
     * Générer le rapport d'import
     */
    protected function generateReport(CsvImport $import): string
    {
        $report = [];
        $report[] = "=== RAPPORT D'IMPORT ===";
        $report[] = "Type: {$import->type}";
        $report[] = "Nom: {$import->name}";
        $report[] = "Date: " . now()->format('Y-m-d H:i:s');
        $report[] = "";
        $report[] = "=== STATISTIQUES ===";
        $report[] = "Total de lignes: {$import->total_rows}";
        $report[] = "Lignes traitées: {$import->processed_rows}";
        $report[] = "Succès: {$import->successful_rows}";
        $report[] = "Échecs: {$import->failed_rows}";
        $report[] = "";
        
        // Ajouter les erreurs de validation
        if ($import->validation_errors && !empty($import->validation_errors)) {
            $report[] = "=== ERREURS DE VALIDATION ===";
            foreach ($import->validation_errors as $error) {
                $row = $error['row'] ?? 'N/A';
                $field = $error['field'] ?? 'N/A';
                $message = $error['message'] ?? 'Erreur inconnue';
                $data = $error['data'] ?? [];
                
                $report[] = "Ligne {$row} - Champ: {$field}";
                $report[] = "  Message: {$message}";
                if (!empty($data)) {
                    $report[] = "  Données: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                $report[] = "";
            }
        }
        
        // Ajouter les erreurs de traitement
        $errors = $import->logs()->errors()->get();
        if ($errors->count() > 0) {
            $report[] = "=== ERREURS DE TRAITEMENT ===";
            foreach ($errors as $error) {
                $sku = $error->sku ?? 'N/A';
                $report[] = "Ligne {$error->row_number} (SKU: {$sku}): {$error->message}";
                if ($error->data) {
                    $report[] = "  Données: " . json_encode($error->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                $report[] = "";
            }
        }
        
        return implode("\n", $report);
    }
}
