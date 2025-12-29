<?php

namespace App\Jobs;

use App\Models\ProductModel3D;
use App\Services\UVProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

/**
 * Job pour traiter les modèles 3D uploadés manuellement
 * 
 * Ce job :
 * 1. Télécharge le modèle depuis S3
 * 2. Traite les UV maps avec xatlas
 * 3. Compresse avec Draco
 * 4. Re-upload sur S3
 */
class ProcessUploadedModel3D implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $model3DId;

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
    public function __construct(string $model3DId)
    {
        $this->model3DId = $model3DId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Récupérer le modèle 3D
            $model3D = ProductModel3D::find($this->model3DId);
            
            if (!$model3D) {
                Log::error('ProcessUploadedModel3D - Modèle 3D non trouvé', [
                    'model_id' => $this->model3DId,
                ]);
                return;
            }

            // Vérifier que le modèle a un fichier S3
            if (!$model3D->s3_url) {
                Log::warning('ProcessUploadedModel3D - Pas de fichier S3', [
                    'model_id' => $this->model3DId,
                ]);
                return;
            }

            Log::info('ProcessUploadedModel3D - Début du traitement', [
                'model_id' => $this->model3DId,
                's3_url' => $model3D->s3_url,
            ]);

            // Créer un répertoire temporaire
            $tempDir = storage_path('app/temp/upload-' . $this->model3DId);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $tempInputFile = $tempDir . '/model-original.glb';
            $tempUVProcessedFile = $tempDir . '/model-uv-processed.glb';
            $tempOutputFile = $tempDir . '/model-compressed.glb';

            // Télécharger le fichier depuis S3
            $fileContent = Storage::disk('s3')->get($model3D->s3_url);
            
            if (!$fileContent) {
                Log::error('ProcessUploadedModel3D - Impossible de télécharger le fichier S3', [
                    'model_id' => $this->model3DId,
                    's3_url' => $model3D->s3_url,
                ]);
                $this->cleanupTempDir($tempDir);
                return;
            }

            file_put_contents($tempInputFile, $fileContent);
            $originalSize = strlen($fileContent);
            
            Log::info('ProcessUploadedModel3D - Fichier téléchargé', [
                'model_id' => $this->model3DId,
                'size' => $originalSize,
            ]);

            // Traiter les UV maps
            $uvService = new UVProcessingService();
            $fileToProcess = $tempInputFile;
            
            if ($uvService->isAvailable()) {
                Log::info('ProcessUploadedModel3D - Traitement des UV maps en cours', [
                    'model_id' => $this->model3DId,
                ]);
                
                $uvResult = $uvService->process($tempInputFile, $tempUVProcessedFile);
                
                if ($uvResult['success']) {
                    Log::info('ProcessUploadedModel3D - Traitement UV terminé', [
                        'model_id' => $this->model3DId,
                        'processed' => $uvResult['processed'] ?? false,
                        'analysis' => $uvResult['analysis'] ?? null,
                    ]);
                    
                    // Utiliser le fichier traité si le traitement a été effectué
                    if (($uvResult['processed'] ?? false) && file_exists($tempUVProcessedFile)) {
                        $fileToProcess = $tempUVProcessedFile;
                    }
                } else {
                    Log::warning('ProcessUploadedModel3D - Échec du traitement UV, utilisation du fichier original', [
                        'model_id' => $this->model3DId,
                        'error' => $uvResult['error'] ?? 'Erreur inconnue',
                    ]);
                }
            } else {
                Log::info('ProcessUploadedModel3D - Service UV non disponible', [
                    'model_id' => $this->model3DId,
                ]);
            }

            // Compresser avec Draco
            $fileToUpload = $fileToProcess;
            
            try {
                $scriptPath = base_path('scripts/compress-glb-draco.js');
                $nodePath = trim(shell_exec('which node') ?: 'node');
                
                $command = escapeshellarg($nodePath) . ' ' . 
                           escapeshellarg($scriptPath) . ' ' . 
                           escapeshellarg($fileToProcess) . ' ' . 
                           escapeshellarg($tempOutputFile);
                
                $output = [];
                $returnVar = 0;
                exec($command . ' 2>&1', $output, $returnVar);
                
                if ($returnVar !== 0) {
                    throw new \Exception('Échec de la compression Draco: ' . implode("\n", $output));
                }
                
                Log::info('ProcessUploadedModel3D - Compression Draco terminée', [
                    'model_id' => $this->model3DId,
                    'input_size' => filesize($fileToProcess),
                    'output_size' => filesize($tempOutputFile),
                    'compression_ratio' => round((1 - filesize($tempOutputFile) / filesize($fileToProcess)) * 100, 2) . '%',
                ]);
                
                $fileToUpload = $tempOutputFile;
            } catch (\Exception $e) {
                Log::warning('ProcessUploadedModel3D - Échec compression Draco, utilisation du fichier précédent', [
                    'model_id' => $this->model3DId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Re-uploader sur S3 (même chemin)
            $finalContent = file_get_contents($fileToUpload);
            Storage::disk('s3')->put($model3D->s3_url, $finalContent, 'public');
            
            $finalSize = strlen($finalContent);
            
            Log::info('ProcessUploadedModel3D - Modèle re-uploadé sur S3', [
                'model_id' => $this->model3DId,
                's3_url' => $model3D->s3_url,
                'original_size' => $originalSize,
                'final_size' => $finalSize,
                'total_compression' => round((1 - $finalSize / $originalSize) * 100, 2) . '%',
            ]);
            
            // Nettoyer les fichiers temporaires
            $this->cleanupTempDir($tempDir);

            Log::info('ProcessUploadedModel3D - Traitement terminé avec succès', [
                'model_id' => $this->model3DId,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessUploadedModel3D - Erreur lors du traitement', [
                'model_id' => $this->model3DId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Nettoyer les fichiers temporaires en cas d'erreur
            if (isset($tempDir)) {
                $this->cleanupTempDir($tempDir);
            }

            throw $e;
        }
    }

    /**
     * Nettoyer le répertoire temporaire
     */
    private function cleanupTempDir(string $dir): void
    {
        try {
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
                Log::info('ProcessUploadedModel3D - Répertoire temporaire nettoyé', [
                    'dir' => $dir,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('ProcessUploadedModel3D - Erreur lors du nettoyage', [
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
        Log::error('ProcessUploadedModel3D - Job échoué définitivement', [
            'model_id' => $this->model3DId,
            'error' => $exception->getMessage(),
        ]);
    }
}

