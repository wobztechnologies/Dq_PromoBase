<?php

namespace App\Jobs;

use App\Models\CsvImport;
use App\Services\CsvImport\CsvImportService;
use App\Services\CsvImport\CsvAnalysisService;
use App\Services\CsvImport\CsvRowMapperService;
use App\Services\CsvImport\Handlers\ProductImportHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

class ResumeCsvImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Nombre de tentatives
     */
    public int $tries = 1;

    /**
     * Timeout en secondes (2 heures)
     */
    public int $timeout = 7200;

    public function __construct(
        public CsvImport $import,
        public int $startFromRow
    ) {
        $this->onQueue('imports');
    }

    public function handle(
        CsvAnalysisService $analysisService,
        CsvRowMapperService $rowMapper,
        CsvImportService $importService
    ): void {
        // Désactiver le timeout PHP
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $import = $this->import;

        Log::info('ResumeCsvImport - Début de la reprise', [
            'import_id' => $import->id,
            'start_from' => $this->startFromRow,
            'total_rows' => $import->total_rows,
            'already_processed' => $import->processed_rows,
        ]);

        try {
            $filePath = $import->file_path;

            if (!file_exists($filePath)) {
                throw new \Exception("Le fichier CSV n'existe plus: {$filePath}");
            }

            // Lire le fichier CSV
            $format = $analysisService->detectCsvFormat($filePath);
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setDelimiter($format['delimiter']);
            $csv->setEnclosure($format['enclosure']);
            $csv->setHeaderOffset(0);

            $records = iterator_to_array($csv->getRecords());
            $totalRecords = count($records);

            // Obtenir le handler approprié
            $handler = $importService->getHandler($import->type);

            // Réinitialiser le tracker d'images pour les imports de produits
            if ($import->type === 'product') {
                ProductImportHandler::resetImageUrlTracker();
            }

            // Mettre à jour le status
            $import->update(['status' => 'processing']);

            $successCount = 0;
            $failCount = 0;
            $startTime = microtime(true);
            $batchSize = 100;

            foreach ($records as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // +2 car ligne 1 = headers, index 0-based

                // Sauter les lignes déjà traitées
                if ($rowNumber < $this->startFromRow) {
                    continue;
                }

                // Appliquer le mapping de colonnes
                $mappedRow = $rowMapper->mapRow($row, $import);

                // Appliquer les mappings de valeurs
                $mappedRow = $rowMapper->applyValueMappings($mappedRow, $import);

                $success = $handler->processRow($import, $mappedRow, $rowNumber);

                if ($success) {
                    $import->incrementSuccessful();
                    $successCount++;
                } else {
                    $import->incrementFailed();
                    $failCount++;
                }

                $import->incrementProcessed();

                // Log de progression toutes les N lignes
                if (($successCount + $failCount) % $batchSize === 0) {
                    $processed = $successCount + $failCount;
                    $remaining = $totalRecords - $rowNumber + 1;
                    $elapsed = microtime(true) - $startTime;
                    $avgPerRow = $elapsed / max(1, $processed);
                    $estimatedRemaining = $remaining * $avgPerRow;

                    Log::info('ResumeCsvImport - Progression', [
                        'import_id' => $import->id,
                        'processed_in_resume' => $processed,
                        'total_processed' => $import->processed_rows,
                        'total' => $totalRecords,
                        'percent' => round($import->processed_rows / $totalRecords * 100, 1) . '%',
                        'elapsed' => round($elapsed, 1) . 's',
                        'remaining' => round($estimatedRemaining, 1) . 's',
                        'successful' => $import->successful_rows,
                        'failed' => $import->failed_rows,
                    ]);

                    // Libérer la mémoire
                    gc_collect_cycles();
                }
            }

            $elapsed = microtime(true) - $startTime;

            // Marquer comme terminé
            $import->complete();

            Log::info('ResumeCsvImport - Terminé avec succès', [
                'import_id' => $import->id,
                'processed_in_resume' => $successCount + $failCount,
                'success' => $successCount,
                'failed' => $failCount,
                'total_successful' => $import->successful_rows,
                'total_failed' => $import->failed_rows,
                'elapsed' => round($elapsed, 1) . 's',
            ]);

            // Générer le rapport mis à jour
            $this->updateReport($import);

        } catch (\Exception $e) {
            Log::error('ResumeCsvImport - Erreur', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $import->addLog('error', 'Erreur lors de la reprise: ' . $e->getMessage());
            $import->fail();

            throw $e;
        }
    }

    /**
     * Mettre à jour le rapport d'import
     */
    protected function updateReport(CsvImport $import): void
    {
        try {
            $report = [];
            $report[] = "=== RAPPORT D'IMPORT (REPRISE) ===";
            $report[] = "Type: {$import->type}";
            $report[] = "Nom: {$import->name}";
            $report[] = "Date de reprise: " . now()->format('Y-m-d H:i:s');
            $report[] = "";
            $report[] = "=== STATISTIQUES FINALES ===";
            $report[] = "Total de lignes: {$import->total_rows}";
            $report[] = "Lignes traitées: {$import->processed_rows}";
            $report[] = "Succès: {$import->successful_rows}";
            $report[] = "Échecs: {$import->failed_rows}";

            $reportContent = implode("\n", $report);

            if ($import->report_path) {
                \Illuminate\Support\Facades\Storage::disk('s3')->put($import->report_path, $reportContent);
            }
        } catch (\Exception $e) {
            Log::warning('ResumeCsvImport - Impossible de mettre à jour le rapport', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gérer l'échec du job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ResumeCsvImport - Job échoué définitivement', [
            'import_id' => $this->import->id,
            'error' => $exception->getMessage(),
        ]);

        $this->import->addLog('error', 'Job de reprise échoué: ' . $exception->getMessage());
        $this->import->fail();
    }
}

