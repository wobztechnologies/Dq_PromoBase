<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'order',
    ];

    protected static function booted(): void
    {
        static::creating(function ($size) {
            if (empty($size->id)) {
                $size->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function sizeVariants(): HasMany
    {
        return $this->hasMany(ProductSizeVariant::class);
    }
}
