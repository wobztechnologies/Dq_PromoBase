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

class TrainProductOnlyClassifier extends Command
{
    protected $signature = 'ml:train-product-only {--test-ratio=0.2} {--memory=2048M} {--balance : Équilibrer les classes pour éviter le biais}';
    protected $description = 'Entraîner le modèle de classification product-only (vêtement seul vs mise en situation)';

    public function handle()
    {
        // Augmenter la limite de mémoire
        $memoryLimit = $this->option('memory');
        ini_set('memory_limit', $memoryLimit);
        
        $this->info('Entraînement du modèle de classification product-only...');
        
        $trainingDir = storage_path('app/training/images/product-only');
        $modelPath = storage_path('app/models/product-only-classifier.rbx');
        
        // Vérifier que les dossiers existent
        if (!File::exists($trainingDir)) {
            $this->error("Le dossier d'entraînement n'existe pas: {$trainingDir}");
            $this->info("Créez les dossiers suivants et ajoutez vos images:");
            $this->info("  - {$trainingDir}/product-only (images avec seulement le vêtement)");
            $this->info("  - {$trainingDir}/situational (images avec mise en situation, personne qui porte le vêtement, etc.)");
            return 1;
        }
        
        $categories = [
            'product-only' => 'true',
            'situational' => 'false',
        ];
        
        $samples = [];
        $labels = [];
        $imagesByCategory = [];
        
        $imageManager = new ImageManager(new Driver());
        
        foreach ($categories as $category => $label) {
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
            $label = $categories[$category];
            foreach ($images as $imagePath) {
                try {
                    $image = $imageManager->read(file_get_contents($imagePath));
                    $features = $this->extractImageFeatures($image);
                    
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
    
    private function extractImageFeatures($image): array
    {
        // Redimensionner à 224x224
        $resized = $image->scale(width: 224, height: 224);
        $features = [];
        
        for ($y = 0; $y < 224; $y++) {
            for ($x = 0; $x < 224; $x++) {
                try {
                    $color = $resized->pickColor($x, $y);
                    if ($color && is_array($color) && count($color) >= 3) {
                        $features[] = (float)($color[0] ?? 0);
                        $features[] = (float)($color[1] ?? 0);
                        $features[] = (float)($color[2] ?? 0);
                    } else {
                        $features[] = 0.0;
                        $features[] = 0.0;
                        $features[] = 0.0;
                    }
                } catch (\Exception $e) {
                    $features[] = 0.0;
                    $features[] = 0.0;
                    $features[] = 0.0;
                }
            }
        }
        
        return $features;
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
