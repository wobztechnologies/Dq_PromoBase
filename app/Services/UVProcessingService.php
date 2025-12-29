<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

/**
 * Service pour traiter et corriger les UV maps des modèles 3D
 * 
 * Ce service utilise un script Node.js avec xatlas-three pour:
 * 1. Analyser les UV maps existantes
 * 2. Détecter la fragmentation excessive
 * 3. Régénérer les UV maps si nécessaire
 * 4. Créer un layer UV2 pour la personnalisation
 */
class UVProcessingService
{
    /**
     * Chemin vers le script Node.js de traitement UV
     */
    protected string $scriptPath;

    /**
     * Options par défaut pour le traitement
     */
    protected array $defaultOptions = [
        'resolution' => 1024,
        'forceUnwrap' => false,
        'analyzeOnly' => false,
    ];

    public function __construct()
    {
        $this->scriptPath = base_path('scripts/process-uv-maps.js');
    }

    /**
     * Traiter les UV maps d'un fichier GLB
     * 
     * @param string $inputPath Chemin du fichier GLB source
     * @param string|null $outputPath Chemin de sortie (optionnel, par défaut = inputPath)
     * @param array $options Options de traitement
     * @return array Résultat du traitement avec analyse et statut
     */
    public function process(string $inputPath, ?string $outputPath = null, array $options = []): array
    {
        $options = array_merge($this->defaultOptions, $options);
        $outputPath = $outputPath ?? $inputPath;

        Log::info('UVProcessingService - Début du traitement', [
            'input' => $inputPath,
            'output' => $outputPath,
            'options' => $options,
        ]);

        // Vérifier que le fichier d'entrée existe
        if (!file_exists($inputPath)) {
            Log::error('UVProcessingService - Fichier non trouvé', ['path' => $inputPath]);
            return [
                'success' => false,
                'error' => 'Le fichier source n\'existe pas: ' . $inputPath,
            ];
        }

        // Vérifier que le script Node.js existe
        if (!file_exists($this->scriptPath)) {
            Log::error('UVProcessingService - Script UV non trouvé', ['script' => $this->scriptPath]);
            return [
                'success' => false,
                'error' => 'Le script de traitement UV n\'existe pas',
            ];
        }

        // Construire la commande
        $nodePath = $this->getNodePath();
        $command = $this->buildCommand($nodePath, $inputPath, $outputPath, $options);

        Log::info('UVProcessingService - Exécution de la commande', ['command' => $command]);

        // Exécuter le script
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        $outputString = implode("\n", $output);
        
        Log::info('UVProcessingService - Sortie du script', [
            'return_code' => $returnCode,
            'output_lines' => count($output),
        ]);

        // Parser le résultat JSON
        $result = $this->parseResult($outputString);

        if ($returnCode !== 0 || !$result['success']) {
            Log::error('UVProcessingService - Échec du traitement', [
                'return_code' => $returnCode,
                'error' => $result['error'] ?? 'Erreur inconnue',
                'output' => $outputString,
            ]);
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Erreur lors du traitement UV',
                'output' => $outputString,
            ];
        }

        Log::info('UVProcessingService - Traitement terminé avec succès', [
            'processed' => $result['processed'] ?? false,
            'analysis' => $result['analysis'] ?? null,
        ]);

        return $result;
    }

    /**
     * Analyser les UV maps sans les modifier
     * 
     * @param string $inputPath Chemin du fichier GLB
     * @return array Résultat de l'analyse
     */
    public function analyze(string $inputPath): array
    {
        return $this->process($inputPath, null, ['analyzeOnly' => true]);
    }

    /**
     * Forcer le re-unwrap des UV maps
     * 
     * @param string $inputPath Chemin du fichier GLB source
     * @param string|null $outputPath Chemin de sortie
     * @return array Résultat du traitement
     */
    public function forceUnwrap(string $inputPath, ?string $outputPath = null): array
    {
        return $this->process($inputPath, $outputPath, ['forceUnwrap' => true]);
    }

    /**
     * Traiter un fichier GLB de manière asynchrone dans un répertoire temporaire
     * Retourne le chemin du fichier traité
     * 
     * @param string $inputPath Chemin du fichier source
     * @param string $tempDir Répertoire temporaire
     * @return array Résultat avec le chemin du fichier traité
     */
    public function processToTemp(string $inputPath, string $tempDir): array
    {
        // Créer le répertoire temporaire si nécessaire
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Générer un nom de fichier de sortie
        $outputPath = $tempDir . '/model-uv-processed.glb';

        // Traiter le fichier
        $result = $this->process($inputPath, $outputPath);

        if ($result['success']) {
            $result['output_path'] = $outputPath;
        }

        return $result;
    }

    /**
     * Obtenir le chemin vers Node.js
     */
    protected function getNodePath(): string
    {
        // Essayer de trouver node dans le PATH
        $nodePath = trim(shell_exec('which node') ?? '');
        
        if (empty($nodePath)) {
            // Fallback sur les chemins communs
            $commonPaths = [
                '/usr/local/bin/node',
                '/usr/bin/node',
                '/opt/homebrew/bin/node',
            ];
            
            foreach ($commonPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
            
            // Dernier recours
            return 'node';
        }

        return $nodePath;
    }

    /**
     * Construire la commande à exécuter
     */
    protected function buildCommand(string $nodePath, string $inputPath, string $outputPath, array $options): string
    {
        $args = [
            escapeshellarg($nodePath),
            escapeshellarg($this->scriptPath),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath),
        ];

        if ($options['analyzeOnly'] ?? false) {
            $args[] = '--analyze-only';
        }

        if ($options['forceUnwrap'] ?? false) {
            $args[] = '--force-unwrap';
        }

        if (isset($options['resolution'])) {
            $args[] = '--resolution=' . (int) $options['resolution'];
        }

        return implode(' ', $args);
    }

    /**
     * Parser le résultat JSON du script
     */
    protected function parseResult(string $output): array
    {
        // Chercher le bloc JSON dans la sortie
        $jsonStart = strrpos($output, '📤 Résultat (JSON):');
        
        if ($jsonStart !== false) {
            $jsonString = substr($output, $jsonStart + strlen('📤 Résultat (JSON):'));
            $jsonString = trim($jsonString);
            
            $result = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $result;
            }
        }

        // Fallback: essayer de parser tout comme JSON
        $result = json_decode($output, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // Retourner un résultat d'erreur par défaut
        return [
            'success' => false,
            'error' => 'Impossible de parser le résultat du script',
            'raw_output' => $output,
        ];
    }

    /**
     * Vérifier si le traitement UV est disponible (Node.js + script présent)
     */
    public function isAvailable(): bool
    {
        $nodePath = $this->getNodePath();
        
        // Vérifier que Node.js est accessible
        $nodeVersion = shell_exec(escapeshellarg($nodePath) . ' --version 2>&1');
        
        if (empty($nodeVersion) || strpos($nodeVersion, 'v') !== 0) {
            Log::warning('UVProcessingService - Node.js non disponible');
            return false;
        }

        // Vérifier que le script existe
        if (!file_exists($this->scriptPath)) {
            Log::warning('UVProcessingService - Script de traitement UV non trouvé');
            return false;
        }

        return true;
    }

    /**
     * Obtenir les informations sur le service
     */
    public function getInfo(): array
    {
        $nodePath = $this->getNodePath();
        $nodeVersion = trim(shell_exec(escapeshellarg($nodePath) . ' --version 2>&1') ?? 'inconnu');

        return [
            'available' => $this->isAvailable(),
            'node_path' => $nodePath,
            'node_version' => $nodeVersion,
            'script_path' => $this->scriptPath,
            'script_exists' => file_exists($this->scriptPath),
        ];
    }
}

