<?php

namespace App\Jobs;

use App\Models\ProductModel3D;
use App\Services\FalService;
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

class ProcessFal3DGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $model3DId;
    public ?string $modelMeshUrl;
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
    public function __construct(string $model3DId, ?string $modelMeshUrl, string $outputPath)
    {
        $this->model3DId = $model3DId;
        $this->modelMeshUrl = $modelMeshUrl;
        $this->outputPath = $outputPath;
    }

    /**
     * Execute the job.
     */
    public function handle(FalService $falService): void
    {
        try {
            // Récupérer le modèle 3D
            $model3D = ProductModel3D::find($this->model3DId);
            
            if (!$model3D) {
                Log::error('ProcessFal3DGeneration - Modèle 3D non trouvé', [
                    'model_id' => $this->model3DId,
                ]);
                return;
            }

            // Si l'URL du modèle n'est pas fournie, vérifier le statut de la requête
            if (!$this->modelMeshUrl) {
                $requestId = $model3D->meshy_task_id; // Réutilisé pour fal.ai request_id
                
                if (!$requestId) {
                    Log::error('ProcessFal3DGeneration - Request ID manquant', [
                        'model_id' => $this->model3DId,
                    ]);
                    $model3D->update([
                        'status' => ProductModel3D::STATUS_ERROR,
                    ]);
                    return;
                }

                // Vérifier le statut de la requête fal.ai
                try {
                    $requestStatus = $falService->getRequestStatus($requestId);
                    
                    Log::info('ProcessFal3DGeneration - Statut de la requête', [
                        'request_id' => $requestId,
                        'status' => $requestStatus['status'],
                    ]);

                    // Si la requête est toujours en cours, relancer le job plus tard
                    if ($requestStatus['status'] === 'PENDING' || $requestStatus['status'] === 'IN_PROGRESS') {
                        Log::info('ProcessFal3DGeneration - Requête en cours, relance programmée', [
                            'request_id' => $requestId,
                            'status' => $requestStatus['status'],
                        ]);
                        
                        self::dispatch($this->model3DId, null, $this->outputPath)
                            ->delay(now()->addSeconds(60));
                        
                        return;
                    }

                    // Si la requête a échoué
                    if ($requestStatus['status'] === 'FAILED' || $requestStatus['status'] === 'ERROR') {
                        Log::error('ProcessFal3DGeneration - Requête échouée', [
                            'request_id' => $requestId,
                            'status' => $requestStatus['status'],
                        ]);
                        
                        $model3D->update([
                            'status' => ProductModel3D::STATUS_ERROR,
                        ]);
                        
                        return;
                    }

                    // Si la requête est terminée, récupérer l'URL du modèle
                    if ($requestStatus['status'] === 'COMPLETED' || $requestStatus['status'] === 'SUCCEEDED') {
                        $this->modelMeshUrl = $requestStatus['model_mesh_url'];
                        
                        if (!$this->modelMeshUrl) {
                            Log::error('ProcessFal3DGeneration - URL du modèle manquante dans la réponse', [
                                'request_id' => $requestId,
                                'response' => $requestStatus,
                            ]);
                            
                            $model3D->update([
                                'status' => ProductModel3D::STATUS_ERROR,
                            ]);
                            
                            return;
                        }
                    } else {
                        // Statut inconnu, relancer le job
                        Log::warning('ProcessFal3DGeneration - Statut inconnu, relance programmée', [
                            'request_id' => $requestId,
                            'status' => $requestStatus['status'],
                        ]);
                        
                        self::dispatch($this->model3DId, null, $this->outputPath)
                            ->delay(now()->addSeconds(60));
                        
                        return;
                    }
                } catch (\Exception $e) {
                    Log::error('ProcessFal3DGeneration - Erreur lors de la vérification du statut', [
                        'request_id' => $requestId,
                        'error' => $e->getMessage(),
                    ]);
                    
                    // Relancer le job après un délai en cas d'erreur temporaire
                    self::dispatch($this->model3DId, null, $this->outputPath)
                        ->delay(now()->addSeconds(60));
                    
                    return;
                }
            }

            // Télécharger le modèle depuis fal.ai
            Log::info('ProcessFal3DGeneration - Téléchargement du modèle', [
                'model_mesh_url' => $this->modelMeshUrl,
                'output_path' => $this->outputPath,
            ]);

            // Créer un répertoire temporaire pour les fichiers
            $tempDir = storage_path('app/temp/fal-' . $this->model3DId);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $tempInputFile = $tempDir . '/model.glb';
            $tempOutputFile = $tempDir . '/model-compressed.glb';

            // Télécharger le fichier depuis fal.ai
            $response = Http::timeout(300)->get($this->modelMeshUrl);
            
            if (!$response->successful()) {
                Log::error('ProcessFal3DGeneration - Échec téléchargement modèle', [
                    'model_id' => $this->model3DId,
                    'model_mesh_url' => $this->modelMeshUrl,
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
            
            Log::info('ProcessFal3DGeneration - Fichier téléchargé', [
                'model_id' => $this->model3DId,
                'input_file' => $tempInputFile,
            ]);

            // Traiter les UV maps avant compression
            $tempUVProcessedFile = $tempDir . '/model-uv-processed.glb';
            $uvService = new UVProcessingService();
            
            if ($uvService->isAvailable()) {
                Log::info('ProcessFal3DGeneration - Traitement des UV maps en cours', [
                    'model_id' => $this->model3DId,
                ]);
                
                $uvResult = $uvService->process($tempInputFile, $tempUVProcessedFile);
                
                if ($uvResult['success']) {
                    Log::info('ProcessFal3DGeneration - Traitement UV terminé', [
                        'model_id' => $this->model3DId,
                        'processed' => $uvResult['processed'] ?? false,
                        'analysis' => $uvResult['analysis'] ?? null,
                    ]);
                    
                    // Utiliser le fichier traité si le traitement a été effectué
                    if (($uvResult['processed'] ?? false) && file_exists($tempUVProcessedFile)) {
                        $tempInputFile = $tempUVProcessedFile;
                    }
                } else {
                    Log::warning('ProcessFal3DGeneration - Échec du traitement UV, utilisation du fichier original', [
                        'model_id' => $this->model3DId,
                        'error' => $uvResult['error'] ?? 'Erreur inconnue',
                    ]);
                }
            } else {
                Log::info('ProcessFal3DGeneration - Service UV non disponible, skip du traitement UV', [
                    'model_id' => $this->model3DId,
                ]);
            }
            
            Log::info('ProcessFal3DGeneration - Compression Draco en cours', [
                'model_id' => $this->model3DId,
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
                
                Log::info('ProcessFal3DGeneration - Compression Draco terminée', [
                    'model_id' => $this->model3DId,
                    'input_size' => filesize($tempInputFile),
                    'output_size' => filesize($tempOutputFile),
                    'compression_ratio' => round((1 - filesize($tempOutputFile) / filesize($tempInputFile)) * 100, 2) . '%',
                ]);
                
                // Utiliser le fichier compressé pour l'upload
                $fileToUpload = $tempOutputFile;
            } catch (\Exception $e) {
                Log::warning('ProcessFal3DGeneration - Échec compression Draco, utilisation du fichier original', [
                    'model_id' => $this->model3DId,
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
            
            Log::info('ProcessFal3DGeneration - Modèle uploadé sur S3', [
                'model_id' => $this->model3DId,
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

            Log::info('ProcessFal3DGeneration - Modèle 3D publié avec succès', [
                'model_id' => $this->model3DId,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessFal3DGeneration - Erreur lors du traitement', [
                'model_id' => $this->model3DId,
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
                Log::error('ProcessFal3DGeneration - Impossible de mettre à jour le statut', [
                    'error' => $updateException->getMessage(),
                ]);
            }

            throw $e;
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
                Log::info('ProcessFal3DGeneration - Répertoire temporaire nettoyé', [
                    'dir' => $dir,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('ProcessFal3DGeneration - Erreur lors du nettoyage', [
                'dir' => $dir,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
