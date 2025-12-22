<?php

namespace App\Console\Commands;

use App\Jobs\ProcessFal3DGeneration;
use App\Models\ProductModel3D;
use App\Services\FalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckFalTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fal:check-tasks {--limit=50 : Nombre maximum de tâches à vérifier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifier le statut des tâches fal.ai en cours et dispatcher les jobs de traitement';

    /**
     * Execute the console command.
     */
    public function handle(FalService $falService)
    {
        $this->info('Vérification des tâches fal.ai en cours...');

        // Récupérer les modèles 3D en statut Requested avec un meshy_task_id (réutilisé pour fal.ai request_id)
        // On peut identifier les tâches fal.ai en vérifiant si elles ont été créées récemment
        // ou en ajoutant un champ spécifique. Pour l'instant, on vérifie toutes les tâches Requested
        $limit = (int) $this->option('limit');
        $models = ProductModel3D::where('status', ProductModel3D::STATUS_REQUESTED)
            ->whereNotNull('meshy_task_id') // Réutilisé pour fal.ai request_id
            ->where('created_at', '>=', now()->subDays(7)) // Seulement les 7 derniers jours
            ->limit($limit)
            ->get();

        if ($models->isEmpty()) {
            $this->info('Aucune tâche fal.ai en cours trouvée.');
            return Command::SUCCESS;
        }

        $this->info("Vérification de {$models->count()} tâche(s)...");

        $processed = 0;
        $completed = 0;
        $failed = 0;
        $stillPending = 0;
        $notFalTasks = 0;

        foreach ($models as $model) {
            try {
                // Tenter de vérifier le statut avec fal.ai
                // Si ça échoue (pas une tâche fal.ai), on passe à la suivante
                try {
                    $requestStatus = $falService->getRequestStatus($model->meshy_task_id);
                    
                    $status = strtoupper($requestStatus['status'] ?? 'UNKNOWN');

                    if ($status === 'COMPLETED' || $status === 'SUCCEEDED') {
                        // Dispatcher le job pour télécharger et traiter le modèle
                        $bucket = config('filesystems.disks.s3.bucket');
                        
                        // Régénérer le chemin selon la nouvelle structure : /ManufacturerName/SKU/assets/SKU-variant-numero.glb
                        $product = $model->product;
                        if ($product) {
                            // Récupérer la variante associée au modèle 3D (s'il y en a une)
                            $variantSku = null;
                            $colorVariant = $model->colorVariants()->first();
                            if ($colorVariant) {
                                $variantSku = $colorVariant->sku;
                            }
                            
                            // Générer le chemin selon la nouvelle structure
                            $s3Path = $product->generateAssetPath('glb', $variantSku);
                            $outputPath = 's3://' . $bucket . '/' . $s3Path;
                        } else {
                            // Fallback si le produit n'existe plus
                            $outputPath = $model->s3_url 
                                ? ('s3://' . $bucket . '/' . $model->s3_url)
                                : ('s3://' . $bucket . '/models/ai-generated/' . $model->id . '.glb');
                        }

                        // Dispatcher le job avec l'URL du modèle si disponible
                        $modelGlbUrl = $requestStatus['model_mesh_url'] ?? null;
                        ProcessFal3DGeneration::dispatch($model->id, $modelGlbUrl, $outputPath);
                        $completed++;
                        $this->line("✓ Tâche fal.ai {$model->meshy_task_id} terminée - Job dispatché");
                    } elseif ($status === 'FAILED' || $status === 'ERROR') {
                        $model->update(['status' => ProductModel3D::STATUS_ERROR]);
                        $failed++;
                        $this->warn("✗ Tâche fal.ai {$model->meshy_task_id} échouée");
                    } else {
                        // PENDING ou IN_PROGRESS
                        $stillPending++;
                        $this->line("⏳ Tâche fal.ai {$model->meshy_task_id} en cours - Status: {$status}");
                    }

                    $processed++;
                } catch (\Exception $e) {
                    // Si l'erreur indique que ce n'est pas une tâche fal.ai (404 par exemple), on l'ignore
                    if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not found')) {
                        $notFalTasks++;
                        // C'est probablement une tâche Meshy, on l'ignore
                        continue;
                    }
                    // Sinon, on propage l'erreur
                    throw $e;
                }
            } catch (\Exception $e) {
                Log::error('CheckFalTasks - Erreur lors de la vérification', [
                    'model_id' => $model->id,
                    'request_id' => $model->meshy_task_id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Erreur pour la tâche {$model->meshy_task_id}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Résumé:");
        $this->line("  - Tâches vérifiées: {$processed}");
        $this->line("  - Terminées: {$completed}");
        $this->line("  - Échouées: {$failed}");
        $this->line("  - En cours: {$stillPending}");
        if ($notFalTasks > 0) {
            $this->line("  - Tâches non-fal.ai ignorées: {$notFalTasks}");
        }

        return Command::SUCCESS;
    }
}
