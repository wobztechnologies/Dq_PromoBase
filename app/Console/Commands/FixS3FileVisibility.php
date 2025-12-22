<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FixS3FileVisibility extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 's3:fix-visibility {--all : Rendre publics tous les fichiers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rendre publics les fichiers S3 existants (modèles 3D, images, logos)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Correction de la visibilité des fichiers S3...');

        $fixed = 0;
        $errors = 0;

        // Modèles 3D des produits
        $products = Product::whereNotNull('model_3d_s3_url')->get();
        foreach ($products as $product) {
            try {
                if (Storage::disk('s3')->exists($product->model_3d_s3_url)) {
                    Storage::disk('s3')->setVisibility($product->model_3d_s3_url, 'public');
                    $fixed++;
                    $this->line("✓ Modèle 3D: {$product->model_3d_s3_url}");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("✗ Erreur pour {$product->model_3d_s3_url}: {$e->getMessage()}");
            }
        }

        // Images supplémentaires
        foreach (Product::with('images')->get() as $product) {
            foreach ($product->images as $image) {
                try {
                    if (Storage::disk('s3')->exists($image->s3_url)) {
                        Storage::disk('s3')->setVisibility($image->s3_url, 'public');
                        $fixed++;
                        $this->line("✓ Image produit: {$image->s3_url}");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("✗ Erreur pour {$image->s3_url}: {$e->getMessage()}");
                }
            }
        }

        // Logos des fabricants
        foreach (\App\Models\Manufacturer::whereNotNull('logo_s3_url')->get() as $manufacturer) {
            try {
                if (Storage::disk('s3')->exists($manufacturer->logo_s3_url)) {
                    Storage::disk('s3')->setVisibility($manufacturer->logo_s3_url, 'public');
                    $fixed++;
                    $this->line("✓ Logo fabricant: {$manufacturer->logo_s3_url}");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("✗ Erreur pour {$manufacturer->logo_s3_url}: {$e->getMessage()}");
            }
        }

        // Logos des distributeurs
        foreach (\App\Models\Distributor::whereNotNull('logo_s3_url')->get() as $distributor) {
            try {
                if (Storage::disk('s3')->exists($distributor->logo_s3_url)) {
                    Storage::disk('s3')->setVisibility($distributor->logo_s3_url, 'public');
                    $fixed++;
                    $this->line("✓ Logo distributeur: {$distributor->logo_s3_url}");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("✗ Erreur pour {$distributor->logo_s3_url}: {$e->getMessage()}");
            }
        }

        $this->info("\n✅ {$fixed} fichier(s) rendu(s) public(s)");
        if ($errors > 0) {
            $this->warn("⚠ {$errors} erreur(s) rencontrée(s)");
        }
    }
}
