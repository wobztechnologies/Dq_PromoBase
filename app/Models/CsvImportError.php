<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvImportError extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'csv_import_id',
        'row_number',
        'row_data',
        'error_type',
        'error_message',
        'error_context',
    ];

    protected $casts = [
        'row_data' => 'array',
        'error_context' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($error) {
            if (empty($error->id)) {
                $error->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function csvImport(): BelongsTo
    {
        return $this->belongsTo(CsvImport::class);
    }
}
