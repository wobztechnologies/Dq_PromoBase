<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProductImage extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        's3_url',
        'position',
        'neutral_background',
        'is_default',
        'thumbnail_s3_url',
    ];

    protected $casts = [
        'neutral_background' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Générer un UUID automatiquement lors de la création
        static::creating(function ($image) {
            if (empty($image->id)) {
                $image->id = (string) \Illuminate\Support\Str::uuid();
            }

            // Si c'est la première image du produit, la définir comme image par défaut
            if ($image->product_id && !$image->is_default) {
                $existingImagesCount = static::where('product_id', $image->product_id)->count();
                if ($existingImagesCount === 0) {
                    $image->is_default = true;
                }
            }
        });

        // Supprimer le fichier S3 lors de la suppression de l'image
        static::deleting(function ($image) {
            if ($image->s3_url) {
                Storage::disk('s3')->delete($image->s3_url);
            }
        });

        // Supprimer l'ancien fichier S3 lors de la mise à jour
        static::updating(function ($image) {
            if ($image->isDirty('s3_url') && $image->getOriginal('s3_url')) {
                Storage::disk('s3')->delete($image->getOriginal('s3_url'));
            }
        });

        // S'assurer qu'une seule image est par défaut par produit
        static::saving(function ($image) {
            if ($image->is_default) {
                // Désactiver toutes les autres images par défaut pour ce produit
                static::where('product_id', $image->product_id)
                    ->where('id', '!=', $image->id)
                    ->update(['is_default' => false]);
            }

            if (!$image->is_default && $image->isDirty('is_default') && $image->getOriginal('is_default')) {
                // Supprimer la miniature si l'image n'est plus par défaut
                if ($image->thumbnail_s3_url) {
                    Storage::disk('s3')->delete($image->thumbnail_s3_url);
                    $image->thumbnail_s3_url = null;
                }
            }
        });

        // Générer la miniature après la sauvegarde
        static::saved(function ($image) {
            if ($image->is_default && $image->s3_url) {
                // Générer la miniature WebP si elle n'existe pas ou si l'image a changé
                if (!$image->thumbnail_s3_url || $image->wasChanged('s3_url')) {
                    $image->generateThumbnail();
                    // Sauvegarder le chemin de la miniature sans déclencher les hooks
                    $image->updateQuietly(['thumbnail_s3_url' => $image->thumbnail_s3_url]);
                }
            }
        });

        // Supprimer la miniature lors de la suppression de l'image
        static::deleting(function ($image) {
            if ($image->thumbnail_s3_url) {
                Storage::disk('s3')->delete($image->thumbnail_s3_url);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variantes de couleur associées à cette image
     */
    public function colorVariants(): BelongsToMany
    {
        return $this->belongsToMany(ProductColorVariant::class, 'product_image_color_variant', 'product_image_id', 'product_color_variant_id')
            ->withTimestamps();
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

    /**
     * Obtenir l'URL présignée de la miniature S3
     */
    public function getThumbnailSignedUrlAttribute(): ?string
    {
        if (!$this->thumbnail_s3_url) {
            return null;
        }

        try {
            // Générer une URL présignée valide pendant 24 heures
            return Storage::disk('s3')->temporaryUrl($this->thumbnail_s3_url, now()->addHours(24));
        } catch (\Exception $e) {
            // En cas d'erreur, retourner l'URL directe
            return Storage::disk('s3')->url($this->thumbnail_s3_url);
        }
    }

    /**
     * Générer une miniature WebP de 150x150 et l'uploader sur S3
     */
    public function generateThumbnail(): void
    {
        if (!$this->s3_url) {
            return;
        }

        try {
            // Télécharger l'image depuis S3
            $imageContent = Storage::disk('s3')->get($this->s3_url);
            if (!$imageContent) {
                return;
            }

            // Créer le gestionnaire d'images
            $manager = new ImageManager(new Driver());

            // Créer l'image depuis le contenu
            $image = $manager->read($imageContent);

            // Redimensionner en 150x150 en conservant les proportions (cover)
            $image->cover(150, 150);

            // Encoder en WebP
            $webpContent = $image->toWebp(90);

            // Générer le chemin de la miniature
            $thumbnailPath = 'products/thumbnails/' . pathinfo($this->s3_url, PATHINFO_FILENAME) . '.webp';

            // Supprimer l'ancienne miniature si elle existe
            if ($this->thumbnail_s3_url && $this->thumbnail_s3_url !== $thumbnailPath) {
                Storage::disk('s3')->delete($this->thumbnail_s3_url);
            }

            // Uploader la nouvelle miniature sur S3
            Storage::disk('s3')->put($thumbnailPath, $webpContent, 'public');

            // Mettre à jour le chemin de la miniature
            $this->thumbnail_s3_url = $thumbnailPath;
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la génération de la miniature: ' . $e->getMessage());
        }
    }
}
