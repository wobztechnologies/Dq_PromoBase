<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrimaryColor extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'translations',
        'hex_code',
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
}
