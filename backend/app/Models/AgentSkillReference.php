<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSkillReference extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'skill_id',
        'title',
        'content',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AgentSkill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(AgentSkill::class, 'skill_id');
    }
}
