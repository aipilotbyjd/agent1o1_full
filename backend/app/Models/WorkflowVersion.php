<?php

namespace App\Models;

use App\Exceptions\ApiException;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowVersion extends Model
{
    /** @use HasFactory<\Database\Factories\WorkflowVersionFactory> */
    use HasFactory, HasUuid;

    protected static function booted(): void
    {
        static::saving(function (self $version) {
            if (
                $version->exists
                && $version->is_published
                && $version->isDirty(['nodes', 'edges', 'trigger_config', 'trigger_type'])
            ) {
                throw new ApiException(
                    'Published versions are immutable. Create a new version instead.',
                    422
                );
            }
        });
    }

    protected $fillable = [
        'workflow_id',
        'version_number',
        'name',
        'description',
        'trigger_type',
        'trigger_config',
        'nodes',
        'edges',
        'viewport',
        'settings',
        'created_by',
        'change_summary',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'trigger_config' => 'array',
            'nodes' => 'array',
            'edges' => 'array',
            'viewport' => 'array',
            'settings' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
