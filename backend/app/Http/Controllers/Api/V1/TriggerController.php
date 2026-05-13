<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTriggerRequest;
use App\Http\Requests\UpdateTriggerRequest;
use App\Models\Trigger;
use App\Models\TriggerType;
use App\Models\Workflow;
use App\Services\TriggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TriggerController extends Controller
{
    public function __construct(private TriggerService $triggerService) {}

    /**
     * Get available trigger categories and types
     */
    public function getAvailable(): JsonResponse
    {
        $triggers = $this->triggerService->getAvailableTriggers();

        return response()->json([
            'data' => $triggers,
        ]);
    }

    /**
     * Create a new trigger for a workflow
     */
    public function store(Workflow $workflow, StoreTriggerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $triggerType = TriggerType::findOrFail($validated['trigger_type_id']);

        // Get credential if provided
        $credential = null;
        if (isset($validated['credential_id'])) {
            $credential = $workflow->workspace->credentials()->findOrFail($validated['credential_id']);
        }

        // Map field labels to field IDs for service
        $fieldValues = $this->mapFieldValues($triggerType, $validated['field_values'] ?? []);

        $trigger = $this->triggerService->createTrigger(
            $workflow,
            $triggerType,
            $credential,
            $fieldValues,
            $validated['name'] ?? null
        );

        return response()->json([
            'data' => $trigger->load('fieldValues', 'triggerType'),
        ], 201);
    }

    /**
     * Update trigger configuration
     */
    public function update(Trigger $trigger, UpdateTriggerRequest $request): JsonResponse
    {
        // Check authorization
        $this->authorize('update', $trigger);

        $validated = $request->validated();

        // Map field labels to field IDs
        $fieldValues = $this->mapFieldValues($trigger->triggerType, $validated['field_values'] ?? []);

        $trigger = $this->triggerService->updateTrigger(
            $trigger,
            $fieldValues,
            $validated['name'] ?? null
        );

        return response()->json([
            'data' => $trigger->load('fieldValues'),
        ]);
    }

    /**
     * Publish trigger (activate it)
     */
    public function publish(Trigger $trigger): JsonResponse
    {
        $this->authorize('update', $trigger);

        try {
            $trigger = $this->triggerService->publishTrigger($trigger);

            return response()->json([
                'data' => $trigger,
                'message' => 'Trigger published successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to publish trigger',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Unpublish trigger (deactivate it)
     */
    public function unpublish(Trigger $trigger): JsonResponse
    {
        $this->authorize('update', $trigger);

        try {
            $trigger = $this->triggerService->unpublishTrigger($trigger);

            return response()->json([
                'data' => $trigger,
                'message' => 'Trigger unpublished successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to unpublish trigger',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a trigger
     */
    public function destroy(Trigger $trigger): JsonResponse
    {
        $this->authorize('delete', $trigger);

        $this->triggerService->deleteTrigger($trigger);

        return response()->json(null, 204);
    }

    /**
     * Get trigger details
     */
    public function show(Trigger $trigger): JsonResponse
    {
        $this->authorize('view', $trigger);

        return response()->json([
            'data' => $trigger->load('fieldValues', 'triggerType', 'credential'),
        ]);
    }

    /**
     * Set polling interval
     */
    public function setPollingInterval(Trigger $trigger, Request $request): JsonResponse
    {
        $this->authorize('update', $trigger);

        $validated = $request->validate([
            'interval_seconds' => 'required|integer|min:60',
        ]);

        $trigger = $this->triggerService->setPollingInterval($trigger, $validated['interval_seconds']);

        return response()->json([
            'data' => $trigger,
            'message' => 'Polling interval updated',
        ]);
    }

    /**
     * Set schedule expression
     */
    public function setSchedule(Trigger $trigger, Request $request): JsonResponse
    {
        $this->authorize('update', $trigger);

        $validated = $request->validate([
            'expression' => 'required|string',
            'timezone' => 'required|string|timezone',
        ]);

        $trigger = $this->triggerService->setScheduleExpression(
            $trigger,
            $validated['expression'],
            $validated['timezone']
        );

        return response()->json([
            'data' => $trigger,
            'message' => 'Schedule updated',
        ]);
    }

    /**
     * Get trigger executions
     */
    public function executions(Trigger $trigger, Request $request): JsonResponse
    {
        $this->authorize('view', $trigger);

        $executions = $trigger->executions()
            ->latest('triggered_at')
            ->paginate($request->query('per_page', 25));

        return response()->json([
            'data' => $executions->items(),
            'pagination' => [
                'total' => $executions->total(),
                'per_page' => $executions->perPage(),
                'current_page' => $executions->currentPage(),
                'last_page' => $executions->lastPage(),
            ],
        ]);
    }

    /**
     * Map field names/labels to field IDs for database storage
     */
    private function mapFieldValues(TriggerType $triggerType, array $fieldValues): array
    {
        $fields = $triggerType->fields->keyBy('field_name');
        $mapped = [];

        foreach ($fieldValues as $fieldName => $value) {
            if (isset($fields[$fieldName])) {
                $mapped[$fields[$fieldName]->id] = $value;
            }
        }

        return $mapped;
    }
}
