<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class ProductModel3D extends Model
{
    use HasFactory;

    protected $table = 'product_3d_models';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        's3_url',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Générer un UUID automatiquement lors de la création
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }

            // Si c'est le premier modèle 3D du produit, le définir comme modèle par défaut
            if ($model->product_id && !$model->is_default) {
                $existingModelsCount = static::where('product_id', $model->product_id)->count();
                if ($existingModelsCount === 0) {
                    $model->is_default = true;
                }
            }
        });

        // Supprimer le fichier S3 lors de la suppression du modèle
        static::deleting(function ($model) {
            if ($model->s3_url) {
                Storage::disk('s3')->delete($model->s3_url);
            }
        });

        // Supprimer l'ancien fichier S3 lors de la mise à jour
        static::updating(function ($model) {
            if ($model->isDirty('s3_url') && $model->getOriginal('s3_url')) {
                Storage::disk('s3')->delete($model->getOriginal('s3_url'));
            }
        });

        // S'assurer qu'un seul modèle est par défaut par produit
        static::saving(function ($model) {
            if ($model->is_default) {
                // Désactiver tous les autres modèles par défaut pour ce produit
                static::where('product_id', $model->product_id)
                    ->where('id', '!=', $model->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variantes de couleur associées à ce modèle 3D
     */
    public function colorVariants(): BelongsToMany
    {
        return $this->belongsToMany(ProductColorVariant::class, 'product_model_3d_color_variant', 'product_model_3d_id', 'product_color_variant_id')
            ->withTimestamps();
    }

    /**
     * Obtenir l'URL présignée du modèle 3D S3
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
