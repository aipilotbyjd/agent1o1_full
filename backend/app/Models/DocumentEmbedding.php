<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentEmbedding extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentEmbeddingFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'workspace_id',
        'collection_name',
        'document_id',
        'chunk_index',
        'content',
        'embedding',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'chunk_index' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
