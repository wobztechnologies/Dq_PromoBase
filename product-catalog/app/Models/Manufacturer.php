<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Manufacturer extends Model
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
        // Supprimer le logo de S3 lors de la suppression du fabricant
        static::deleting(function ($manufacturer) {
            if ($manufacturer->logo_s3_url) {
                Storage::disk('s3')->delete($manufacturer->logo_s3_url);
            }
        });

        // Supprimer l'ancien logo de S3 lors de la mise à jour
        static::updating(function ($manufacturer) {
            if ($manufacturer->isDirty('logo_s3_url') && $manufacturer->getOriginal('logo_s3_url')) {
                Storage::disk('s3')->delete($manufacturer->getOriginal('logo_s3_url'));
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function manufacturerColors(): HasMany
    {
        return $this->hasMany(PrimaryColor::class, 'manufacturer_id')->whereNotNull('parent_id');
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
