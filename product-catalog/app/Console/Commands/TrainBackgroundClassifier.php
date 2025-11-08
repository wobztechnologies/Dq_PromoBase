<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Rubix\ML\Classifiers\KNearestNeighbors;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\File;

class TrainBackgroundClassifier extends Command
{
    protected $signature = 'ml:train-background {--test-ratio=0.2}';
    protected $description = 'Entraîner le modèle de classification de fond neutre';

    public function handle()
    {
        $this->info('Entraînement du modèle de classification de fond neutre...');
        
        $trainingDir = storage_path('app/training/images/background');
        $modelPath = storage_path('app/models/background-classifier.rbx');
        
        // Vérifier que les dossiers existent
        if (!File::exists($trainingDir)) {
            $this->error("Le dossier d'entraînement n'existe pas: {$trainingDir}");
            $this->info("Créez les dossiers suivants et ajoutez vos images:");
            $this->info("  - {$trainingDir}/neutral");
            $this->info("  - {$trainingDir}/non-neutral");
            return 1;
        }
        
        $categories = ['neutral', 'non-neutral'];
        $samples = [];
        $labels = [];
        
        $imageManager = new ImageManager(new Driver());
        
        foreach ($categories as $category) {
            $categoryDir = $trainingDir . '/' . $category;
            
            if (!File::exists($categoryDir)) {
                $this->warn("Dossier manquant: {$categoryDir}");
                continue;
            }
            
            $images = File::glob($categoryDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
            
            if (empty($images)) {
                $this->warn("Aucune image trouvée dans: {$categoryDir}");
                continue;
            }
            
            $this->info("Traitement de {$category}: " . count($images) . " images");
            
            foreach ($images as $imagePath) {
                try {
                    $image = $imageManager->read(file_get_contents($imagePath));
                    $features = $this->extractBackgroundFeatures($image);
                    
                    $samples[] = $features;
                    $labels[] = $category === 'neutral' ? 'true' : 'false';
                } catch (\Exception $e) {
                    $this->warn("Erreur lors du traitement de {$imagePath}: " . $e->getMessage());
                }
            }
        }
        
        if (empty($samples)) {
            $this->error('Aucune image valide trouvée pour l\'entraînement');
            return 1;
        }
        
        $this->info("Total d'images: " . count($samples));
        
        // Créer le dataset
        $dataset = new Labeled($samples, $labels);
        
        // Diviser en train/test
        $testRatio = (float)$this->option('test-ratio');
        [$training, $testing] = $dataset->stratifiedSplit($testRatio);
        
        $this->info("Données d'entraînement: " . $training->numSamples());
        $this->info("Données de test: " . $testing->numSamples());
        
        // Entraîner le modèle
        $this->info('Entraînement du modèle KNN...');
        $estimator = new KNearestNeighbors(5);
        $estimator->train($training);
        
        // Tester le modèle
        $this->info('Test du modèle...');
        $predictions = $estimator->predict($testing);
        $accuracy = $this->calculateAccuracy($testing->labels(), $predictions);
        
        $this->info("Précision: " . number_format($accuracy * 100, 2) . '%');
        
        // Sauvegarder le modèle
        $this->info('Sauvegarde du modèle...');
        $model = new PersistentModel($estimator, new Filesystem($modelPath));
        $model->save();
        
        $this->info("✅ Modèle sauvegardé dans: {$modelPath}");
        
        return 0;
    }
    
    /**
     * Extraire les features spécifiques pour la détection de fond neutre
     * (analyse des bords de l'image)
     */
    private function extractBackgroundFeatures($image): array
    {
        $width = $image->width();
        $height = $image->height();
        
        // Échantillonner les pixels des bords
        $edgePixels = [];
        $sampleSize = min(50, max(10, (int)($width / 20)));
        
        // Top edge
        for ($x = 0; $x < $width; $x += max(1, (int)($width / $sampleSize))) {
            try {
                $color = $image->pickColor($x, 0);
                if ($color && is_array($color) && count($color) >= 3) {
                    $edgePixels[] = [(float)($color[0] ?? 0), (float)($color[1] ?? 0), (float)($color[2] ?? 0)];
                }
            } catch (\Exception $e) {
                // Ignorer
            }
        }
        
        // Bottom edge
        for ($x = 0; $x < $width; $x += max(1, (int)($width / $sampleSize))) {
            try {
                $color = $image->pickColor($x, $height - 1);
                if ($color && is_array($color) && count($color) >= 3) {
                    $edgePixels[] = [(float)($color[0] ?? 0), (float)($color[1] ?? 0), (float)($color[2] ?? 0)];
                }
            } catch (\Exception $e) {
                // Ignorer
            }
        }
        
        // Left edge
        for ($y = 0; $y < $height; $y += max(1, (int)($height / $sampleSize))) {
            try {
                $color = $image->pickColor(0, $y);
                if ($color && is_array($color) && count($color) >= 3) {
                    $edgePixels[] = [(float)($color[0] ?? 0), (float)($color[1] ?? 0), (float)($color[2] ?? 0)];
                }
            } catch (\Exception $e) {
                // Ignorer
            }
        }
        
        // Right edge
        for ($y = 0; $y < $height; $y += max(1, (int)($height / $sampleSize))) {
            try {
                $color = $image->pickColor($width - 1, $y);
                if ($color && is_array($color) && count($color) >= 3) {
                    $edgePixels[] = [(float)($color[0] ?? 0), (float)($color[1] ?? 0), (float)($color[2] ?? 0)];
                }
            } catch (\Exception $e) {
                // Ignorer
            }
        }
        
        // Calculer les statistiques des bords
        if (empty($edgePixels)) {
            return array_fill(0, 9, 0.0); // Retourner des features par défaut
        }
        
        $rValues = array_column($edgePixels, 0);
        $gValues = array_column($edgePixels, 1);
        $bValues = array_column($edgePixels, 2);
        
        // Features: moyenne et variance de R, G, B
        return [
            (float)(array_sum($rValues) / count($rValues)),
            (float)(array_sum($gValues) / count($gValues)),
            (float)(array_sum($bValues) / count($bValues)),
            (float)$this->variance($rValues),
            (float)$this->variance($gValues),
            (float)$this->variance($bValues),
            (float)min($rValues),
            (float)min($gValues),
            (float)min($bValues),
        ];
    }
    
    private function variance(array $values): float
    {
        if (empty($values)) {
            return 0;
        }
        
        $mean = array_sum($values) / count($values);
        $variance = 0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        return $variance / count($values);
    }
    
    private function calculateAccuracy(array $actual, array $predicted): float
    {
        $correct = 0;
        $total = count($actual);
        
        for ($i = 0; $i < $total; $i++) {
            if ($actual[$i] === $predicted[$i]) {
                $correct++;
            }
        }
        
        return $total > 0 ? $correct / $total : 0.0;
    }
}
