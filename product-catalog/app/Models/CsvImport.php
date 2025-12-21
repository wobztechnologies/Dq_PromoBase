<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CsvImport extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'type',
        'mode',
        'strategy',
        'file_path',
        's3_archive_path',
        'report_path',
        'status',
        'validation_errors',
        'column_mapping',
        'value_mappings',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'created_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'validation_errors' => 'array',
        'column_mapping' => 'array',
        'value_mappings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($import) {
            if (empty($import->id)) {
                $import->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(CsvImportMapping::class, 'csv_import_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CsvImportLog::class, 'csv_import_id');
    }

    // Scopes
    public function scopePendingValidation($query)
    {
        return $query->where('status', 'pending_validation');
    }

    public function scopeValidationFailed($query)
    {
        return $query->where('status', 'validation_failed');
    }

    public function scopePendingMatching($query)
    {
        return $query->where('status', 'pending_matching');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Méthodes utilitaires
    public function markValidationFailed(array $errors): void
    {
        $this->update([
            'status' => 'validation_failed',
            'validation_errors' => $errors,
        ]);
    }

    public function markPendingMatching(): void
    {
        $this->update(['status' => 'pending_matching']);
    }

    public function markMatchingCompleted(): void
    {
        $this->update(['status' => 'matching_completed']);
    }

    public function start(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function fail(): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }

    public function addLog(string $level, string $message, ?array $data = null, ?int $rowNumber = null, ?string $sku = null): void
    {
        $this->logs()->create([
            'level' => $level,
            'message' => $message,
            'data' => $data,
            'row_number' => $rowNumber,
            'sku' => $sku,
        ]);
    }

    public function incrementProcessed(): void
    {
        $this->increment('processed_rows');
    }

    public function incrementSuccessful(): void
    {
        $this->increment('successful_rows');
    }

    public function incrementFailed(): void
    {
        $this->increment('failed_rows');
    }
}
