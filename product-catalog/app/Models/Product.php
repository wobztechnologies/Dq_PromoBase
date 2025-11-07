<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, Searchable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'manufacturer_id',
    ];

    protected static function booted(): void
    {
        // Pas de gestion de fichiers S3 au niveau du produit (géré par les variantes)
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function colorVariants(): HasMany
    {
        return $this->hasMany(ProductColorVariant::class);
    }

    public function distributors(): HasMany
    {
        return $this->hasMany(ProductDistributor::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Image par défaut du produit
     */
    public function defaultImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_default', true);
    }

    /**
     * Modèles 3D du produit
     */
    public function models3d(): HasMany
    {
        return $this->hasMany(ProductModel3D::class);
    }

    /**
     * Modèle 3D par défaut du produit
     */
    public function defaultModel3D(): HasOne
    {
        return $this->hasOne(ProductModel3D::class)->where('is_default', true);
    }

    public function toSearchableArray(): array
    {
        $this->load(['category', 'manufacturer', 'colorVariants.primaryColor.parent']);
        
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category?->name,
            'manufacturer' => $this->manufacturer?->name,
            'colors' => $this->colorVariants->map(function ($variant) {
                return $variant->primaryColor->full_name ?? $variant->primaryColor->name;
            })->filter()->toArray(),
        ];
    }
}
