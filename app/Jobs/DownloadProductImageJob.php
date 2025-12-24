<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductColorVariant;
use App\Models\CsvImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DownloadProductImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Nombre de tentatives
     */
    public int $tries = 3;

    /**
     * Délai entre les tentatives (en secondes)
     */
    public array $backoff = [30, 60, 120];

    /**
     * Timeout du job (2 minutes)
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $productId,
        public ?string $colorVariantId,
        public string $imageUrl,
        public int $position,
        public ?string $csvImportId = null
    ) {
        // Utiliser la queue 'images' pour isoler ces jobs
        $this->onQueue('images');
    }

    /**
     * Middleware pour limiter les jobs concurrents
     */
    public function middleware(): array
    {
        return [
            // Empêche les téléchargements dupliqués de la même URL
            (new WithoutOverlapping($this->imageUrl))->dontRelease(),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $product = Product::find($this->productId);
        if (!$product) {
            Log::warning("DownloadProductImageJob: Product {$this->productId} not found, skipping");
            return;
        }

        try {
            // Télécharger l'image avec timeout et retry HTTP
            $response = Http::timeout(60)
                ->retry(2, 1000)
                ->get($this->imageUrl);

            if (!$response->successful()) {
                throw new \Exception("HTTP {$response->status()} - Failed to download image from {$this->imageUrl}");
            }

            $contents = $response->body();
            if (empty($contents)) {
                throw new \Exception("Empty response from {$this->imageUrl}");
            }

            // Détecter l'extension depuis l'URL ou le Content-Type
            $extension = $this->getImageExtension($this->imageUrl, $response->header('Content-Type'));
            
            // Générer le nom du fichier unique
            $filename = Str::slug($product->sku) . '_' . time() . '_' . Str::random(8) . '.' . $extension;
            $path = "products/images/{$filename}";

            // Upload vers S3
            Storage::disk('s3')->put($path, $contents, 'public');

            // Créer l'entrée ProductImage
            $productImage = new ProductImage();
            $productImage->product_id = $this->productId;
            $productImage->s3_url = $path;
            $productImage->position = $this->getPositionName($this->position);
            $productImage->is_default = $this->position === 1;
            // Pour les imports CSV, les images sont gérées par l'utilisateur
            $productImage->management_type = 'user_managed';
            $productImage->status = 'userDefined';
            $productImage->save();

            // Associer à la variante de couleur si elle existe
            if ($this->colorVariantId) {
                $colorVariant = ProductColorVariant::find($this->colorVariantId);
                if ($colorVariant) {
                    $productImage->colorVariants()->syncWithoutDetaching([$this->colorVariantId]);
                }
            }

            // Logger le succès
            Log::info("DownloadProductImageJob: Image downloaded successfully", [
                'product_id' => $this->productId,
                'product_sku' => $product->sku,
                'url' => $this->imageUrl,
                's3_path' => $path,
                'position' => $this->getPositionName($this->position),
            ]);

            // Mettre à jour le log d'import si disponible
            if ($this->csvImportId) {
                $import = CsvImport::find($this->csvImportId);
                if ($import) {
                    $import->addLog('info', "Image téléchargée: {$this->imageUrl}", [], 0, $product->sku);
                }
            }

        } catch (\Exception $e) {
            Log::error("DownloadProductImageJob: Failed to download image", [
                'product_id' => $this->productId,
                'url' => $this->imageUrl,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Si c'est la dernière tentative, logger dans l'import
            if ($this->attempts() >= $this->tries && $this->csvImportId) {
                $import = CsvImport::find($this->csvImportId);
                if ($import) {
                    $import->addLog('warning', "Échec du téléchargement de l'image après {$this->tries} tentatives: {$this->imageUrl}", [], 0, $product->sku ?? null);
                }
            }

            throw $e; // Re-throw pour trigger le retry
        }
    }

    /**
     * Détermine l'extension de l'image
     */
    protected function getImageExtension(string $url, ?string $contentType): string
    {
        // Essayer depuis l'URL d'abord
        $urlPath = parse_url($url, PHP_URL_PATH);
        if ($urlPath) {
            $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                return $extension === 'jpeg' ? 'jpg' : $extension;
            }
        }

        // Sinon, utiliser le Content-Type
        if ($contentType) {
            $mimeToExt = [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
            ];

            foreach ($mimeToExt as $mime => $ext) {
                if (str_contains($contentType, $mime)) {
                    return $ext;
                }
            }
        }

        // Par défaut
        return 'jpg';
    }

    /**
     * Convertit la position numérique en nom
     */
    protected function getPositionName(int $position): string
    {
        return match($position) {
            1 => 'front',
            2 => 'back',
            3 => 'left',
            4 => 'right',
            5 => 'top',
            6 => 'bottom',
            default => 'detail',
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("DownloadProductImageJob: Job permanently failed", [
            'product_id' => $this->productId,
            'url' => $this->imageUrl,
            'error' => $exception->getMessage(),
        ]);
    }
}

