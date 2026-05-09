<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use Carbon\Carbon;

/**
 * Date/Time Node
 * 
 * Date and time operations, formatting, and calculations.
 */
class DateTimeNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'DATETIME_ERROR';
    }

    protected function operations(): array
    {
        return [
            'parse' => $this->parse(...),
            'format' => $this->format(...),
            'add' => $this->add(...),
            'subtract' => $this->subtract(...),
            'diff' => $this->diff(...),
            'compare' => $this->compare(...),
            'now' => $this->now(...),
            'timezone' => $this->timezone(...),
        ];
    }

    /**
     * Parse date string
     */
    private function parse(NodePayload $payload): array
    {
        $dateString = $payload->config['date_string'] ?? '';
        $format = $payload->config['format'] ?? null;
        $timezone = $payload->config['timezone'] ?? null;

        if (empty($dateString)) {
            throw new \InvalidArgumentException('Date string is required');
        }

        try {
            if ($format) {
                $date = Carbon::createFromFormat($format, $dateString, $timezone);
            } else {
                $date = Carbon::parse($dateString, $timezone);
            }

            return [
                'timestamp' => $date->timestamp,
                'iso8601' => $date->toIso8601String(),
                'formatted' => $date->toDateTimeString(),
                'timezone' => $date->timezoneName,
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to parse date: '.$e->getMessage());
        }
    }

    /**
     * Format date
     */
    private function format(NodePayload $payload): array
    {
        $date = $payload->config['date'] ?? $payload->inputData['date'] ?? 'now';
        $format = $payload->config['format'] ?? 'Y-m-d H:i:s';
        $timezone = $payload->config['timezone'] ?? null;

        try {
            $carbon = $date instanceof Carbon ? $date : Carbon::parse($date, $timezone);

            if ($timezone) {
                $carbon->setTimezone($timezone);
            }

            return [
                'formatted' => $carbon->format($format),
                'timestamp' => $carbon->timestamp,
                'iso8601' => $carbon->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to format date: '.$e->getMessage());
        }
    }

    /**
     * Add time to date
     */
    private function add(NodePayload $payload): array
    {
        $date = $payload->config['date'] ?? 'now';
        $value = (int) ($payload->config['value'] ?? 1);
        $unit = $payload->config['unit'] ?? 'days'; // years | months | days | hours | minutes | seconds
        $timezone = $payload->config['timezone'] ?? null;

        try {
            $carbon = Carbon::parse($date, $timezone);

            match ($unit) {
                'years' => $carbon->addYears($value),
                'months' => $carbon->addMonths($value),
                'weeks' => $carbon->addWeeks($value),
                'days' => $carbon->addDays($value),
                'hours' => $carbon->addHours($value),
                'minutes' => $carbon->addMinutes($value),
                'seconds' => $carbon->addSeconds($value),
                default => throw new \InvalidArgumentException("Unknown unit: {$unit}"),
            };

            return [
                'result' => $carbon->toIso8601String(),
                'formatted' => $carbon->toDateTimeString(),
                'timestamp' => $carbon->timestamp,
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to add time: '.$e->getMessage());
        }
    }

    /**
     * Subtract time from date
     */
    private function subtract(NodePayload $payload): array
    {
        $date = $payload->config['date'] ?? 'now';
        $value = (int) ($payload->config['value'] ?? 1);
        $unit = $payload->config['unit'] ?? 'days';
        $timezone = $payload->config['timezone'] ?? null;

        try {
            $carbon = Carbon::parse($date, $timezone);

            match ($unit) {
                'years' => $carbon->subYears($value),
                'months' => $carbon->subMonths($value),
                'weeks' => $carbon->subWeeks($value),
                'days' => $carbon->subDays($value),
                'hours' => $carbon->subHours($value),
                'minutes' => $carbon->subMinutes($value),
                'seconds' => $carbon->subSeconds($value),
                default => throw new \InvalidArgumentException("Unknown unit: {$unit}"),
            };

            return [
                'result' => $carbon->toIso8601String(),
                'formatted' => $carbon->toDateTimeString(),
                'timestamp' => $carbon->timestamp,
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to subtract time: '.$e->getMessage());
        }
    }

    /**
     * Calculate difference between dates
     */
    private function diff(NodePayload $payload): array
    {
        $date1 = $payload->config['date1'] ?? 'now';
        $date2 = $payload->config['date2'] ?? 'now';
        $unit = $payload->config['unit'] ?? 'days'; // years | months | days | hours | minutes | seconds
        $absolute = (bool) ($payload->config['absolute'] ?? true);

        try {
            $carbon1 = Carbon::parse($date1);
            $carbon2 = Carbon::parse($date2);

            $diff = match ($unit) {
                'years' => $carbon1->diffInYears($carbon2, $absolute),
                'months' => $carbon1->diffInMonths($carbon2, $absolute),
                'weeks' => $carbon1->diffInWeeks($carbon2, $absolute),
                'days' => $carbon1->diffInDays($carbon2, $absolute),
                'hours' => $carbon1->diffInHours($carbon2, $absolute),
                'minutes' => $carbon1->diffInMinutes($carbon2, $absolute),
                'seconds' => $carbon1->diffInSeconds($carbon2, $absolute),
                default => throw new \InvalidArgumentException("Unknown unit: {$unit}"),
            };

            return [
                'difference' => $diff,
                'unit' => $unit,
                'human_readable' => $carbon1->diffForHumans($carbon2),
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to calculate difference: '.$e->getMessage());
        }
    }

    /**
     * Compare two dates
     */
    private function compare(NodePayload $payload): array
    {
        $date1 = $payload->config['date1'] ?? 'now';
        $date2 = $payload->config['date2'] ?? 'now';
        $operation = $payload->config['operation'] ?? 'equals'; // equals | before | after | between

        try {
            $carbon1 = Carbon::parse($date1);
            $carbon2 = Carbon::parse($date2);

            $result = match ($operation) {
                'equals', '==' => $carbon1->equalTo($carbon2),
                'before', '<' => $carbon1->lessThan($carbon2),
                'after', '>' => $carbon1->greaterThan($carbon2),
                'before_or_equal', '<=' => $carbon1->lessThanOrEqualTo($carbon2),
                'after_or_equal', '>=' => $carbon1->greaterThanOrEqualTo($carbon2),
                default => throw new \InvalidArgumentException("Unknown operation: {$operation}"),
            };

            return [
                'result' => $result,
                'operation' => $operation,
                'date1' => $carbon1->toIso8601String(),
                'date2' => $carbon2->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to compare dates: '.$e->getMessage());
        }
    }

    /**
     * Get current date/time
     */
    private function now(NodePayload $payload): array
    {
        $timezone = $payload->config['timezone'] ?? null;
        $format = $payload->config['format'] ?? 'iso8601';

        $carbon = Carbon::now($timezone);

        $formatted = match ($format) {
            'iso8601' => $carbon->toIso8601String(),
            'datetime' => $carbon->toDateTimeString(),
            'date' => $carbon->toDateString(),
            'time' => $carbon->toTimeString(),
            'timestamp' => $carbon->timestamp,
            default => $carbon->format($format),
        };

        return [
            'datetime' => $formatted,
            'timestamp' => $carbon->timestamp,
            'iso8601' => $carbon->toIso8601String(),
            'timezone' => $carbon->timezoneName,
            'unix' => $carbon->timestamp,
        ];
    }

    /**
     * Convert timezone
     */
    private function timezone(NodePayload $payload): array
    {
        $date = $payload->config['date'] ?? 'now';
        $fromTimezone = $payload->config['from_timezone'] ?? null;
        $toTimezone = $payload->config['to_timezone'] ?? 'UTC';

        if (empty($toTimezone)) {
            throw new \InvalidArgumentException('Target timezone is required');
        }

        try {
            $carbon = Carbon::parse($date, $fromTimezone);
            $carbon->setTimezone($toTimezone);

            return [
                'result' => $carbon->toIso8601String(),
                'formatted' => $carbon->toDateTimeString(),
                'timezone' => $carbon->timezoneName,
                'offset' => $carbon->offsetHours,
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to convert timezone: '.$e->getMessage());
        }
    }
}
