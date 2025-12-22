<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'translations',
        'parent_id',
        'path',
        'order',
        'image_s3_url',
    ];

    protected $casts = [
        'path' => 'string',
        'translations' => 'array',
    ];

    protected static function booted(): void
    {
        // Générer un UUID et calculer le path ltree lors de la création
        static::creating(function ($category) {
            // Générer un UUID si nécessaire
            if (empty($category->id)) {
                $category->id = (string) \Illuminate\Support\Str::uuid();
            }
            
            // Calculer automatiquement le path ltree
            if ($category->parent_id) {
                $parent = static::find($category->parent_id);
                if ($parent && $parent->path) {
                    // Sous-catégorie : parent.path.id
                    $category->path = $parent->path . '.' . str_replace('-', '_', $category->id);
                } else {
                    // Parent introuvable ou sans path, utiliser juste l'ID
                    $category->path = str_replace('-', '_', $category->id);
                }
            } else {
                // Catégorie racine : juste l'ID
                $category->path = str_replace('-', '_', $category->id);
            }
        });

        // Recalculer le path lors de la mise à jour si le parent change
        // + Supprimer l'ancienne image S3 si elle change
        static::updating(function ($category) {
            // Gestion du changement de parent
            if ($category->isDirty('parent_id')) {
                if ($category->parent_id) {
                    $parent = static::find($category->parent_id);
                    if ($parent && $parent->path) {
                        $category->path = $parent->path . '.' . str_replace('-', '_', $category->id);
                    } else {
                        $category->path = str_replace('-', '_', $category->id);
                    }
                } else {
                    // Devenir une catégorie racine
                    $category->path = str_replace('-', '_', $category->id);
                }

                // Mettre à jour les paths de tous les enfants
                static::updateChildrenPaths($category);
            }

            // Supprimer l'ancienne image S3 si elle change
            if ($category->isDirty('image_s3_url') && $category->getOriginal('image_s3_url')) {
                Storage::disk('s3')->delete($category->getOriginal('image_s3_url'));
            }
        });

        // Supprimer l'image S3 lors de la suppression de la catégorie
        static::deleting(function ($category) {
            if ($category->image_s3_url) {
                Storage::disk('s3')->delete($category->image_s3_url);
            }
        });
    }

    /**
     * Mettre à jour les paths de tous les enfants récursivement
     */
    protected static function updateChildrenPaths(Category $category): void
    {
        $children = static::where('parent_id', $category->id)->get();
        foreach ($children as $child) {
            $child->path = $category->path . '.' . str_replace('-', '_', $child->id);
            $child->saveQuietly(); // saveQuietly pour éviter les hooks récursifs
            static::updateChildrenPaths($child);
        }
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
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
     * Obtenir l'URL présignée de l'image (valide 24h)
     */
    public function getImageSignedUrlAttribute(): ?string
    {
        if (!$this->image_s3_url) {
            return null;
        }

        try {
            // Générer une URL présignée valide pendant 24 heures
            return Storage::disk('s3')->temporaryUrl($this->image_s3_url, now()->addHours(24));
        } catch (\Exception $e) {
            // En cas d'erreur, retourner l'URL directe
            return Storage::disk('s3')->url($this->image_s3_url);
        }
    }
}
