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
     * Obtenir l'URL complète du logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_s3_url) {
            return null;
        }

        return Storage::disk('s3')->url($this->logo_s3_url);
    }
}
