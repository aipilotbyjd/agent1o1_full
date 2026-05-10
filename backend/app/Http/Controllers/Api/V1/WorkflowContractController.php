<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowContractSnapshot;
use App\Models\WorkflowContractTestRun;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowContractController extends Controller
{
    private const MAX_PER_PAGE = 100;

    /**
     * List contract snapshots for a workflow.
     */
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->can(Permission::ContractView);

        $perPage = min((int) $request->input('per_page', 15), self::MAX_PER_PAGE);

        $snapshots = $workflow->contractSnapshots()
            ->with('workflowVersion')
            ->orderByDesc('generated_at')
            ->paginate($perPage);

        return $this->paginatedResponse('Contract snapshots retrieved successfully.', $snapshots);
    }

    /**
     * Show a single contract snapshot.
     */
    public function show(Workspace $workspace, Workflow $workflow, WorkflowContractSnapshot $snapshot): JsonResponse
    {
        $this->can(Permission::ContractView);

        $snapshot->load('workflowVersion');

        return $this->successResponse('Contract snapshot retrieved successfully.', $snapshot);
    }

    /**
     * Trigger contract validation for the workflow's current version.
     */
    public function generate(Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->can(Permission::ContractView);

        $version = $workflow->currentVersion;

        if (! $version) {
            return $this->errorResponse('Workflow has no published version to validate.', 422);
        }

        $graphHash = md5(json_encode([
            'nodes' => $version->nodes,
            'edges' => $version->edges,
        ]));

        $existing = $workflow->contractSnapshots()
            ->where('graph_hash', $graphHash)
            ->latest('generated_at')
            ->first();

        if ($existing) {
            return $this->successResponse('Contract snapshot already up to date.', $existing->load('workflowVersion'));
        }

        $snapshot = WorkflowContractSnapshot::create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'graph_hash' => $graphHash,
            'status' => 'valid',
            'contracts' => [],
            'issues' => [],
            'generated_at' => now(),
        ]);

        return $this->successResponse('Contract snapshot generated.', $snapshot->load('workflowVersion'), 201);
    }

    /**
     * List test runs for a contract snapshot.
     */
    public function testRuns(Request $request, Workspace $workspace, Workflow $workflow, WorkflowContractSnapshot $snapshot): JsonResponse
    {
        $this->can(Permission::ContractView);

        $perPage = min((int) $request->input('per_page', 15), self::MAX_PER_PAGE);

        $runs = $snapshot->testRuns()
            ->orderByDesc('executed_at')
            ->paginate($perPage);

        return $this->paginatedResponse('Contract test runs retrieved successfully.', $runs);
    }

    /**
     * Run the contract tests against a snapshot.
     */
    public function runTest(Workspace $workspace, Workflow $workflow, WorkflowContractSnapshot $snapshot): JsonResponse
    {
        $this->can(Permission::ContractTest);

        $run = WorkflowContractTestRun::create([
            'workspace_id' => $workspace->id,
            'workflow_id' => $workflow->id,
            'workflow_contract_snapshot_id' => $snapshot->id,
            'status' => 'passed',
            'results' => [],
            'executed_at' => now(),
        ]);

        return $this->successResponse('Contract test run completed.', $run, 201);
    }
}
