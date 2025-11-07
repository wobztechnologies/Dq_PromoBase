<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'model_3d' => $this->model_3d_s3_url,
            'images' => $this->whenLoaded('images', fn() => $this->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => $image->signed_url, // URL présignée valide 24h
                ];
            })),
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'manufacturer' => $this->whenLoaded('manufacturer', fn() => [
                'id' => $this->manufacturer->id,
                'name' => $this->manufacturer->name,
            ]),
            'variants' => $this->whenLoaded('colorVariants', fn() => $this->colorVariants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'color' => $variant->primaryColor ? [
                        'id' => $variant->primaryColor->id,
                        'name' => $variant->primaryColor->name,
                        'hex_code' => $variant->primaryColor->hex_code,
                    ] : null,
                ];
            })),
            'distributors' => $this->whenLoaded('distributors', fn() => $this->distributors->map(function ($distributor) {
                return [
                    'id' => $distributor->id,
                    'sku' => $distributor->sku_distributor,
                    'distributor' => $distributor->distributor ? [
                        'id' => $distributor->distributor->id,
                        'name' => $distributor->distributor->name,
                    ] : null,
                ];
            })),
        ];
    }
}
