<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvImportLog extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'csv_import_id',
        'row_number',
        'sku',
        'level',
        'message',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($log) {
            if (empty($log->id)) {
                $log->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function csvImport(): BelongsTo
    {
        return $this->belongsTo(CsvImport::class, 'csv_import_id');
    }

    // Scopes
    public function scopeErrors($query)
    {
        return $query->where('level', 'error');
    }

    public function scopeWarnings($query)
    {
        return $query->where('level', 'warning');
    }

    public function scopeInfo($query)
    {
        return $query->where('level', 'info');
    }
}
