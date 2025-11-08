<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ReorganizeTrainingFolders extends Command
{
    protected $signature = 'ml:reorganize-training-folders {--dry-run : Afficher les actions sans les exécuter}';
    protected $description = 'Réorganiser les dossiers d\'entraînement : regrouper Left, Right, LateralLeft, LateralRight dans Side';

    public function handle()
    {
        $trainingDir = storage_path('app/training/images/position');
        $dryRun = $this->option('dry-run');
        
        if (!File::exists($trainingDir)) {
            $this->error("Le dossier d'entraînement n'existe pas: {$trainingDir}");
            return 1;
        }
        
        $oldFolders = ['Left', 'Right', 'LateralLeft', 'LateralRight'];
        $targetFolder = $trainingDir . '/Side';
        
        // Créer le dossier Side s'il n'existe pas
        if (!File::exists($targetFolder)) {
            if (!$dryRun) {
                File::makeDirectory($targetFolder, 0755, true);
                $this->info("✅ Dossier créé: {$targetFolder}");
            } else {
                $this->info("🔍 [DRY RUN] Créerait le dossier: {$targetFolder}");
            }
        }
        
        $totalMoved = 0;
        
        foreach ($oldFolders as $oldFolder) {
            $oldPath = $trainingDir . '/' . $oldFolder;
            
            if (!File::exists($oldPath)) {
                $this->warn("Dossier non trouvé (ignoré): {$oldPath}");
                continue;
            }
            
            $images = File::glob($oldPath . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
            
            if (empty($images)) {
                $this->info("Aucune image dans: {$oldPath}");
                if (!$dryRun) {
                    File::deleteDirectory($oldPath);
                    $this->info("✅ Dossier supprimé: {$oldPath}");
                } else {
                    $this->info("🔍 [DRY RUN] Supprimerait le dossier vide: {$oldPath}");
                }
                continue;
            }
            
            $this->info("📁 {$oldFolder}: " . count($images) . " image(s) à déplacer");
            
            foreach ($images as $imagePath) {
                $filename = basename($imagePath);
                $targetPath = $targetFolder . '/' . $filename;
                
                // Si le fichier existe déjà dans Side, ajouter un préfixe
                if (File::exists($targetPath)) {
                    $name = pathinfo($filename, PATHINFO_FILENAME);
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $targetPath = $targetFolder . '/' . $oldFolder . '_' . $filename;
                    $this->warn("  ⚠️  Fichier existant, renommage: {$filename} → " . basename($targetPath));
                }
                
                if (!$dryRun) {
                    File::move($imagePath, $targetPath);
                    $totalMoved++;
                } else {
                    $this->info("  🔍 [DRY RUN] Déplacerait: {$filename} → " . basename($targetPath));
                    $totalMoved++;
                }
            }
            
            // Supprimer le dossier source s'il est vide
            if (!$dryRun) {
                $remainingImages = File::glob($oldPath . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
                if (empty($remainingImages)) {
                    File::deleteDirectory($oldPath);
                    $this->info("✅ Dossier supprimé: {$oldPath}");
                }
            } else {
                $this->info("🔍 [DRY RUN] Supprimerait le dossier: {$oldPath}");
            }
        }
        
        if ($dryRun) {
            $this->newLine();
            $this->info("🔍 [DRY RUN] Total: {$totalMoved} image(s) seraient déplacées");
            $this->info("Pour exécuter réellement, relancez la commande sans --dry-run");
        } else {
            $this->newLine();
            $this->info("✅ Réorganisation terminée: {$totalMoved} image(s) déplacée(s) vers Side");
        }
        
        return 0;
    }
}


