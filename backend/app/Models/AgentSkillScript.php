<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSkillScript extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'skill_id',
        'name',
        'description',
        'language',
        'code',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
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
