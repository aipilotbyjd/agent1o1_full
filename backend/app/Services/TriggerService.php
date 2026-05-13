<?php

namespace App\Services;

use App\Models\Credential;
use App\Models\Trigger;
use App\Models\TriggerCategory;
use App\Models\TriggerFieldValue;
use App\Models\TriggerType;
use App\Models\Workflow;
use Illuminate\Support\Str;

class TriggerService
{
    public function __construct(
        private TriggerValidationService $validationService,
        private TriggerRegistrationService $registrationService,
    ) {}

    /**
     * Get all available trigger categories and types
     */
    public function getAvailableTriggers()
    {
        return TriggerCategory::with('triggerTypes.fields')
            ->where('is_active', true)
            ->get()
            ->map(fn ($category) => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'description' => $category->description,
                'icon' => $category->icon,
                'category_type' => $category->category_type,
                'types' => $category->triggerTypes->where('is_active', true)->map(fn ($type) => [
                    'id' => $type->id,
                    'slug' => $type->slug,
                    'name' => $type->name,
                    'description' => $type->description,
                    'execution_mode' => $type->execution_mode,
                    'zapier_mode' => $type->zapier_mode,
                    'requires_credential' => $type->requires_credential,
                    'requires_config_fields' => $type->requires_config_fields,
                    'fields' => $type->fields->sortBy('sort_order')->map(fn ($field) => [
                        'id' => $field->id,
                        'field_name' => $field->field_name,
                        'field_label' => $field->field_label,
                        'field_type' => $field->field_type,
                        'is_required' => $field->is_required,
                        'is_secret' => $field->is_secret,
                        'placeholder' => $field->placeholder,
                        'help_text' => $field->help_text,
                        'options' => $field->options,
                        'validation_regex' => $field->validation_regex,
                    ])->values(),
                ])->values(),
            ]);
    }

    /**
     * Create a new trigger for a workflow
     *
     * @param Workflow $workflow
     * @param TriggerType $triggerType
     * @param ?Credential $credential
     * @param array $fieldValues Field ID => value pairs
     * @return Trigger
     */
    public function createTrigger(
        Workflow $workflow,
        TriggerType $triggerType,
        ?Credential $credential,
        array $fieldValues,
        ?string $name = null
    ): Trigger {
        $this->validationService->validateFieldValues($triggerType, $fieldValues);

        $triggerData = [
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'trigger_type_id' => $triggerType->id,
            'trigger_category_id' => $triggerType->category_id,
            'credential_id' => $credential?->id,
            'name' => $name,
            'is_active' => false,
            'is_published' => false,
        ];

        // Add execution mode specific fields
        if ($triggerType->isWebhookBased()) {
            $triggerData['webhook_uuid'] = Str::uuid();
            $triggerData['webhook_status'] = 'pending';
        } elseif ($triggerType->isPollingBased()) {
            $triggerData['polling_interval_seconds'] = 300; // Default 5 minutes
            $triggerData['polling_last_seen_ids'] = [];
        }

        $trigger = Trigger::create($triggerData);

        // Store field values
        foreach ($fieldValues as $fieldId => $value) {
            TriggerFieldValue::create([
                'trigger_id' => $trigger->id,
                'trigger_type_field_id' => $fieldId,
                'value' => $value,
            ]);
        }

        return $trigger->load('fieldValues', 'triggerType');
    }

    /**
     * Update trigger configuration
     */
    public function updateTrigger(
        Trigger $trigger,
        array $fieldValues,
        ?string $name = null
    ): Trigger {
        $this->validationService->validateFieldValues($trigger->triggerType, $fieldValues);

        if ($name !== null) {
            $trigger->update(['name' => $name]);
        }

        // Update field values
        foreach ($fieldValues as $fieldId => $value) {
            TriggerFieldValue::updateOrCreate(
                [
                    'trigger_id' => $trigger->id,
                    'trigger_type_field_id' => $fieldId,
                ],
                ['value' => $value]
            );
        }

        return $trigger->load('fieldValues');
    }

    /**
     * Publish a trigger (activate it)
     */
    public function publishTrigger(Trigger $trigger): Trigger
    {
        if ($trigger->is_published) {
            return $trigger;
        }

        // Validate configuration before publishing
        $this->validationService->validateConfiguration($trigger);

        // Register webhook if needed
        if ($trigger->isWebhookBased()) {
            $this->registrationService->registerWebhookTrigger($trigger);
        }

        $trigger->update([
            'is_published' => true,
            'is_active' => true,
        ]);

        return $trigger;
    }

    /**
     * Unpublish a trigger (deactivate it)
     */
    public function unpublishTrigger(Trigger $trigger): Trigger
    {
        if (!$trigger->is_published) {
            return $trigger;
        }

        // Unregister webhook if needed
        if ($trigger->isWebhookBased()) {
            $this->registrationService->unregisterWebhookTrigger($trigger);
        }

        $trigger->update([
            'is_published' => false,
            'is_active' => false,
        ]);

        return $trigger;
    }

    /**
     * Delete a trigger
     */
    public function deleteTrigger(Trigger $trigger): bool
    {
        // Unpublish first (unregisters webhooks)
        if ($trigger->is_published) {
            $this->unpublishTrigger($trigger);
        }

        return $trigger->delete();
    }

    /**
     * Get field values as key-value pairs
     */
    public function getFieldValues(Trigger $trigger): array
    {
        return $trigger->fieldValues()
            ->with('field')
            ->get()
            ->mapWithKeys(fn ($fv) => [$fv->field->field_name => $fv->value])
            ->toArray();
    }

    /**
     * Set polling interval
     */
    public function setPollingInterval(Trigger $trigger, int $seconds): Trigger
    {
        if (!$trigger->isPollingBased()) {
            throw new \InvalidArgumentException('Cannot set polling interval on non-polling trigger');
        }

        $trigger->update(['polling_interval_seconds' => max(60, $seconds)]);

        return $trigger;
    }

    /**
     * Update schedule expression
     */
    public function setScheduleExpression(Trigger $trigger, string $expression, string $timezone = 'UTC'): Trigger
    {
        if ($trigger->triggerCategory->category_type !== 'schedule') {
            throw new \InvalidArgumentException('Cannot set schedule on non-schedule trigger');
        }

        $trigger->update([
            'schedule_expression' => $expression,
            'schedule_timezone' => $timezone,
        ]);

        return $trigger;
    }

    /**
     * Record trigger execution (for stats)
     */
    public function recordTriggerExecution(Trigger $trigger): Trigger
    {
        $trigger->increment('trigger_count');
        $trigger->update(['last_triggered_at' => now()]);

        return $trigger;
    }

    /**
     * Record trigger error
     */
    public function recordTriggerError(Trigger $trigger, string $error): Trigger
    {
        $trigger->update([
            'last_error' => $error,
            'last_error_at' => now(),
            'consecutive_errors' => $trigger->consecutive_errors + 1,
        ]);

        return $trigger;
    }

    /**
     * Clear trigger errors
     */
    public function clearTriggerErrors(Trigger $trigger): Trigger
    {
        $trigger->update([
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_errors' => 0,
        ]);

        return $trigger;
    }

    /**
     * Update webhook status
     */
    public function updateWebhookStatus(Trigger $trigger, string $status, ?string $message = null): Trigger
    {
        if (!$trigger->isWebhookBased()) {
            throw new \InvalidArgumentException('Cannot update webhook status on non-webhook trigger');
        }

        $trigger->update([
            'webhook_status' => $status,
            'webhook_status_message' => $message,
        ]);

        return $trigger;
    }

    /**
     * Update polling last check time
     */
    public function updatePollingCheckTime(Trigger $trigger, array $lastSeenIds = []): Trigger
    {
        if (!$trigger->isPollingBased()) {
            throw new \InvalidArgumentException('Cannot update polling check time on non-polling trigger');
        }

        $newLastSeenIds = array_unique(array_merge($lastSeenIds, $trigger->polling_last_seen_ids ?? []));
        // Keep only last 1000 to avoid bloat
        $newLastSeenIds = array_slice($newLastSeenIds, -1000);

        $trigger->update([
            'polling_last_check_at' => now(),
            'polling_last_seen_ids' => $newLastSeenIds,
        ]);

        return $trigger;
    }

    /**
     * Get triggers that need polling check
     */
    public function getPollingDueTriggers()
    {
        return Trigger::where('is_published', true)
            ->whereHas('triggerType', fn ($q) => $q->where('execution_mode', 'polling'))
            ->where(function ($q) {
                $q->whereNull('polling_last_check_at')
                    ->orWhereRaw('polling_last_check_at + (polling_interval_seconds || \'seconds\')::interval < NOW()');
            })
            ->with('workflow', 'triggerType', 'fieldValues')
            ->get();
    }

    /**
     * Get scheduled triggers that are due
     */
    public function getScheduledDueTriggers()
    {
        return Trigger::where('is_published', true)
            ->whereHas('triggerCategory', fn ($q) => $q->where('category_type', 'schedule'))
            ->where('schedule_next_run_at', '<=', now())
            ->with('workflow', 'triggerType', 'fieldValues')
            ->get();
    }
}
