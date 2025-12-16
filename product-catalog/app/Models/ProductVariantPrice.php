<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariantPrice extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'product_color_variant_id',
        'product_size_variant_id',
        'distributor_id',
        'sku_distributor',
        'stock',
        'is_active',
        'last_updated_at',
    ];

    protected $casts = [
        'stock' => 'integer',
        'is_active' => 'boolean',
        'last_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Générer un UUID automatiquement lors de la création
        static::creating(function ($variantPrice) {
            if (empty($variantPrice->id)) {
                $variantPrice->id = (string) \Illuminate\Support\Str::uuid();
            }
            
            // Mettre à jour last_updated_at lors de la création
            if (empty($variantPrice->last_updated_at)) {
                $variantPrice->last_updated_at = now();
            }
        });
        
        // Mettre à jour last_updated_at lors de la mise à jour
        static::updating(function ($variantPrice) {
            if ($variantPrice->isDirty(['stock', 'sku_distributor', 'is_active'])) {
                $variantPrice->last_updated_at = now();
            }
        });
    }

    /**
     * Produit de base
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variante de couleur (nullable)
     */
    public function colorVariant(): BelongsTo
    {
        return $this->belongsTo(ProductColorVariant::class, 'product_color_variant_id');
    }

    /**
     * Variante de taille (nullable)
     */
    public function sizeVariant(): BelongsTo
    {
        return $this->belongsTo(ProductSizeVariant::class, 'product_size_variant_id');
    }

    /**
     * Distributeur
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * Grilles de prix (paliers de quantité)
     */
    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('quantity_min');
    }

    /**
     * Obtenir le prix unitaire pour une quantité donnée
     */
    public function getPriceForQuantity(int $quantity): ?float
    {
        $tier = $this->priceTiers()
            ->where('quantity_min', '<=', $quantity)
            ->where(function ($query) use ($quantity) {
                $query->whereNull('quantity_max')
                      ->orWhere('quantity_max', '>=', $quantity);
            })
            ->orderBy('quantity_min', 'desc')
            ->first();
        
        return $tier ? (float) $tier->unit_price : null;
    }

    /**
     * Obtenir le SKU complet de la variation
     */
    public function getVariantSkuAttribute(): string
    {
        if ($this->sizeVariant) {
            return $this->sizeVariant->sku;
        }
        if ($this->colorVariant) {
            return $this->colorVariant->sku;
        }
        return $this->product->sku;
    }
}
