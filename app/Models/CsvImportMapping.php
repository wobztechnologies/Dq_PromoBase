<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvImportMapping extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'source_value',
        'target_type',
        'target_id',
        'target_name',
        'mapping_type',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function ($mapping) {
            if (empty($mapping->id)) {
                $mapping->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Trouver un mapping existant pour réutilisation
     */
    public static function findExisting(string $mappingType, string $sourceValue): ?self
    {
        return static::where('mapping_type', $mappingType)
            ->where('source_value', $sourceValue)
            ->latest()
            ->first();
    }

    /**
     * Créer ou récupérer un mapping
     */
    public static function createOrGet(
        string $mappingType,
        string $sourceValue,
        string $targetType,
        string $targetId,
        ?string $targetName = null,
        ?int $createdBy = null
    ): self {
        $existing = static::findExisting($mappingType, $sourceValue);
        
        if ($existing) {
            // Mettre à jour si nécessaire
            if ($existing->target_id !== $targetId || $existing->target_type !== $targetType) {
                $existing->update([
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'target_name' => $targetName,
                ]);
            }
            return $existing;
        }

        return static::create([
            'source_value' => $sourceValue,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'mapping_type' => $mappingType,
            'created_by' => $createdBy,
        ]);
    }
}
