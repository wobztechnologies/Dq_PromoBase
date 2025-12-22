<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Services\ImageAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $imageId;
    public string $s3Url;
    public ?string $productId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $imageId, string $s3Url, ?string $productId = null)
    {
        $this->imageId = $imageId;
        $this->s3Url = $s3Url;
        $this->productId = $productId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Augmenter la limite de mémoire pour l'analyse ML
        ini_set('memory_limit', '2048M');
        
        try {
            // Recharger l'image depuis la base
            $image = ProductImage::find($this->imageId);
            if (!$image) {
                Log::warning("Image non trouvée pour l'analyse: {$this->imageId}");
                return;
            }

            $analysisService = app(ImageAnalysisService::class);
            $analysis = $analysisService->analyzeImage($this->s3Url);

            // Logger les résultats de l'analyse pour debug
            Log::info("Analyse ML pour image {$this->imageId}", [
                'position' => $analysis['position'] ?? 'null',
                'neutral_background' => $analysis['neutral_background'] ?? false,
                'product_only' => $analysis['product_only'] ?? false,
                'dominant_color' => $analysis['dominant_color'] ?? 'null',
            ]);

            // Mettre à jour l'image avec les résultats de l'analyse
            $updates = [];

            if ($analysis['position'] && !$image->position) {
                $updates['position'] = $analysis['position'];
            }

            if (isset($analysis['neutral_background']) && $image->neutral_background === false) {
                $updates['neutral_background'] = $analysis['neutral_background'];
            }

            if (isset($analysis['product_only']) && $image->product_only === false) {
                $updates['product_only'] = $analysis['product_only'];
            }

            if (!empty($updates)) {
                $image->updateQuietly($updates);
            }

            // Mettre à jour le statut à MLcompleted
            $image->updateQuietly(['status' => 'MLcompleted']);

            Log::info("Image {$this->imageId} analysée avec succès par le ML");

            // Si une couleur dominante est détectée, créer automatiquement une variante
            if ($analysis['dominant_color'] && $this->productId) {
                try {
                    // Vérifier si ColorMappingService existe
                    if (class_exists(\App\Services\ColorMappingService::class)) {
                        $colorMappingService = app(\App\Services\ColorMappingService::class);
                        $primaryColor = $colorMappingService->findClosestPrimaryColor($analysis['dominant_color']);

                        if ($primaryColor) {
                            $product = \App\Models\Product::find($this->productId);
                            if ($product && $product->sku) {
                                $colorName = strtoupper(substr($primaryColor->name, 0, 3));
                                $variantSku = $product->sku . '-' . $colorName;

                                $existingVariant = \App\Models\ProductColorVariant::where('product_id', $this->productId)
                                    ->where('sku', $variantSku)
                                    ->first();

                                if (!$existingVariant) {
                                    $variant = \App\Models\ProductColorVariant::create([
                                        'id' => (string) \Illuminate\Support\Str::uuid(),
                                        'product_id' => $this->productId,
                                        'primary_color_id' => $primaryColor->id,
                                        'sku' => $variantSku,
                                    ]);

                                    // Associer l'image à la variante créée
                                    $image->colorVariants()->attach($variant->id);

                                    Log::info("Variante de couleur créée automatiquement: {$variantSku} et associée à l'image {$this->imageId}");
                                } else {
                                    // Si la variante existe déjà, vérifier si l'image est déjà associée
                                    if (!$image->colorVariants()->where('product_color_variant_id', $existingVariant->id)->exists()) {
                                        $image->colorVariants()->attach($existingVariant->id);
                                        Log::info("Image {$this->imageId} associée à la variante existante: {$variantSku}");
                                    }
                                }
                            }
                        }
                    } else {
                        Log::info("Couleur dominante détectée pour l'image {$this->imageId}: {$analysis['dominant_color']}");
                    }
                } catch (\Exception $e) {
                    Log::error("Erreur lors de la création automatique de variante: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'analyse automatique de l\'image: ' . $e->getMessage());
            throw $e; // Re-throw pour que la queue puisse gérer les retries
        }
    }
}
