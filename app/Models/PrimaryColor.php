<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class PrimaryColor extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'translations',
        'hex_code',
        'image_s3_url',
        'color_sku_code',
        'rgb',
        'pantone_c',
        'pantone_tcx',
        'pms',
        'parent_id',
        'manufacturer_id',
    ];

    protected $casts = [
        'translations' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($color) {
            if (empty($color->id)) {
                $color->id = (string) \Illuminate\Support\Str::uuid();
            }
        });

        // Supprimer l'image S3 lors de la suppression de la couleur
        static::deleting(function ($color) {
            if ($color->image_s3_url) {
                Storage::disk('s3')->delete($color->image_s3_url);
            }
        });

        // Supprimer l'ancienne image S3 lors de la mise à jour
        static::updating(function ($color) {
            if ($color->isDirty('image_s3_url') && $color->getOriginal('image_s3_url')) {
                Storage::disk('s3')->delete($color->getOriginal('image_s3_url'));
            }
        });

        // Redimensionner et convertir l'image en WebP 100x100 après la sauvegarde
        static::saved(function ($color) {
            if ($color->wasChanged('image_s3_url') && $color->image_s3_url) {
                $color->processColorImage();
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PrimaryColor::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PrimaryColor::class, 'parent_id');
    }

    public function colorVariants(): HasMany
    {
        return $this->hasMany(ProductColorVariant::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * Obtenir le nom traduit selon la locale courante
     */
    public function getNameAttribute($value): string
    {
        $locale = app()->getLocale();
        $translations = $this->translations ?? [];
        
        if (!empty($translations) && isset($translations[$locale])) {
            return $translations[$locale];
        }
        
        // Fallback sur le nom original de la base de données
        return $value ?? '';
    }

    /**
     * Obtenir le nom traduit pour une locale spécifique
     */
    public function getTranslatedName(string $locale): ?string
    {
        $translations = $this->translations;
        return $translations[$locale] ?? null;
    }

    /**
     * Définir une traduction pour une locale
     */
    public function setTranslation(string $locale, string $name): void
    {
        $translations = $this->translations ?? [];
        $translations[$locale] = $name;
        $this->translations = $translations;
    }

    /**
     * Obtenir le nom complet avec la hiérarchie
     */
    public function getFullNameAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->name . ' ' . $this->name;
        }
        return $this->name;
    }

    /**
     * Scope pour les couleurs principales (sans parent)
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope pour les sous-couleurs (avec parent)
     */
    public function scopeSubColors($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Traiter l'image de couleur : redimensionner en 100x100 et convertir en WebP
     */
    public function processColorImage(): void
    {
        if (!$this->image_s3_url) {
            return;
        }

        try {
            // Télécharger l'image depuis S3
            $imageContent = Storage::disk('s3')->get($this->image_s3_url);
            if (!$imageContent) {
                return;
            }

            // Créer le gestionnaire d'images
            $manager = new ImageManager(new Driver());

            // Créer l'image depuis le contenu
            $image = $manager->read($imageContent);

            // Redimensionner en 100x100 en conservant les proportions (cover)
            $image->cover(100, 100);

            // Encoder en WebP
            $webpContent = $image->toWebp(90);

            // Générer le chemin de l'image redimensionnée (toujours en .webp)
            $pathInfo = pathinfo($this->image_s3_url);
            $directory = $pathInfo['dirname'];
            $filename = $pathInfo['filename'];
            $resizedPath = $directory . '/' . $filename . '.webp';

            // Sauvegarder le chemin original pour suppression ultérieure
            $originalPath = $this->image_s3_url;

            // Uploader la nouvelle image redimensionnée sur S3
            Storage::disk('s3')->put($resizedPath, $webpContent, 'public');

            // Si le chemin a changé, supprimer l'ancien fichier et mettre à jour
            if ($originalPath !== $resizedPath) {
                Storage::disk('s3')->delete($originalPath);
                $this->updateQuietly(['image_s3_url' => $resizedPath]);
            } else {
                // Même chemin, juste mettre à jour le contenu (déjà fait avec put)
                // Pas besoin de mettre à jour image_s3_url car c'est déjà le bon chemin
            }
        } catch (\Exception $e) {
            \Log::error('Erreur lors du traitement de l\'image de couleur: ' . $e->getMessage());
        }
    }

    /**
     * Obtenir l'URL complète de l'image
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_s3_url) {
            return null;
        }

        return Storage::disk('s3')->url($this->image_s3_url);
    }

    /**
     * Obtenir l'URL présignée de l'image
     */
    public function getImageSignedUrlAttribute(): ?string
    {
        if (!$this->image_s3_url) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl($this->image_s3_url, now()->addHours(24));
    }
}
