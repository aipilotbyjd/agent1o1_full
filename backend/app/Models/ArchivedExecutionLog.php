<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivedExecutionLog extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'execution_id',
        'workflow_id',
        'workflow_name',
        'status',
        'mode',
        'started_at',
        'finished_at',
        'triggered_by',
        's3_bucket',
        's3_key',
        's3_region',
        's3_storage_class',
        'archived_at',
        'file_size_bytes',
        'compressed_size_bytes',
        'compression_ratio',
        'is_restored',
        'restored_at',
        'restore_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'archived_at' => 'datetime',
            'restored_at' => 'datetime',
            'restore_expires_at' => 'datetime',
            'is_restored' => 'boolean',
            'file_size_bytes' => 'integer',
            'compressed_size_bytes' => 'integer',
            'compression_ratio' => 'decimal:4',
        ];
    }

    /**
     * Workspace relationship
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Workflow relationship (nullable - workflow may be deleted)
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Triggered by user relationship (nullable)
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * Get human-readable file size
     */
    public function getHumanFileSizeAttribute(): string
    {
        return $this->formatBytes($this->file_size_bytes);
    }

    /**
     * Get human-readable compressed size
     */
    public function getHumanCompressedSizeAttribute(): string
    {
        return $this->formatBytes($this->compressed_size_bytes);
    }

    /**
     * Get compression percentage
     */
    public function getCompressionPercentageAttribute(): float
    {
        if ($this->file_size_bytes === 0) {
            return 0;
        }

        return round((1 - $this->compression_ratio) * 100, 2);
    }

    /**
     * Format bytes to human-readable size
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2).' '.$units[$i];
    }
}
