<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DownloadProductImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Product $product,
        public array $imageUrls
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $existingImagesCount = ProductImage::where('product_id', $this->product->id)->count();
        $position = $existingImagesCount + 1;

        foreach ($this->imageUrls as $imageUrl) {
            if ($position > 8) {
                Log::warning('Too many images for product', [
                    'product_id' => $this->product->id,
                    'sku' => $this->product->sku,
                ]);
                break;
            }

            try {
                $s3Path = $this->downloadImage($imageUrl, $this->product->id, $position);
                if ($s3Path) {
                    ProductImage::create([
                        'product_id' => $this->product->id,
                        's3_url' => $s3Path,
                        'position' => $position,
                        'is_default' => $position === 1 && $existingImagesCount === 0,
                    ]);
                    $position++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to download product image', [
                    'product_id' => $this->product->id,
                    'image_url' => $imageUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function downloadImage(string $url, string $productId, int $position): ?string
    {
        try {
            $imageContent = file_get_contents($url);
            if ($imageContent === false) {
                return null;
            }

            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $fileName = "{$productId}_{$position}.{$extension}";
            $s3Path = "products/images/{$fileName}";

            Storage::disk('s3')->put($s3Path, $imageContent);
            return $s3Path;
        } catch (\Exception $e) {
            Log::error('Image download failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
