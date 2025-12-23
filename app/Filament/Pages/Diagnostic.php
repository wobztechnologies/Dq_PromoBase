<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class Diagnostic extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string $view = 'filament.pages.diagnostic';

    protected static ?string $navigationLabel = 'Diagnostic';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Diagnostic système';

    public array $diagnostics = [];

    public function mount(): void
    {
        $this->runDiagnostics();
    }

    public function runDiagnostics(): void
    {
        $this->diagnostics = [
            'redis' => $this->checkRedis(),
            'meilisearch' => $this->checkMeilisearch(),
            'fal_ai' => $this->checkFalAi(),
            'meshy' => $this->checkMeshy(),
        ];
    }

    public function refresh(): void
    {
        $this->runDiagnostics();
        
        Notification::make()
            ->title('Diagnostic actualisé')
            ->success()
            ->send();
    }

    private function checkRedis(): array
    {
        $result = [
            'name' => 'Redis',
            'status' => 'error',
            'message' => '',
            'details' => [],
        ];

        try {
            // Vérifier si Redis est configuré
            $redisHost = config('database.redis.default.host', 'N/A');
            $redisPort = config('database.redis.default.port', 'N/A');
            
            $result['details']['host'] = $redisHost;
            $result['details']['port'] = $redisPort;

            // Tester la connexion
            $redis = Redis::connection();
            $pong = $redis->ping();
            
            if ($pong) {
                $result['status'] = 'success';
                $result['message'] = 'Connexion établie';
                
                // Infos supplémentaires
                $info = $redis->info();
                $result['details']['version'] = $info['redis_version'] ?? 'N/A';
                $result['details']['connected_clients'] = $info['connected_clients'] ?? 'N/A';
                $result['details']['used_memory_human'] = $info['used_memory_human'] ?? 'N/A';
            }
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Erreur de connexion: ' . $e->getMessage();
        }

        return $result;
    }

    private function checkMeilisearch(): array
    {
        $result = [
            'name' => 'Meilisearch',
            'status' => 'error',
            'message' => '',
            'details' => [],
        ];

        try {
            $host = config('scout.meilisearch.host', 'N/A');
            $result['details']['host'] = $host;

            if ($host === 'N/A' || empty($host)) {
                $result['status'] = 'warning';
                $result['message'] = 'Non configuré';
                return $result;
            }

            // Tester la connexion à l'API health
            $response = Http::timeout(5)->get($host . '/health');

            if ($response->successful()) {
                $health = $response->json();
                
                if (($health['status'] ?? '') === 'available') {
                    $result['status'] = 'success';
                    $result['message'] = 'Service disponible';
                    
                    // Récupérer les stats
                    $statsResponse = Http::timeout(5)
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . config('scout.meilisearch.key', ''),
                        ])
                        ->get($host . '/stats');
                    
                    if ($statsResponse->successful()) {
                        $stats = $statsResponse->json();
                        $result['details']['indexes'] = count($stats['indexes'] ?? []);
                        $result['details']['database_size'] = $this->formatBytes($stats['databaseSize'] ?? 0);
                    }
                    
                    // Récupérer la version
                    $versionResponse = Http::timeout(5)->get($host . '/version');
                    if ($versionResponse->successful()) {
                        $version = $versionResponse->json();
                        $result['details']['version'] = $version['pkgVersion'] ?? 'N/A';
                    }
                } else {
                    $result['status'] = 'warning';
                    $result['message'] = 'Service en cours de démarrage';
                }
            } else {
                $result['status'] = 'error';
                $result['message'] = 'Service non disponible (HTTP ' . $response->status() . ')';
            }
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Erreur: ' . $e->getMessage();
        }

        return $result;
    }

    private function checkFalAi(): array
    {
        $result = [
            'name' => 'Fal.ai',
            'status' => 'error',
            'message' => '',
            'details' => [],
        ];

        try {
            $apiKey = Setting::get('fal_api_key', '');
            
            if (empty($apiKey)) {
                $result['status'] = 'warning';
                $result['message'] = 'Clé API non configurée';
                $result['details']['api_key'] = 'Non définie';
                return $result;
            }

            $result['details']['api_key'] = substr($apiKey, 0, 8) . '...' . substr($apiKey, -4);

            // Tester l'authentification avec un appel simple
            // On utilise l'endpoint de queue status qui ne coûte rien
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Key ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->get('https://queue.fal.run/fal-ai/hunyuan3d/requests/test-invalid-id');

            // Un 404 signifie que l'authentification a fonctionné mais l'ID n'existe pas
            // Un 401/403 signifie que l'authentification a échoué
            if ($response->status() === 404 || $response->status() === 422) {
                $result['status'] = 'success';
                $result['message'] = 'Authentification réussie';
                $result['details']['endpoint'] = 'queue.fal.run';
            } elseif ($response->status() === 401 || $response->status() === 403) {
                $result['status'] = 'error';
                $result['message'] = 'Authentification échouée - Clé API invalide';
            } else {
                // Autre statut, probablement OK
                $result['status'] = 'success';
                $result['message'] = 'Service accessible (HTTP ' . $response->status() . ')';
            }
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Erreur: ' . $e->getMessage();
        }

        return $result;
    }

    private function checkMeshy(): array
    {
        $result = [
            'name' => 'Meshy',
            'status' => 'error',
            'message' => '',
            'details' => [],
        ];

        try {
            $apiKey = Setting::get('meshy_api_key', '');
            
            if (empty($apiKey)) {
                $result['status'] = 'warning';
                $result['message'] = 'Clé API non configurée';
                $result['details']['api_key'] = 'Non définie';
                return $result;
            }

            $result['details']['api_key'] = substr($apiKey, 0, 8) . '...' . substr($apiKey, -4);

            // Tester l'authentification avec l'endpoint de balance/credits
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->get('https://api.meshy.ai/openapi/v1/balance');

            if ($response->successful()) {
                $result['status'] = 'success';
                $result['message'] = 'Authentification réussie';
                
                $data = $response->json();
                if (isset($data['balance'])) {
                    $result['details']['balance'] = $data['balance'] . ' crédits';
                }
                if (isset($data['subscription'])) {
                    $result['details']['subscription'] = $data['subscription'];
                }
            } elseif ($response->status() === 401 || $response->status() === 403) {
                $result['status'] = 'error';
                $result['message'] = 'Authentification échouée - Clé API invalide';
            } else {
                $result['status'] = 'warning';
                $result['message'] = 'Réponse inattendue (HTTP ' . $response->status() . ')';
            }
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Erreur: ' . $e->getMessage();
        }

        return $result;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

