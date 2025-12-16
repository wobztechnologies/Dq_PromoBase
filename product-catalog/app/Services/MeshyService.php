<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MeshyService
{
    private ?string $apiKey = null;
    private string $baseUrl = 'https://api.meshy.ai';

    public function __construct()
    {
        $this->apiKey = Setting::get('meshy_api_key');
    }

    /**
     * Générer un modèle 3D à partir d'une ou plusieurs images
     * Choisit automatiquement entre Image to 3D (1 image) et Multi-Image to 3D (2-4 images)
     * 
     * @param array $imageUrls Tableau d'URLs d'images (1 à 4 images)
     * @param string $outputPath Chemin S3 pour sauvegarder le modèle généré
     * @param string $aiModel Modèle AI à utiliser ('meshy-5' ou 'meshy-6'), par défaut 'meshy-5'
     * @return array Réponse avec success, task_id, et status
     */
    public function generate3D(array $imageUrls, string $outputPath, string $aiModel = 'meshy-5'): array
    {
        if (!$this->apiKey) {
            Log::error('Meshy Service - API key non configurée');
            throw new \Exception('Configuration Meshy incomplète. Veuillez configurer la clé API dans les paramètres.');
        }

        $imageCount = count($imageUrls);

        if ($imageCount < 1 || $imageCount > 4) {
            throw new \Exception('Nombre d\'images invalide. 1 à 4 images requis.');
        }

        try {
            // Convertir les URLs S3 en URLs publiques/temporaires pour Meshy
            // (si ce ne sont pas déjà des URLs HTTP/HTTPS)
            $publicImageUrls = $this->convertS3UrlsToPublic($imageUrls);
            
            Log::info('Meshy Service - URLs converties pour Meshy', [
                'original_count' => count($imageUrls),
                'converted_count' => count($publicImageUrls),
                'first_url' => $publicImageUrls[0] ?? null,
            ]);

            if ($imageCount === 1) {
                // Utiliser Image to 3D endpoint
                return $this->imageTo3D($publicImageUrls[0], $outputPath, $aiModel);
            } else {
                // Utiliser Multi-Image to 3D endpoint
                return $this->multiImageTo3D($publicImageUrls, $outputPath, $aiModel);
            }
        } catch (\Exception $e) {
            Log::error('Meshy Service - Erreur lors de la génération', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Générer un modèle 3D à partir d'une seule image
     */
    private function imageTo3D(string $imageUrl, string $outputPath, string $aiModel = 'meshy-5'): array
    {
        try {
            $url = $this->baseUrl . '/openapi/v1/image-to-3d';
            
            $payload = [
                'image_url' => $imageUrl,
                'ai_model' => $aiModel,
                'enable_pbr' => false,
                'should_texture' => true,
                'symmetry_mode' => 'auto',
                'moderation' => true,
            ];

            Log::info('Meshy Service - Requête Image to 3D', [
                'url' => $url,
                'image_url' => $imageUrl,
            ]);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::error('Meshy Service - Échec Image to 3D', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Échec de la génération du modèle 3D. Status: ' . $response->status() . ' - ' . $response->body());
            }

            $data = $response->json();
            // Meshy retourne 'id' dans la réponse, pas 'result'
            $taskId = $data['id'] ?? $data['result'] ?? null;

            if (!$taskId) {
                Log::error('Meshy Service - Task ID manquant dans la réponse', ['response' => $data]);
                throw new \Exception('Task ID non reçu du serveur Meshy.');
            }

            Log::info('Meshy Service - Génération Image to 3D lancée', [
                'task_id' => $taskId,
                'output_path' => $outputPath,
            ]);

            return [
                'success' => true,
                'task_id' => $taskId,
                'status' => 'pending',
                'output_path' => $outputPath,
            ];
        } catch (\Exception $e) {
            Log::error('Meshy Service - Erreur Image to 3D', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Générer un modèle 3D à partir de plusieurs images (2-4)
     */
    private function multiImageTo3D(array $imageUrls, string $outputPath, string $aiModel = 'meshy-5'): array
    {
        try {
            $url = $this->baseUrl . '/openapi/v1/multi-image-to-3d';
            
            $payload = [
                'image_urls' => $imageUrls,
                'ai_model' => $aiModel,
                'enable_pbr' => false,
                'should_texture' => true,
                'symmetry_mode' => 'auto',
                'moderation' => true,
            ];

            Log::info('Meshy Service - Requête Multi-Image to 3D', [
                'url' => $url,
                'image_count' => count($imageUrls),
            ]);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::error('Meshy Service - Échec Multi-Image to 3D', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Échec de la génération du modèle 3D. Status: ' . $response->status() . ' - ' . $response->body());
            }

            $data = $response->json();
            // Meshy retourne 'id' dans la réponse, pas 'result'
            $taskId = $data['id'] ?? $data['result'] ?? null;

            if (!$taskId) {
                Log::error('Meshy Service - Task ID manquant dans la réponse', ['response' => $data]);
                throw new \Exception('Task ID non reçu du serveur Meshy.');
            }

            Log::info('Meshy Service - Génération Multi-Image to 3D lancée', [
                'task_id' => $taskId,
                'output_path' => $outputPath,
            ]);

            return [
                'success' => true,
                'task_id' => $taskId,
                'status' => 'pending',
                'output_path' => $outputPath,
            ];
        } catch (\Exception $e) {
            Log::error('Meshy Service - Erreur Multi-Image to 3D', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Vérifier le statut d'une tâche Meshy
     */
    public function getTaskStatus(string $taskId): array
    {
        if (!$this->apiKey) {
            throw new \Exception('Configuration Meshy incomplète.');
        }

        try {
            // L'endpoint pour vérifier le statut est le même pour image-to-3d et multi-image-to-3d
            $url = $this->baseUrl . '/openapi/v1/image-to-3d/' . $taskId;
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::error('Meshy Service - Échec récupération statut', [
                    'task_id' => $taskId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Échec de la récupération du statut. Status: ' . $response->status());
            }

            $data = $response->json();
            
            // Meshy retourne model_urls comme un dictionnaire avec des clés: glb, fbx, obj, usdz
            $modelUrls = $data['model_urls'] ?? null;
            
            return [
                'status' => strtoupper($data['status'] ?? 'unknown'),
                'progress_percentage' => $data['progress'] ?? null, // Meshy utilise 'progress', pas 'progress_percentage'
                'model_urls' => $modelUrls, // Dictionnaire avec glb, fbx, obj, usdz
                'thumbnail_url' => $data['thumbnail_url'] ?? null,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Meshy Service - Erreur récupération statut', [
                'task_id' => $taskId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Convertir les URLs S3 en URLs publiques/temporaires pour Meshy
     */
    private function convertS3UrlsToPublic(array $s3Urls): array
    {
        $publicUrls = [];
        
        foreach ($s3Urls as $s3Url) {
            // Si c'est déjà une URL HTTP/HTTPS, la garder telle quelle
            if (filter_var($s3Url, FILTER_VALIDATE_URL)) {
                $publicUrls[] = $s3Url;
                continue;
            }
            
            // Si c'est un chemin S3 (s3://bucket/path ou bucket/path)
            $s3Path = str_replace('s3://', '', $s3Url);
            $parts = explode('/', $s3Path, 2);
            
            if (count($parts) === 2) {
                $bucket = $parts[0];
                $path = $parts[1];
                
                // Générer une URL temporaire présignée valide 24h
                try {
                    $publicUrl = Storage::disk('s3')->temporaryUrl($path, now()->addHours(24));
                    $publicUrls[] = $publicUrl;
                } catch (\Exception $e) {
                    // Si échec, essayer l'URL publique directe
                    $publicUrl = Storage::disk('s3')->url($path);
                    $publicUrls[] = $publicUrl;
                }
            } else {
                // Si c'est juste un chemin relatif, utiliser Storage
                try {
                    $publicUrl = Storage::disk('s3')->temporaryUrl($s3Path, now()->addHours(24));
                    $publicUrls[] = $publicUrl;
                } catch (\Exception $e) {
                    $publicUrl = Storage::disk('s3')->url($s3Path);
                    $publicUrls[] = $publicUrl;
                }
            }
        }
        
        return $publicUrls;
    }
}
