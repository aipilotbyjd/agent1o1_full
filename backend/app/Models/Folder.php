<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    /** @use HasFactory<\Database\Factories\FolderFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'workspace_id',
        'parent_id',
        'name',
        'color',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Workflow, $this>
     */
    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }
}
