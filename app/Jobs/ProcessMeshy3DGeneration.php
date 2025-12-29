<?php

namespace App\Jobs;

use App\Models\ProductModel3D;
use App\Services\MeshyService;
use App\Services\UVProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class ProcessMeshy3DGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $model3DId;
    public string $taskId;
    public string $outputPath;

    /**
     * Nombre de tentatives en cas d'échec
     */
    public int $tries = 3;

    /**
     * Délai entre les tentatives (en secondes)
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(string $model3DId, string $taskId, string $outputPath)
    {
        $this->model3DId = $model3DId;
        $this->taskId = $taskId;
        $this->outputPath = $outputPath;
    }

    /**
     * Execute the job.
     */
    public function handle(MeshyService $meshyService): void
    {
        try {
            // Récupérer le modèle 3D
            $model3D = ProductModel3D::find($this->model3DId);
            
            if (!$model3D) {
                Log::error('ProcessMeshy3DGeneration - Modèle 3D non trouvé', [
                    'model_id' => $this->model3DId,
                ]);
                return;
            }

            // Vérifier le statut de la tâche Meshy
            $taskStatus = $meshyService->getTaskStatus($this->taskId);
            
            Log::info('ProcessMeshy3DGeneration - Statut de la tâche', [
                'task_id' => $this->taskId,
                'status' => $taskStatus['status'],
                'progress' => $taskStatus['progress_percentage'] ?? null,
            ]);

            // Si la tâche est toujours en cours, relancer le job plus tard
            // Meshy utilise: PENDING, IN_PROGRESS, SUCCEEDED, FAILED
            if ($taskStatus['status'] === 'PENDING' || $taskStatus['status'] === 'IN_PROGRESS') {
                $progress = $taskStatus['progress_percentage'] ?? 0;
                
                // Relancer le job dans 30 secondes si < 50%, sinon 60 secondes
                $delay = $progress < 50 ? 30 : 60;
                
                Log::info('ProcessMeshy3DGeneration - Tâche en cours, relance programmée', [
                    'task_id' => $this->taskId,
                    'status' => $taskStatus['status'],
                    'progress' => $progress,
                    'delay' => $delay,
                ]);
                
                self::dispatch($this->model3DId, $this->taskId, $this->outputPath)
                    ->delay(now()->addSeconds($delay));
                
                return;
            }

            // Si la tâche a échoué
            if ($taskStatus['status'] === 'FAILED' || $taskStatus['status'] === 'ERROR') {
                Log::error('ProcessMeshy3DGeneration - Tâche échouée', [
                    'task_id' => $this->taskId,
                    'status' => $taskStatus['status'],
                ]);
                
                $model3D->update([
                    'status' => ProductModel3D::STATUS_ERROR,
                ]);
                
                return;
            }

            // Si la tâche est terminée avec succès
            if ($taskStatus['status'] === 'SUCCEEDED') {
                $modelUrls = $taskStatus['model_urls'] ?? null;
                
                if (empty($modelUrls) || !is_array($modelUrls)) {
                    Log::error('ProcessMeshy3DGeneration - Aucune URL de modèle dans la réponse', [
                        'task_id' => $this->taskId,
                        'response' => $taskStatus,
                        'model_urls' => $modelUrls,
                    ]);
                    
                    $model3D->update([
                        'status' => ProductModel3D::STATUS_ERROR,
                    ]);
                    
                    return;
                }

                // Meshy retourne model_urls comme un dictionnaire avec des clés: glb, fbx, obj, usdz
                // On veut le fichier GLB en priorité
                $modelUrl = $modelUrls['glb'] ?? null;
                $formatUsed = 'glb';
                
                // Fallback: essayer d'autres formats si glb n'est pas disponible
                if (!$modelUrl) {
                    $modelUrl = $modelUrls['fbx'] ?? null;
                    $formatUsed = $modelUrl ? 'fbx' : null;
                }
                if (!$modelUrl) {
                    $modelUrl = $modelUrls['obj'] ?? null;
                    $formatUsed = $modelUrl ? 'obj' : null;
                }
                if (!$modelUrl) {
                    $modelUrl = $modelUrls['usdz'] ?? null;
                    $formatUsed = $modelUrl ? 'usdz' : null;
                }
                
                if (!$modelUrl) {
                    Log::error('ProcessMeshy3DGeneration - Aucune URL de modèle disponible', [
                        'task_id' => $this->taskId,
                        'model_urls' => $modelUrls,
                    ]);
                    
                    $model3D->update([
                        'status' => ProductModel3D::STATUS_ERROR,
                    ]);
                    
                    return;
                }

                Log::info('ProcessMeshy3DGeneration - Téléchargement du modèle', [
                    'task_id' => $this->taskId,
                    'format' => $formatUsed,
                    'model_url' => $modelUrl,
                    'output_path' => $this->outputPath,
                ]);

                // Créer un répertoire temporaire pour les fichiers
                $tempDir = storage_path('app/temp/meshy-' . $this->taskId);
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }
                
                $tempInputFile = $tempDir . '/model.glb';
                $tempOutputFile = $tempDir . '/model-compressed.glb';

                // Télécharger le fichier depuis Meshy
                $response = Http::timeout(300)->get($modelUrl);
                
                if (!$response->successful()) {
                    Log::error('ProcessMeshy3DGeneration - Échec téléchargement modèle', [
                        'task_id' => $this->taskId,
                        'status' => $response->status(),
                    ]);
                    
                    // Nettoyer le répertoire temporaire
                    $this->cleanupTempDir($tempDir);
                    
                    $model3D->update([
                        'status' => ProductModel3D::STATUS_ERROR,
                    ]);
                    
                    return;
                }

                // Sauvegarder le fichier téléchargé temporairement
                file_put_contents($tempInputFile, $response->body());
                
                Log::info('ProcessMeshy3DGeneration - Fichier téléchargé', [
                    'task_id' => $this->taskId,
                    'input_file' => $tempInputFile,
                ]);

                // Traiter les UV maps avant compression
                $tempUVProcessedFile = $tempDir . '/model-uv-processed.glb';
                $uvService = new UVProcessingService();
                
                if ($uvService->isAvailable()) {
                    Log::info('ProcessMeshy3DGeneration - Traitement des UV maps en cours', [
                        'task_id' => $this->taskId,
                    ]);
                    
                    $uvResult = $uvService->process($tempInputFile, $tempUVProcessedFile);
                    
                    if ($uvResult['success']) {
                        Log::info('ProcessMeshy3DGeneration - Traitement UV terminé', [
                            'task_id' => $this->taskId,
                            'processed' => $uvResult['processed'] ?? false,
                            'analysis' => $uvResult['analysis'] ?? null,
                        ]);
                        
                        // Utiliser le fichier traité si le traitement a été effectué
                        if (($uvResult['processed'] ?? false) && file_exists($tempUVProcessedFile)) {
                            $tempInputFile = $tempUVProcessedFile;
                        }
                    } else {
                        Log::warning('ProcessMeshy3DGeneration - Échec du traitement UV, utilisation du fichier original', [
                            'task_id' => $this->taskId,
                            'error' => $uvResult['error'] ?? 'Erreur inconnue',
                        ]);
                    }
                } else {
                    Log::info('ProcessMeshy3DGeneration - Service UV non disponible, skip du traitement UV', [
                        'task_id' => $this->taskId,
                    ]);
                }
                
                Log::info('ProcessMeshy3DGeneration - Compression Draco en cours', [
                    'task_id' => $this->taskId,
                    'input_file' => $tempInputFile,
                    'output_file' => $tempOutputFile,
                ]);

                // Compresser le fichier avec Draco
                try {
                    $scriptPath = base_path('scripts/compress-glb-draco.js');
                    $nodePath = trim(shell_exec('which node') ?: 'node');
                    
                    // Exécuter le script Node.js
                    $command = escapeshellarg($nodePath) . ' ' . 
                               escapeshellarg($scriptPath) . ' ' . 
                               escapeshellarg($tempInputFile) . ' ' . 
                               escapeshellarg($tempOutputFile);
                    
                    $output = [];
                    $returnVar = 0;
                    exec($command . ' 2>&1', $output, $returnVar);
                    
                    if ($returnVar !== 0) {
                        throw new \Exception('Échec de la compression Draco: ' . implode("\n", $output));
                    }
                    
                    Log::info('ProcessMeshy3DGeneration - Compression Draco terminée', [
                        'task_id' => $this->taskId,
                        'input_size' => filesize($tempInputFile),
                        'output_size' => filesize($tempOutputFile),
                        'compression_ratio' => round((1 - filesize($tempOutputFile) / filesize($tempInputFile)) * 100, 2) . '%',
                    ]);
                    
                    // Utiliser le fichier compressé pour l'upload
                    $fileToUpload = $tempOutputFile;
                } catch (\Exception $e) {
                    Log::warning('ProcessMeshy3DGeneration - Échec compression Draco, utilisation du fichier original', [
                        'task_id' => $this->taskId,
                        'error' => $e->getMessage(),
                    ]);
                    
                    // En cas d'échec de compression, utiliser le fichier original
                    $fileToUpload = $tempInputFile;
                }

                // Extraire le chemin S3 du outputPath (format: s3://bucket/path ou bucket/path)
                $s3Path = $this->extractS3Path($this->outputPath);
                
                // Uploader le fichier sur S3
                $fileContent = file_get_contents($fileToUpload);
                Storage::disk('s3')->put($s3Path, $fileContent, 'public');
                
                Log::info('ProcessMeshy3DGeneration - Modèle uploadé sur S3', [
                    'task_id' => $this->taskId,
                    's3_path' => $s3Path,
                    'file_size' => strlen($fileContent),
                    'compressed' => $fileToUpload === $tempOutputFile,
                ]);
                
                // Nettoyer les fichiers temporaires
                $this->cleanupTempDir($tempDir);

                // Mettre à jour le modèle avec l'URL S3 et le statut Published
                $model3D->update([
                    's3_url' => $s3Path,
                    'status' => ProductModel3D::STATUS_PUBLISHED,
                ]);

                Log::info('ProcessMeshy3DGeneration - Modèle 3D publié avec succès', [
                    'model_id' => $this->model3DId,
                    'task_id' => $this->taskId,
                ]);
            } else {
                // Statut inconnu, relancer le job
                Log::warning('ProcessMeshy3DGeneration - Statut inconnu, relance programmée', [
                    'task_id' => $this->taskId,
                    'status' => $taskStatus['status'],
                ]);
                
                self::dispatch($this->model3DId, $this->taskId, $this->outputPath)
                    ->delay(now()->addSeconds(60));
            }
        } catch (\Exception $e) {
            Log::error('ProcessMeshy3DGeneration - Erreur lors du traitement', [
                'model_id' => $this->model3DId,
                'task_id' => $this->taskId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Nettoyer les fichiers temporaires en cas d'erreur
            if (isset($tempDir)) {
                $this->cleanupTempDir($tempDir);
            }

            // Mettre à jour le statut en Error
            try {
                $model3D = ProductModel3D::find($this->model3DId);
                if ($model3D) {
                    $model3D->update([
                        'status' => ProductModel3D::STATUS_ERROR,
                    ]);
                }
            } catch (\Exception $updateException) {
                Log::error('ProcessMeshy3DGeneration - Impossible de mettre à jour le statut', [
                    'error' => $updateException->getMessage(),
                ]);
            }

            throw $e; // Re-throw pour permettre les retries
        }
    }

    /**
     * Extraire le chemin S3 depuis un outputPath
     * Supporte les formats: s3://bucket/path, bucket/path, ou path
     */
    private function extractS3Path(string $outputPath): string
    {
        // Enlever le préfixe s3:// si présent
        $path = str_replace('s3://', '', $outputPath);
        
        // Si le chemin contient un bucket (format: bucket/path), enlever le bucket
        $parts = explode('/', $path, 2);
        if (count($parts) === 2) {
            // Le premier élément est le bucket, on garde seulement le chemin
            return $parts[1];
        }
        
        return $path;
    }

    /**
     * Nettoyer le répertoire temporaire
     */
    private function cleanupTempDir(string $dir): void
    {
        try {
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
                Log::info('ProcessMeshy3DGeneration - Répertoire temporaire nettoyé', [
                    'dir' => $dir,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('ProcessMeshy3DGeneration - Impossible de nettoyer le répertoire temporaire', [
                'dir' => $dir,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gérer l'échec du job après toutes les tentatives
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessMeshy3DGeneration - Job échoué définitivement', [
            'model_id' => $this->model3DId,
            'task_id' => $this->taskId,
            'error' => $exception->getMessage(),
        ]);

        try {
            $model3D = ProductModel3D::find($this->model3DId);
            if ($model3D) {
                $model3D->update([
                    'status' => ProductModel3D::STATUS_ERROR,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ProcessMeshy3DGeneration - Impossible de mettre à jour le statut après échec', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
