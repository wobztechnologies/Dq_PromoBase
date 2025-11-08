<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'parent_id',
        'path',
    ];

    protected $casts = [
        'path' => 'string',
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
        static::updating(function ($category) {
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
}
