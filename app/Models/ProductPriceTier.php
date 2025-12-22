<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceTier extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_variant_price_id',
        'quantity_min',
        'quantity_max',
        'unit_price',
        'currency',
    ];

    protected $casts = [
        'quantity_min' => 'integer',
        'quantity_max' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Générer un UUID automatiquement lors de la création
        static::creating(function ($tier) {
            if (empty($tier->id)) {
                $tier->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Variation complète avec distributeur
     */
    public function variantPrice(): BelongsTo
    {
        return $this->belongsTo(ProductVariantPrice::class);
    }
}
