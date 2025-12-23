<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FalService
{
    private ?string $apiKey = null;
    private string $baseUrl = 'https://queue.fal.run';

    public function __construct()
    {
        $this->apiKey = Setting::get('fal_api_key');
    }

    /**
     * Générer un modèle 3D à partir de 3 images (Front, Back, Left)
     * Utilise le modèle Hunyuan3D v2 Multi-View (fal-ai/hunyuan3d/v2/multi-view)
     * Documentation: https://fal.ai/models/fal-ai/hunyuan3d/v2/multi-view
     * 
     * @param string $frontImageUrl URL de l'image Front
     * @param string $backImageUrl URL de l'image Back
     * @param string $sideImageUrl URL de l'image Left
     * @param string $outputPath Chemin S3 pour sauvegarder le modèle généré (non utilisé par fal.ai directement)
     * @return array Réponse avec success, request_id, et status
     */
    public function generate3D(string $frontImageUrl, string $backImageUrl, string $sideImageUrl, string $outputPath): array
    {
        if (!$this->apiKey) {
            Log::error('Fal Service - API key non configurée');
            throw new \Exception('Configuration fal.ai incomplète. Veuillez configurer la clé API dans les paramètres.');
        }

        try {
            // Utiliser l'API de queue de fal.ai
            // Modèle Hunyuan3D v2 Multi-View pour génération haute qualité à partir de 3 vues
            // Format: https://queue.fal.run/fal-ai/hunyuan3d/v2/multi-view
            $url = $this->baseUrl . '/fal-ai/hunyuan3d/v2/multi-view';
            
            // Le payload pour l'API Queue doit être directement dans le body
            // Le modèle multi-view requiert: front_image_url, back_image_url, left_image_url
            $payload = [
                'front_image_url' => $frontImageUrl,
                'back_image_url' => $backImageUrl,
                'left_image_url' => $sideImageUrl,
                'textured_mesh' => true, // Toujours activer textured mesh pour obtenir un modèle avec textures
            ];

            Log::info('Fal Service - Requête Hunyuan3D v2 Multi-View to 3D', [
                'url' => $url,
                'front_image_url' => $frontImageUrl,
                'back_image_url' => $backImageUrl,
                'left_image_url' => $sideImageUrl,
                'textured_mesh' => true,
            ]);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Key ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);
            
            // Log de la réponse pour debug
            Log::info('Fal Service - Réponse de fal.ai', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if (!$response->successful()) {
                Log::error('Fal Service - Échec Hunyuan3D v2 Multi-View', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Échec de la génération du modèle 3D (Multi-View). Status: ' . $response->status() . ' - ' . $response->body());
            }

            $data = $response->json();
            
            // fal.ai Queue API retourne request_id et des URLs pour status/response
            $requestId = $data['request_id'] ?? null;
            $statusUrl = $data['status_url'] ?? null;
            $responseUrl = $data['response_url'] ?? null;
            
            if (!$requestId) {
                Log::error('Fal Service - Request ID manquant dans la réponse', [
                    'response' => $data,
                ]);
                throw new \Exception('Request ID non reçu du serveur fal.ai.');
            }

            Log::info('Fal Service - Génération Hunyuan3D v2 Multi-View lancée', [
                'request_id' => $requestId,
                'status_url' => $statusUrl,
                'response_url' => $responseUrl,
                'output_path' => $outputPath,
            ]);

            return [
                'success' => true,
                'request_id' => $requestId,
                'status_url' => $statusUrl,
                'response_url' => $responseUrl,
                'status' => 'pending',
                'model_mesh_url' => null, // Sera récupéré via getRequestStatus
                'output_path' => $outputPath,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Fal Service - Erreur Hunyuan3D v2 Multi-View', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Générer un modèle 3D à partir d'une seule image (Front)
     * Utilise le modèle Hunyuan3D v2 (fal-ai/hunyuan3d/v2)
     * Documentation: https://fal.ai/models/fal-ai/hunyuan3d/v2
     * 
     * @param string $imageUrl URL de l'image Front
     * @param string $outputPath Chemin S3 pour sauvegarder le modèle généré (non utilisé par fal.ai directement)
     * @return array Réponse avec success, request_id, et status
     */
    public function generate3DFromSingleImage(string $imageUrl, string $outputPath): array
    {
        if (!$this->apiKey) {
            Log::error('Fal Service - API key non configurée');
            throw new \Exception('Configuration fal.ai incomplète. Veuillez configurer la clé API dans les paramètres.');
        }

        try {
            // Utiliser l'API de queue de fal.ai
            // Modèle Hunyuan3D v2 standard pour génération mono-image haute qualité
            // Format: https://queue.fal.run/fal-ai/hunyuan3d/v2
            $url = $this->baseUrl . '/fal-ai/hunyuan3d/v2';
            
            // Le payload pour l'API Queue doit être directement dans le body
            $payload = [
                'image_url' => $imageUrl,
                'textured_mesh' => true, // Toujours activer textured mesh pour obtenir un modèle avec textures
            ];

            Log::info('Fal Service - Requête Hunyuan3D v2 Image to 3D', [
                'url' => $url,
                'image_url' => $imageUrl,
                'textured_mesh' => true,
            ]);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Key ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);
            
            // Log de la réponse pour debug
            Log::info('Fal Service - Réponse de fal.ai (mono)', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if (!$response->successful()) {
                Log::error('Fal Service - Échec Image to 3D (mono)', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Échec de la génération du modèle 3D. Status: ' . $response->status() . ' - ' . $response->body());
            }

            $data = $response->json();
            
            // fal.ai Queue API retourne request_id et des URLs pour status/response
            $requestId = $data['request_id'] ?? null;
            $statusUrl = $data['status_url'] ?? null;
            $responseUrl = $data['response_url'] ?? null;
            
            if (!$requestId) {
                Log::error('Fal Service - Request ID manquant dans la réponse (mono)', [
                    'response' => $data,
                ]);
                throw new \Exception('Request ID non reçu du serveur fal.ai.');
            }

            Log::info('Fal Service - Génération Image to 3D (mono) lancée', [
                'request_id' => $requestId,
                'status_url' => $statusUrl,
                'response_url' => $responseUrl,
                'output_path' => $outputPath,
            ]);

            return [
                'success' => true,
                'request_id' => $requestId,
                'status_url' => $statusUrl,
                'response_url' => $responseUrl,
                'status' => 'pending',
                'model_mesh_url' => null, // Sera récupéré via getRequestStatus
                'output_path' => $outputPath,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Fal Service - Erreur Image to 3D (mono)', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Vérifier le statut d'une requête fal.ai
     */
    public function getRequestStatus(string $requestId): array
    {
        if (!$this->apiKey) {
            throw new \Exception('Configuration fal.ai incomplète.');
        }

        try {
            // Construire l'URL de statut depuis le request_id
            // D'après les logs, fal.ai retourne des URLs comme: /fal-ai/hunyuan3d/requests/{request_id}
            // Sans le chemin /v2/multi-view/turbo dans l'URL de requête
            $url = $this->baseUrl . '/fal-ai/hunyuan3d/requests/' . $requestId;
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Key ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->get($url); // GET pour le statut

            if (!$response->successful()) {
                Log::error('Fal Service - Échec récupération statut', [
                    'request_id' => $requestId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Échec de la récupération du statut. Status: ' . $response->status());
            }

            $data = $response->json();
            
            // Log de la réponse complète pour debug
            Log::info('Fal Service - Réponse statut', [
                'request_id' => $requestId,
                'response_data' => $data,
            ]);
            
            // Si la réponse contient directement le résultat (model_mesh ou model_glb), la requête est terminée
            $hasResult = isset($data['model_mesh']) || isset($data['model_glb']) || 
                        (isset($data['response']) && (isset($data['response']['model_mesh']) || isset($data['response']['model_glb'])));
            
            // Si pas de statut explicite mais qu'on a un résultat, c'est COMPLETED
            if (!isset($data['status']) && $hasResult) {
                $status = 'COMPLETED';
            } else {
                $status = strtoupper($data['status'] ?? 'UNKNOWN');
            }
            
            // Si la requête est terminée, récupérer l'URL du modèle depuis la réponse
            // L'API retourne directement le résultat dans la réponse GET si le statut est COMPLETED
            $modelGlbUrl = null;
            if ($status === 'COMPLETED' || $status === 'SUCCEEDED' || $hasResult) {
                // Le résultat est déjà dans $data
                // Utiliser model_glb ou model_mesh pour le fichier GLB
                if (isset($data['model_glb']) && isset($data['model_glb']['url'])) {
                    $modelGlbUrl = $data['model_glb']['url'];
                } elseif (isset($data['model_mesh']) && isset($data['model_mesh']['url'])) {
                    // Utiliser model_mesh si model_glb n'est pas disponible
                    $modelGlbUrl = $data['model_mesh']['url'];
                } elseif (isset($data['response'])) {
                    if (isset($data['response']['model_glb']) && isset($data['response']['model_glb']['url'])) {
                        $modelGlbUrl = $data['response']['model_glb']['url'];
                    } elseif (isset($data['response']['model_mesh']) && isset($data['response']['model_mesh']['url'])) {
                        $modelGlbUrl = $data['response']['model_mesh']['url'];
                    }
                }
            }
            
            return [
                'status' => $status,
                'model_mesh_url' => $modelGlbUrl, // Utiliser model_glb pour le GLB
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Fal Service - Erreur récupération statut', [
                'request_id' => $requestId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
