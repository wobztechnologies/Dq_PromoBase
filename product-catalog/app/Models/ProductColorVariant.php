<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ProductColorVariant extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'primary_color_id',
        'sku',
    ];

    protected static function booted(): void
    {
        // Générer un UUID automatiquement lors de la création
        static::creating(function ($variant) {
            if (empty($variant->id)) {
                $variant->id = (string) \Illuminate\Support\Str::uuid();
            }
        });

        // Pas de gestion de fichiers S3 au niveau de la variante (géré par ProductModel3D)
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function primaryColor(): BelongsTo
    {
        return $this->belongsTo(PrimaryColor::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductColorImage::class, 'variant_id');
    }

    /**
     * Images de produit associées à cette variante
     */
    public function productImages(): BelongsToMany
    {
        return $this->belongsToMany(ProductImage::class, 'product_image_color_variant', 'product_color_variant_id', 'product_image_id')
            ->withTimestamps();
    }

    /**
     * Modèles 3D associés à cette variante
     */
    public function models3d(): BelongsToMany
    {
        return $this->belongsToMany(ProductModel3D::class, 'product_model_3d_color_variant', 'product_color_variant_id', 'product_model_3d_id')
            ->withTimestamps();
    }
}
