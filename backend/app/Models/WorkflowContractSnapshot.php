<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowContractSnapshot extends Model
{
    use HasUuid;

    protected $fillable = [
        'workflow_id',
        'workflow_version_id',
        'graph_hash',
        'status',
        'contracts',
        'issues',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'contracts' => 'array',
            'issues' => 'array',
            'generated_at' => 'datetime',
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
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<WorkflowContractTestRun, $this>
     */
    public function testRuns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkflowContractTestRun::class, 'workflow_contract_snapshot_id');
    }
}
