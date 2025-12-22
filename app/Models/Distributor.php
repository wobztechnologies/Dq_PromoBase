<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Distributor extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'logo_s3_url',
    ];

    protected static function booted(): void
    {
        // Générer un UUID lors de la création
        static::creating(function ($distributor) {
            if (empty($distributor->id)) {
                $distributor->id = (string) \Illuminate\Support\Str::uuid();
            }
        });

        // Supprimer le logo de S3 lors de la suppression du distributeur
        static::deleting(function ($distributor) {
            if ($distributor->logo_s3_url) {
                Storage::disk('s3')->delete($distributor->logo_s3_url);
            }
        });

        // Supprimer l'ancien logo de S3 lors de la mise à jour
        static::updating(function ($distributor) {
            if ($distributor->isDirty('logo_s3_url') && $distributor->getOriginal('logo_s3_url')) {
                Storage::disk('s3')->delete($distributor->getOriginal('logo_s3_url'));
            }
        });
    }

    public function productDistributors(): HasMany
    {
        return $this->hasMany(ProductDistributor::class);
    }

    /**
     * Prix et stock pour toutes les variations de ce distributeur
     */
    public function variantPrices(): HasMany
    {
        return $this->hasMany(ProductVariantPrice::class);
    }

    /**
     * Obtenir l'URL complète du logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_s3_url) {
            return null;
        }

        return Storage::disk('s3')->url($this->logo_s3_url);
    }

    /**
     * Obtenir l'URL présignée du logo
     */
    public function getLogoSignedUrlAttribute(): ?string
    {
        if (!$this->logo_s3_url) {
            return null;
        }

        try {
            // Générer une URL présignée valide pendant 24 heures
            return Storage::disk('s3')->temporaryUrl($this->logo_s3_url, now()->addHours(24));
        } catch (\Exception $e) {
            // En cas d'erreur, retourner l'URL directe
            return Storage::disk('s3')->url($this->logo_s3_url);
        }
    }
}
