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
    protected $signature = 'ml:train-position {--test-ratio=0.2}';
    protected $description = 'Entraîner le modèle de classification de position des images';

    public function handle()
    {
        $this->info('Entraînement du modèle de classification de position...');
        
        $trainingDir = storage_path('app/training/images/position');
        $modelPath = storage_path('app/models/position-classifier.rbx');
        
        // Vérifier que les dossiers existent
        if (!File::exists($trainingDir)) {
            $this->error("Le dossier d'entraînement n'existe pas: {$trainingDir}");
            $this->info("Créez les dossiers suivants et ajoutez vos images:");
            $this->info("  - {$trainingDir}/Front");
            $this->info("  - {$trainingDir}/Back");
            $this->info("  - {$trainingDir}/Left");
            $this->info("  - {$trainingDir}/Right");
            $this->info("  - {$trainingDir}/LateralLeft");
            $this->info("  - {$trainingDir}/LateralRight");
            $this->info("  - {$trainingDir}/Top");
            $this->info("  - {$trainingDir}/Bottom");
            $this->info("  - {$trainingDir}/PartZoom");
            return 1;
        }
        
        $positions = ['Front', 'Back', 'Left', 'Right', 'Lateral Left', 'Lateral Right', 'Top', 'Bottom', 'Part Zoom'];
        $samples = [];
        $labels = [];
        
        $imageManager = new ImageManager(new Driver());
        
        foreach ($positions as $position) {
            // Convertir "Lateral Left" en "LateralLeft" pour le nom de dossier
            $folderName = str_replace(' ', '', $position);
            $positionDir = $trainingDir . '/' . $folderName;
            
            if (!File::exists($positionDir)) {
                $this->warn("Dossier manquant: {$positionDir}");
                continue;
            }
            
            $images = File::glob($positionDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
            
            if (empty($images)) {
                $this->warn("Aucune image trouvée dans: {$positionDir}");
                continue;
            }
            
            $this->info("Traitement de {$position}: " . count($images) . " images");
            
            foreach ($images as $imagePath) {
                try {
                    $image = $imageManager->read(file_get_contents($imagePath));
                    $features = $this->extractImageFeatures($image);
                    
                    $samples[] = $features;
                    $labels[] = $position;
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
}
