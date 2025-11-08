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
    protected $signature = 'ml:train-background {--test-ratio=0.2} {--memory=2048M} {--balance : Équilibrer les classes pour éviter le biais}';
    protected $description = 'Entraîner le modèle de classification de fond neutre';

    public function handle()
    {
        // Augmenter la limite de mémoire
        $memoryLimit = $this->option('memory');
        ini_set('memory_limit', $memoryLimit);
        
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
        $imagesByCategory = [];
        
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
            $imagesByCategory[$category] = $images;
        }
        
        // Équilibrer les classes si demandé
        if ($this->option('balance')) {
            $this->info("\n🔄 Équilibrage des classes...");
            $imagesByCategory = $this->balanceClasses($imagesByCategory);
            
            foreach ($imagesByCategory as $category => $images) {
                $this->info("Après équilibrage - '{$category}': " . count($images) . " images");
            }
        }
        
        // Extraire les features
        $this->info("\n📊 Extraction des features...");
        $progressBar = $this->output->createProgressBar(array_sum(array_map('count', $imagesByCategory)));
        $progressBar->start();
        
        foreach ($imagesByCategory as $category => $images) {
            $label = $category === 'neutral' ? 'true' : 'false';
            foreach ($images as $imagePath) {
                try {
                    $image = $imageManager->read(file_get_contents($imagePath));
                    $features = $this->extractBackgroundFeatures($image);
                    
                    $samples[] = $features;
                    $labels[] = $label;
                    $progressBar->advance();
                } catch (\Exception $e) {
                    $this->warn("\nErreur lors du traitement de {$imagePath}: " . $e->getMessage());
                }
            }
        }
        $progressBar->finish();
        $this->newLine();
        
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
    
    /**
     * Équilibrer les classes pour éviter le biais vers les classes majoritaires
     */
    private function balanceClasses(array $imagesByCategory): array
    {
        // Calculer le nombre d'images par catégorie
        $counts = array_map('count', $imagesByCategory);
        
        // Stratégie : utiliser la médiane comme cible
        $sortedCounts = $counts;
        sort($sortedCounts);
        $medianIndex = (int)(count($sortedCounts) / 2);
        $targetCount = $sortedCounts[$medianIndex];
        
        // Minimum 50 images par classe pour avoir assez de données
        $targetCount = max(50, $targetCount);
        
        $this->info("Cible d'équilibrage : {$targetCount} images par catégorie");
        
        $balanced = [];
        
        foreach ($imagesByCategory as $category => $images) {
            $currentCount = count($images);
            
            if ($currentCount > $targetCount) {
                // Sous-échantillonner (réduire) les classes majoritaires
                shuffle($images);
                $balanced[$category] = array_slice($images, 0, $targetCount);
            } elseif ($currentCount < $targetCount) {
                // Sur-échantillonner (dupliquer) les classes minoritaires
                $balanced[$category] = $images;
                $needed = $targetCount - $currentCount;
                
                // Dupliquer aléatoirement des images existantes
                for ($i = 0; $i < $needed; $i++) {
                    $balanced[$category][] = $images[array_rand($images)];
                }
            } else {
                $balanced[$category] = $images;
            }
        }
        
        return $balanced;
    }
}
