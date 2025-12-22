<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMeshy3DGeneration;
use App\Models\ProductModel3D;
use App\Services\MeshyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckMeshyTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meshy:check-tasks {--limit=50 : Nombre maximum de tâches à vérifier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifier le statut des tâches Meshy en cours et dispatcher les jobs de traitement';

    /**
     * Execute the console command.
     */
    public function handle(MeshyService $meshyService)
    {
        $this->info('Vérification des tâches Meshy en cours...');

        // Récupérer les modèles 3D en statut Requested avec un meshy_task_id
        $limit = (int) $this->option('limit');
        $models = ProductModel3D::where('status', ProductModel3D::STATUS_REQUESTED)
            ->whereNotNull('meshy_task_id')
            ->where('created_at', '>=', now()->subDays(7)) // Seulement les 7 derniers jours
            ->limit($limit)
            ->get();

        if ($models->isEmpty()) {
            $this->info('Aucune tâche Meshy en cours trouvée.');
            return Command::SUCCESS;
        }

        $this->info("Vérification de {$models->count()} tâche(s)...");

        $processed = 0;
        $completed = 0;
        $failed = 0;
        $stillPending = 0;

        foreach ($models as $model) {
            try {
                $taskStatus = $meshyService->getTaskStatus($model->meshy_task_id);

                $status = strtoupper($taskStatus['status'] ?? 'UNKNOWN');

                if ($status === 'SUCCEEDED') {
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

                    ProcessMeshy3DGeneration::dispatch($model->id, $model->meshy_task_id, $outputPath);
                    $completed++;
                    $this->line("✓ Tâche {$model->meshy_task_id} terminée - Job dispatché");
                } elseif ($status === 'FAILED' || $status === 'ERROR') {
                    $model->update(['status' => ProductModel3D::STATUS_ERROR]);
                    $failed++;
                    $this->warn("✗ Tâche {$model->meshy_task_id} échouée");
                } else {
                    // PENDING ou IN_PROGRESS
                    $stillPending++;
                    $progress = $taskStatus['progress_percentage'] ?? 0;
                    $this->line("⏳ Tâche {$model->meshy_task_id} en cours - Status: {$status} ({$progress}%)");
                }

                $processed++;
            } catch (\Exception $e) {
                Log::error('CheckMeshyTasks - Erreur lors de la vérification', [
                    'model_id' => $model->id,
                    'task_id' => $model->meshy_task_id,
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

        return Command::SUCCESS;
    }
}
