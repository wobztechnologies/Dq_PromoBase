<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Ai3DService
{
    private ?string $serverUrl = null;
    private ?string $serverLogin = null;
    private ?string $serverPassword = null;

    public function __construct()
    {
        $this->serverUrl = Setting::get('3dai_server_url');
        $this->serverLogin = Setting::get('3dai_server_login');
        $this->serverPassword = Setting::get('3dai_server_password');
    }

    /**
     * Authentifier auprès du serveur AI et récupérer le token JWT
     */
    public function authenticate(): ?string
    {
        if (!$this->serverUrl || !$this->serverLogin || !$this->serverPassword) {
            Log::error('AI 3D Service - Configuration incomplète', [
                'url' => $this->serverUrl ? 'présent' : 'absent',
                'login' => $this->serverLogin ? 'présent' : 'absent',
                'password' => $this->serverPassword ? 'présent' : 'absent',
            ]);
            throw new \Exception('Configuration du serveur AI incomplète. Veuillez vérifier les paramètres.');
        }

        // Vérifier si un token valide est en cache
        $cacheKey = 'ai3d_token_' . md5($this->serverUrl . $this->serverLogin);
        $cachedToken = Cache::get($cacheKey);
        
        if ($cachedToken) {
            Log::info('AI 3D Service - Utilisation du token en cache');
            return $cachedToken;
        }

        try {
            $url = rtrim($this->serverUrl, '/') . '/api/login';
            
            Log::info('AI 3D Service - Authentification', ['url' => $url]);
            
            $response = Http::timeout(30)->post($url, [
                'email' => $this->serverLogin,
                'password' => $this->serverPassword,
            ]);

            if (!$response->successful()) {
                Log::error('AI 3D Service - Échec authentification', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Échec de l\'authentification auprès du serveur AI. Status: ' . $response->status());
            }

            $data = $response->json();
            $token = $data['token'] ?? null;
            $expiresIn = $data['expires_in'] ?? 3600;

            if (!$token) {
                Log::error('AI 3D Service - Token manquant dans la réponse', ['response' => $data]);
                throw new \Exception('Token non reçu du serveur AI.');
            }

            // Mettre en cache le token (expire 5 minutes avant l'expiration réelle)
            $cacheExpiration = max(60, $expiresIn - 300);
            Cache::put($cacheKey, $token, $cacheExpiration);

            Log::info('AI 3D Service - Authentification réussie', ['expires_in' => $expiresIn]);

            return $token;
        } catch (\Exception $e) {
            Log::error('AI 3D Service - Erreur lors de l\'authentification', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Générer un modèle 3D via l'API AI
     * 
     * @param array $views Tableau associatif position => s3_url (ex: ['front' => 's3://...'])
     * @param string $outputPath Chemin S3 pour le modèle généré
     * @param array $options Options de génération (target_faces, compression_level)
     * @return array Réponse de l'API avec job_id et status
     */
    public function generate3D(array $views, string $outputPath, array $options = []): array
    {
        // Authentifier et récupérer le token
        $token = $this->authenticate();

        try {
            $url = rtrim($this->serverUrl, '/') . '/api/generate-3d';
            
            $payload = [
                'views' => $views,
                'output_path' => $outputPath,
                'options' => array_merge([
                    'target_faces' => 10000,
                    'compression_level' => 10,
                ], $options),
            ];

            // Encoder le payload en JSON pour affichage
            $jsonPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            Log::info('AI 3D Service - Requête de génération', [
                'url' => $url,
                'payload' => $payload,
            ]);
            
            // Log du JSON formaté pour la console
            Log::info("AI 3D Service - JSON POST vers DecqAiServer:\n" . $jsonPayload);

            $response = Http::timeout(60)
                ->withToken($token)
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::error('AI 3D Service - Échec génération', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Échec de la génération du modèle 3D. Status: ' . $response->status());
            }

            $data = $response->json();

            Log::info('AI 3D Service - Génération lancée', ['response' => $data]);

            return [
                'success' => true,
                'job_id' => $data['job_id'] ?? null,
                'status' => $data['status'] ?? 'unknown',
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('AI 3D Service - Erreur lors de la génération', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

