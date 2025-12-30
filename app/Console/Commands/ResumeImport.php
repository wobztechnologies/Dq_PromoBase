<?php

namespace App\Console\Commands;

use App\Models\CsvImport;
use App\Services\CsvImport\CsvImportService;
use App\Services\CsvImport\CsvAnalysisService;
use App\Services\CsvImport\CsvRowMapperService;
use App\Services\CsvImport\Handlers\ProductImportHandler;
use Illuminate\Console\Command;
use League\Csv\Reader;

class ResumeImport extends Command
{
    protected $signature = 'import:resume 
                            {import_id : ID de l\'import à reprendre}
                            {--from= : Numéro de ligne à partir duquel reprendre (défaut: processed_rows + 1)}
                            {--dry-run : Afficher ce qui serait fait sans exécuter}';

    protected $description = 'Reprendre un import CSV interrompu';

    public function handle(CsvAnalysisService $analysisService, CsvRowMapperService $rowMapper): int
    {
        $importId = $this->argument('import_id');
        
        $import = CsvImport::find($importId);
        
        if (!$import) {
            $this->error("Import non trouvé: {$importId}");
            return 1;
        }

        $this->info("=== Reprise d'import ===");
        $this->info("ID: {$import->id}");
        $this->info("Nom: {$import->name}");
        $this->info("Type: {$import->type}");
        $this->info("Status: {$import->status}");
        $this->info("Total lignes: {$import->total_rows}");
        $this->info("Déjà traitées: {$import->processed_rows}");
        $this->info("Succès: {$import->successful_rows}");
        $this->info("Échecs: {$import->failed_rows}");
        $this->newLine();

        // Vérifier que le fichier existe
        $filePath = $import->file_path;
        if (!file_exists($filePath)) {
            $this->error("Le fichier CSV n'existe plus: {$filePath}");
            $this->info("Vous pouvez re-uploader le fichier ou utiliser l'archive S3 si disponible.");
            return 1;
        }

        // Déterminer la ligne de départ
        $startFrom = $this->option('from') 
            ? (int) $this->option('from') 
            : $import->processed_rows + 1;

        $this->info("Reprise à partir de la ligne: {$startFrom}");
        
        if ($this->option('dry-run')) {
            $this->warn("Mode dry-run: aucune modification ne sera effectuée.");
            return 0;
        }

        if (!$this->confirm("Voulez-vous reprendre l'import à partir de la ligne {$startFrom}?")) {
            $this->info("Annulé.");
            return 0;
        }

        // Désactiver le timeout
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        try {
            // Lire le fichier CSV
            $format = $analysisService->detectCsvFormat($filePath);
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setDelimiter($format['delimiter']);
            $csv->setEnclosure($format['enclosure']);
            $csv->setHeaderOffset(0);
            
            $records = iterator_to_array($csv->getRecords());
            $totalRecords = count($records);
            
            $this->info("Total lignes dans le fichier: {$totalRecords}");
            
            // Calculer combien de lignes restent
            $remainingCount = $totalRecords - $startFrom + 1;
            $this->info("Lignes restantes à traiter: {$remainingCount}");
            $this->newLine();

            // Obtenir le handler approprié
            $importService = app(CsvImportService::class);
            $handler = $importService->getHandler($import->type);

            // Réinitialiser le tracker d'images pour les imports de produits
            if ($import->type === 'product') {
                ProductImportHandler::resetImageUrlTracker();
            }

            // Mettre à jour le status
            $import->update(['status' => 'processing']);

            $progressBar = $this->output->createProgressBar($remainingCount);
            $progressBar->start();

            $successCount = 0;
            $failCount = 0;
            $startTime = microtime(true);

            foreach ($records as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // +2 car ligne 1 = headers, index 0-based
                
                // Sauter les lignes déjà traitées
                if ($rowNumber < $startFrom) {
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
                $progressBar->advance();

                // Libérer la mémoire toutes les 100 lignes
                if (($rowIndex + 1) % 100 === 0) {
                    gc_collect_cycles();
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            $elapsed = microtime(true) - $startTime;
            
            // Marquer comme terminé
            $import->complete();

            $this->info("=== Reprise terminée ===");
            $this->info("Lignes traitées: " . ($successCount + $failCount));
            $this->info("Succès: {$successCount}");
            $this->info("Échecs: {$failCount}");
            $this->info("Temps: " . round($elapsed, 1) . "s");
            $this->newLine();
            
            $this->info("=== Total import ===");
            $import->refresh();
            $this->info("Total traitées: {$import->processed_rows}/{$import->total_rows}");
            $this->info("Total succès: {$import->successful_rows}");
            $this->info("Total échecs: {$import->failed_rows}");

            return 0;

        } catch (\Exception $e) {
            $this->error("Erreur: " . $e->getMessage());
            $import->addLog('error', 'Erreur lors de la reprise: ' . $e->getMessage());
            return 1;
        }
    }
}

