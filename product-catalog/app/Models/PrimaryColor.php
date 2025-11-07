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
        'hex_code',
        'parent_id',
    ];

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
