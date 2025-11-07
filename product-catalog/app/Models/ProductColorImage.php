<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductColorImage extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'variant_id',
        's3_url',
    ];

    protected static function booted(): void
    {
        // Générer un UUID automatiquement lors de la création
        static::creating(function ($image) {
            if (empty($image->id)) {
                $image->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductColorVariant::class, 'variant_id');
    }

    /**
     * Obtenir l'URL présignée de l'image S3
     */
    public function getSignedUrlAttribute(): ?string
    {
        if (!$this->s3_url) {
            return null;
        }

        try {
            // Générer une URL présignée valide pendant 24 heures
            return Storage::disk('s3')->temporaryUrl($this->s3_url, now()->addHours(24));
        } catch (\Exception $e) {
            // En cas d'erreur, retourner l'URL directe
            return Storage::disk('s3')->url($this->s3_url);
        }
    }
}
