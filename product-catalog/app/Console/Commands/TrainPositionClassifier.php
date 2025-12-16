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

class TrainPositionClassifier extends Command
{
    protected $signature = 'ml:train-position {--test-ratio=0.2} {--memory=2048M} {--balance : Équilibrer les classes pour éviter le biais}';
    protected $description = 'Entraîner le modèle de classification de position des images';

    public function handle()
    {
        // Augmenter la limite de mémoire
        $memoryLimit = $this->option('memory');
        ini_set('memory_limit', $memoryLimit);
        
        $this->info('Entraînement du modèle de classification de position...');
        
        $trainingDir = storage_path('app/training/images/position');
        $modelPath = storage_path('app/models/position-classifier.rbx');
        
        // Vérifier que les dossiers existent
        if (!File::exists($trainingDir)) {
            $this->error("Le dossier d'entraînement n'existe pas: {$trainingDir}");
            $this->info("Créez les dossiers suivants et ajoutez vos images:");
            $this->info("  - {$trainingDir}/Back");
            $this->info("  - {$trainingDir}/Bottom");
            $this->info("  - {$trainingDir}/Front");
            $this->info("  - {$trainingDir}/PartZoom");
            $this->info("  - {$trainingDir}/Side");
            $this->info("  - {$trainingDir}/Top");
            $this->info("  - {$trainingDir}/Left");
            $this->info("  - {$trainingDir}/Right");
            return 1;
        }
        
        $positions = ['Back', 'Bottom', 'Front', 'Part Zoom', 'Side', 'Top', 'Left', 'Right'];
        
        $samples = [];
        $labels = [];
        $imagesByPosition = [];
        
        $this->info('Chargement des images d\'entraînement...');
        
        $imageManager = new ImageManager(new Driver());
        
        // Charger toutes les images par position
        foreach ($positions as $position) {
            $folderName = str_replace(' ', '', $position);
            $positionDir = $trainingDir . '/' . $folderName;
            
            // Pour Side, Left et Right, aussi chercher dans les anciens dossiers pour compatibilité
            if ($position === 'Side') {
                $oldFolders = ['LateralLeft', 'LateralRight'];
                $allImages = [];
                
                // Chercher dans le dossier Side
                if (File::exists($positionDir)) {
                    $allImages = array_merge($allImages, File::glob($positionDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE));
                }
                
                // Chercher dans les anciens dossiers latéraux
                foreach ($oldFolders as $oldFolder) {
                    $oldDir = $trainingDir . '/' . $oldFolder;
                    if (File::exists($oldDir)) {
                        $oldImages = File::glob($oldDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
                        $allImages = array_merge($allImages, $oldImages);
                    }
                }
                
                if (empty($allImages)) {
                    $this->warn("Aucune image trouvée pour Side (dossiers: Side, LateralLeft, LateralRight)");
                    continue;
                }
                
                $this->info("Position '{$position}': " . count($allImages) . " images (incluant les anciens dossiers)");
                $imagesByPosition[$position] = $allImages;
                continue;
            }
            
            if ($position === 'Left') {
                $oldFolders = ['LateralLeft'];
                $allImages = [];
                
                // Chercher dans le dossier Left
                if (File::exists($positionDir)) {
                    $allImages = array_merge($allImages, File::glob($positionDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE));
                }
                
                // Chercher dans les anciens dossiers
                foreach ($oldFolders as $oldFolder) {
                    $oldDir = $trainingDir . '/' . $oldFolder;
                    if (File::exists($oldDir)) {
                        $oldImages = File::glob($oldDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
                        $allImages = array_merge($allImages, $oldImages);
                    }
                }
                
                if (empty($allImages)) {
                    $this->warn("Aucune image trouvée pour Left (dossiers: Left, LateralLeft)");
                    continue;
                }
                
                $this->info("Position '{$position}': " . count($allImages) . " images (incluant les anciens dossiers)");
                $imagesByPosition[$position] = $allImages;
                continue;
            }
            
            if ($position === 'Right') {
                $oldFolders = ['LateralRight'];
                $allImages = [];
                
                // Chercher dans le dossier Right
                if (File::exists($positionDir)) {
                    $allImages = array_merge($allImages, File::glob($positionDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE));
                }
                
                // Chercher dans les anciens dossiers
                foreach ($oldFolders as $oldFolder) {
                    $oldDir = $trainingDir . '/' . $oldFolder;
                    if (File::exists($oldDir)) {
                        $oldImages = File::glob($oldDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
                        $allImages = array_merge($allImages, $oldImages);
                    }
                }
                
                if (empty($allImages)) {
                    $this->warn("Aucune image trouvée pour Right (dossiers: Right, LateralRight)");
                    continue;
                }
                
                $this->info("Position '{$position}': " . count($allImages) . " images (incluant les anciens dossiers)");
                $imagesByPosition[$position] = $allImages;
                continue;
            }
            
            if (!File::exists($positionDir)) {
                $this->warn("Dossier manquant: {$positionDir}");
                continue;
            }
            
            $images = File::glob($positionDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
            
            if (empty($images)) {
                $this->warn("Aucune image trouvée dans: {$positionDir}");
                continue;
            }
            
            $this->info("Position '{$position}': " . count($images) . " images");
            $imagesByPosition[$position] = $images;
        }
        
        // Équilibrer les classes si demandé
        if ($this->option('balance')) {
            $this->info("\n🔄 Équilibrage des classes...");
            $imagesByPosition = $this->balanceClasses($imagesByPosition);
            
            foreach ($imagesByPosition as $position => $images) {
                $this->info("Après équilibrage - '{$position}': " . count($images) . " images");
            }
        }
        
        // Extraire les features
        $this->info("\n📊 Extraction des features...");
        $progressBar = $this->output->createProgressBar(array_sum(array_map('count', $imagesByPosition)));
        $progressBar->start();
        
        foreach ($imagesByPosition as $position => $images) {
            foreach ($images as $imagePath) {
                try {
                    $image = $imageManager->read(file_get_contents($imagePath));
                    $features = $this->extractImageFeatures($image);
                    $samples[] = $features;
                    $labels[] = $position;
                    $progressBar->advance();
                } catch (\Exception $e) {
                    $this->error("\nErreur lors du chargement de {$imagePath}: " . $e->getMessage());
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
        // Redimensionner à 112x112 pour réduire la mémoire (au lieu de 224x224)
        $resized = $image->scale(width: 112, height: 112);
        $features = [];
        
        // Échantillonner tous les 2 pixels
        for ($y = 0; $y < 112; $y += 2) {
            for ($x = 0; $x < 112; $x += 2) {
                try {
                    $color = $resized->pickColor($x, $y);
                    if ($color) {
                        $rgb = $color->toArray();
                        $features[] = (float)$rgb[0];
                        $features[] = (float)$rgb[1];
                        $features[] = (float)$rgb[2];
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
    private function balanceClasses(array $imagesByPosition): array
    {
        // Calculer le nombre d'images par position
        $counts = array_map('count', $imagesByPosition);
        
        // Stratégie : utiliser la médiane comme cible
        $sortedCounts = $counts;
        sort($sortedCounts);
        $medianIndex = (int)(count($sortedCounts) / 2);
        $targetCount = $sortedCounts[$medianIndex];
        
        // Minimum 50 images par classe pour avoir assez de données
        $targetCount = max(50, $targetCount);
        
        $this->info("Cible d'équilibrage : {$targetCount} images par position");
        
        $balanced = [];
        
        foreach ($imagesByPosition as $position => $images) {
            $currentCount = count($images);
            
            if ($currentCount > $targetCount) {
                // Sous-échantillonner (réduire) les classes majoritaires
                shuffle($images);
                $balanced[$position] = array_slice($images, 0, $targetCount);
            } elseif ($currentCount < $targetCount) {
                // Sur-échantillonner (dupliquer) les classes minoritaires
                $balanced[$position] = $images;
                $needed = $targetCount - $currentCount;
                
                // Dupliquer aléatoirement des images existantes
                for ($i = 0; $i < $needed; $i++) {
                    $balanced[$position][] = $images[array_rand($images)];
                }
            } else {
                $balanced[$position] = $images;
            }
        }
        
        return $balanced;
    }
}
