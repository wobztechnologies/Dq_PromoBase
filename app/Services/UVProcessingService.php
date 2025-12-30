<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

/**
 * Service pour créer des UV de personnalisation pour modèles 3D
 * 
 * IMPORTANT: Ce service NE MODIFIE PAS les UV originales (TEXCOORD_0)
 * Il crée un layer UV séparé (TEXCOORD_1) pour la personnalisation Fabric.js
 * 
 * Types de projection disponibles:
 * - cylindrical: Idéal pour t-shirts, mugs (wrap autour de l'axe Y)
 * - planar: Idéal pour surfaces plates, posters (projection XY)
 * - box: Idéal pour objets cubiques (6 faces)
 * - spherical: Idéal pour objets ronds (ballons, casques)
 */
class UVProcessingService
{
    /**
     * Chemin vers le script Node.js de traitement UV
     */
    protected string $scriptPath;

    /**
     * Types de projection disponibles
     */
    public const PROJECTION_AUTO = 'auto';
    public const PROJECTION_CYLINDRICAL = 'cylindrical';
    public const PROJECTION_PLANAR = 'planar';
    public const PROJECTION_BOX = 'box';
    public const PROJECTION_SPHERICAL = 'spherical';

    /**
     * Options par défaut pour le traitement
     * Par défaut: auto-détection basée sur la forme du modèle
     */
    protected array $defaultOptions = [
        'projection' => self::PROJECTION_AUTO, // Auto-détection par défaut
        'analyzeOnly' => false,
        'preserveUV2' => false,
    ];

    public function __construct()
    {
        $this->scriptPath = base_path('scripts/process-uv-maps.js');
    }

    /**
     * Créer les UV de personnalisation pour un fichier GLB
     * 
     * Cette méthode:
     * - Préserve les UV originales (TEXCOORD_0)
     * - Crée un nouveau layer UV (TEXCOORD_1) pour Fabric.js
     * 
     * @param string $inputPath Chemin du fichier GLB source
     * @param string|null $outputPath Chemin de sortie (optionnel, par défaut = inputPath)
     * @param array $options Options de traitement
     * @return array Résultat du traitement
     */
    public function process(string $inputPath, ?string $outputPath = null, array $options = []): array
    {
        $options = array_merge($this->defaultOptions, $options);
        $outputPath = $outputPath ?? $inputPath;

        Log::info('UVProcessingService - Création UV personnalisation', [
            'input' => $inputPath,
            'output' => $outputPath,
            'projection' => $options['projection'],
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

        Log::info('UVProcessingService - Exécution', ['command' => $command]);

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
            Log::error('UVProcessingService - Échec', [
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

        Log::info('UVProcessingService - Succès', [
            'uv1_created' => $result['uv1Created'] ?? 0,
            'projection' => $result['projection'] ?? $options['projection'],
            'uv_original_preserved' => $result['uvOriginalPreserved'] ?? true,
        ]);

        return $result;
    }

    /**
     * Analyser les UV d'un modèle sans les modifier
     * 
     * @param string $inputPath Chemin du fichier GLB
     * @return array Résultat de l'analyse
     */
    public function analyze(string $inputPath): array
    {
        return $this->process($inputPath, null, ['analyzeOnly' => true]);
    }

    /**
     * Créer les UV de personnalisation avec projection cylindrique
     * Idéal pour: t-shirts, mugs, bouteilles
     */
    public function createCylindricalUV(string $inputPath, ?string $outputPath = null): array
    {
        return $this->process($inputPath, $outputPath, [
            'projection' => self::PROJECTION_CYLINDRICAL,
        ]);
    }

    /**
     * Créer les UV de personnalisation avec projection planaire
     * Idéal pour: posters, écrans, surfaces plates
     */
    public function createPlanarUV(string $inputPath, ?string $outputPath = null): array
    {
        return $this->process($inputPath, $outputPath, [
            'projection' => self::PROJECTION_PLANAR,
        ]);
    }

    /**
     * Créer les UV de personnalisation avec projection box
     * Idéal pour: boîtes, cubes, objets angulaires
     */
    public function createBoxUV(string $inputPath, ?string $outputPath = null): array
    {
        return $this->process($inputPath, $outputPath, [
            'projection' => self::PROJECTION_BOX,
        ]);
    }

    /**
     * Créer les UV de personnalisation avec projection sphérique
     * Idéal pour: ballons, casques, objets ronds
     */
    public function createSphericalUV(string $inputPath, ?string $outputPath = null): array
    {
        return $this->process($inputPath, $outputPath, [
            'projection' => self::PROJECTION_SPHERICAL,
        ]);
    }

    /**
     * Traiter un fichier GLB dans un répertoire temporaire
     * 
     * @param string $inputPath Chemin du fichier source
     * @param string $tempDir Répertoire temporaire
     * @param string $projection Type de projection
     * @return array Résultat avec le chemin du fichier traité
     */
    public function processToTemp(string $inputPath, string $tempDir, string $projection = self::PROJECTION_CYLINDRICAL): array
    {
        // Créer le répertoire temporaire si nécessaire
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Générer un nom de fichier de sortie
        $outputPath = $tempDir . '/model-with-uv-perso.glb';

        // Traiter le fichier
        $result = $this->process($inputPath, $outputPath, [
            'projection' => $projection,
        ]);

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

        if ($options['preserveUV2'] ?? false) {
            $args[] = '--preserve-uv2';
        }

        if (isset($options['projection'])) {
            $args[] = '--projection=' . escapeshellarg($options['projection']);
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
     * Vérifier si le traitement UV est disponible
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
            Log::warning('UVProcessingService - Script non trouvé');
            return false;
        }

        return true;
    }

    /**
     * Obtenir les types de projection disponibles
     */
    public static function getProjectionTypes(): array
    {
        return [
            self::PROJECTION_AUTO => 'Auto-détection (basée sur la forme)',
            self::PROJECTION_CYLINDRICAL => 'Cylindrique (t-shirts, mugs)',
            self::PROJECTION_PLANAR => 'Planaire (surfaces plates)',
            self::PROJECTION_BOX => 'Box (objets cubiques)',
            self::PROJECTION_SPHERICAL => 'Sphérique (objets ronds)',
        ];
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
            'projection_types' => self::getProjectionTypes(),
            'preserves_original_uv' => true,
            'creates_uv1_for_personalization' => true,
        ];
    }
}
