<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeProductImage;
use App\Models\ProductImage;
use Illuminate\Console\Command;

class ProcessWaitingMLImages extends Command
{
    protected $signature = 'ml:process-images {--limit= : Nombre maximum d\'images à traiter}';
    protected $description = 'Traiter toutes les images en attente d\'analyse ML (statut waitML)';

    public function handle()
    {
        $this->info('🤖 Traitement des images en attente d\'analyse ML...');

        // Récupérer toutes les images avec le statut waitML
        $query = ProductImage::where('status', 'waitML')
            ->whereNotNull('s3_url');

        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $images = $query->get();

        if ($images->isEmpty()) {
            $this->info('✅ Aucune image en attente de traitement ML');
            return 0;
        }

        $this->info("📸 {$images->count()} image(s) à traiter");

        $progressBar = $this->output->createProgressBar($images->count());
        $progressBar->start();

        $queued = 0;
        foreach ($images as $image) {
            try {
                // Dispatcher la job d'analyse ML
                AnalyzeProductImage::dispatch(
                    $image->id,
                    $image->s3_url,
                    $image->product_id
                );
                $queued++;
                $progressBar->advance();
            } catch (\Exception $e) {
                $this->error("\n❌ Erreur lors de la mise en queue de l'image {$image->id}: " . $e->getMessage());
            }
        }

        $progressBar->finish();

        $this->info("\n\n✅ {$queued} image(s) mise(s) en queue pour analyse ML");
        $this->info("ℹ️  Les images seront traitées par le worker de queue");

        return 0;
    }
}
