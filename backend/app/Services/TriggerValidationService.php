<?php

namespace App\Services;

use App\Models\Trigger;
use App\Models\TriggerType;

class TriggerValidationService
{
    /**
     * Validate field values against trigger type definition
     */
    public function validateFieldValues(TriggerType $triggerType, array $fieldValues): void
    {
        $fields = $triggerType->fields;

        foreach ($fields as $field) {
            $value = $fieldValues[$field->id] ?? null;

            // Check required fields
            if ($field->is_required && (is_null($value) || trim((string) $value) === '')) {
                throw new \InvalidArgumentException("Field '{$field->field_label}' is required");
            }

            if ($value !== null && $value !== '') {
                // Validate against regex if provided
                if ($field->validation_regex && !preg_match('/'.$field->validation_regex.'/', (string) $value)) {
                    throw new \InvalidArgumentException("Field '{$field->field_label}' has invalid format");
                }

                // Validate field type
                $this->validateFieldType($field, $value);
            }
        }
    }

    /**
     * Validate field value type
     */
    private function validateFieldType($field, mixed $value): void
    {
        switch ($field->field_type) {
            case 'number':
                if (!is_numeric($value)) {
                    throw new \InvalidArgumentException("Field '{$field->field_label}' must be a number");
                }
                break;

            case 'time':
                if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', (string) $value)) {
                    throw new \InvalidArgumentException("Field '{$field->field_label}' must be in HH:MM format");
                }
                break;

            case 'date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                    throw new \InvalidArgumentException("Field '{$field->field_label}' must be in YYYY-MM-DD format");
                }
                break;

            case 'cron':
                if (!$this->isValidCron((string) $value)) {
                    throw new \InvalidArgumentException("Field '{$field->field_label}' is not a valid cron expression");
                }
                break;

            case 'select':
                $options = $field->options ?? [];
                $validValues = array_column($options, 'value');
                if (!in_array($value, $validValues)) {
                    throw new \InvalidArgumentException("Field '{$field->field_label}' has invalid value");
                }
                break;

            case 'multiselect':
                if (!is_array($value)) {
                    $value = [$value];
                }
                $options = $field->options ?? [];
                $validValues = array_column($options, 'value');
                foreach ($value as $v) {
                    if (!in_array($v, $validValues)) {
                        throw new \InvalidArgumentException("Field '{$field->field_label}' has invalid value");
                    }
                }
                break;
        }
    }

    /**
     * Check if string is valid cron expression
     */
    private function isValidCron(string $expression): bool
    {
        $parts = preg_split('/\s+/', trim($expression));

        if (count($parts) !== 5) {
            return false;
        }

        $cronRanges = [
            [0, 59],     // minute
            [0, 23],     // hour
            [1, 31],     // day of month
            [1, 12],     // month
            [0, 6],      // day of week
        ];

        foreach ($parts as $i => $part) {
            if (!$this->isValidCronPart($part, $cronRanges[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a cron part is valid
     */
    private function isValidCronPart(string $part, array $range): bool
    {
        if ($part === '*') {
            return true;
        }

        // Handle ranges (0-5)
        if (str_contains($part, '-')) {
            $parts = explode('-', $part);
            if (count($parts) !== 2) {
                return false;
            }
            foreach ($parts as $p) {
                if (!is_numeric($p) || (int) $p < $range[0] || (int) $p > $range[1]) {
                    return false;
                }
            }
            return true;
        }

        // Handle steps (*/5)
        if (str_contains($part, '/')) {
            $parts = explode('/', $part);
            if (count($parts) !== 2) {
                return false;
            }
            if ($parts[0] !== '*' && !is_numeric($parts[0])) {
                return false;
            }
            return is_numeric($parts[1]);
        }

        // Handle lists (0,5,10)
        if (str_contains($part, ',')) {
            foreach (explode(',', $part) as $p) {
                if (!is_numeric($p) || (int) $p < $range[0] || (int) $p > $range[1]) {
                    return false;
                }
            }
            return true;
        }

        // Single number
        return is_numeric($part) && (int) $part >= $range[0] && (int) $part <= $range[1];
    }

    /**
     * Validate entire trigger configuration
     */
    public function validateConfiguration(Trigger $trigger): void
    {
        // Validate credential if required
        if ($trigger->triggerType->requires_credential && !$trigger->credential_id) {
            throw new \InvalidArgumentException('This trigger requires authentication');
        }

        // Validate required fields have values
        $requiredFields = $trigger->triggerType->fields->where('is_required', true);
        $fieldValues = $trigger->fieldValues()->pluck('value', 'trigger_type_field_id')->toArray();

        foreach ($requiredFields as $field) {
            if (!isset($fieldValues[$field->id]) || trim((string) $fieldValues[$field->id]) === '') {
                throw new \InvalidArgumentException("Required field '{$field->field_label}' is not configured");
            }
        }

        // Schedule-specific validation
        if ($trigger->triggerCategory->category_type === 'schedule') {
            if (!$trigger->schedule_expression || !$trigger->schedule_timezone) {
                throw new \InvalidArgumentException('Schedule expression and timezone are required');
            }
        }

        // Polling-specific validation
        if ($trigger->isPollingBased() && !$trigger->polling_interval_seconds) {
            throw new \InvalidArgumentException('Polling interval is required');
        }

        // Webhook-specific validation
        if ($trigger->isWebhookBased() && !$trigger->webhook_uuid) {
            throw new \InvalidArgumentException('Webhook UUID is required');
        }
    }
}
