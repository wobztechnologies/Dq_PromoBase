<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSizeVariant extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'product_color_variant_id',
        'size_id',
        'sku',
    ];

    protected static function booted(): void
    {
        // Générer un UUID automatiquement lors de la création
        static::creating(function ($variant) {
            if (empty($variant->id)) {
                $variant->id = (string) \Illuminate\Support\Str::uuid();
            }
            
            // Validation : au moins un des deux doit être défini
            if (empty($variant->product_id) && empty($variant->product_color_variant_id)) {
                throw new \Exception('Une variante de taille doit être liée soit à un produit, soit à une variante de couleur.');
            }
        });
        
        // Validation lors de la mise à jour
        static::updating(function ($variant) {
            // Si les deux sont null après la mise à jour, c'est invalide
            if (empty($variant->product_id) && empty($variant->product_color_variant_id)) {
                throw new \Exception('Une variante de taille doit être liée soit à un produit, soit à une variante de couleur.');
            }
        });
    }

    /**
     * Produit associé (si la variante de taille est directement sur le produit)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variante de couleur associée (si la variante de taille est sur une variante de couleur)
     */
    public function colorVariant(): BelongsTo
    {
        return $this->belongsTo(ProductColorVariant::class, 'product_color_variant_id');
    }

    /**
     * Taille associée
     */
    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    /**
     * Prix et stock pour cette variante de taille
     */
    public function variantPrices(): HasMany
    {
        return $this->hasMany(ProductVariantPrice::class);
    }
}
