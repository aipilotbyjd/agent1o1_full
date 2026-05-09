<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

trait FiltersListQuery
{
    /**
     * Resolve a safe per_page value from the request.
     */
    protected function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        return min(max(1, (int) $request->input('per_page', $default)), $max);
    }

    /**
     * Resolve a safe sort column from the request.
     *
     * @param  string[]  $allowed
     */
    protected function sortColumn(Request $request, array $allowed, string $default = 'created_at'): string
    {
        return in_array($request->input('sort_by'), $allowed, true)
            ? $request->input('sort_by')
            : $default;
    }

    /**
     * Resolve a safe sort direction (asc|desc) from the request.
     */
    protected function sortDirection(Request $request): string
    {
        return $request->input('sort_direction') === 'asc' ? 'asc' : 'desc';
    }

    /**
     * Apply created_at (or any date column) range filters from the request.
     *
     * Reads `from` and `to` query parameters.
     */
    protected function applyDateRange(Builder|Relation $query, Request $request, string $column = 'created_at'): void
    {
        if ($request->filled('from')) {
            $query->where($column, '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where($column, '<=', $request->input('to'));
        }
    }

    /**
     * Apply a LIKE search across one or more columns from the request.
     *
     * Reads the `search` query parameter. Safely escapes % and _ wildcards.
     *
     * @param  string|string[]  $columns  One or more column names to search across.
     */
    protected function applySearch(Builder|Relation $query, Request $request, string|array $columns): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $term = str_replace(['%', '_'], ['\%', '\_'], $request->input('search'));
        $cols = (array) $columns;

        $query->where(function (Builder $q) use ($term, $cols): void {
            foreach ($cols as $i => $col) {
                $method = $i === 0 ? 'where' : 'orWhere';
                $q->{$method}($col, 'like', "%{$term}%");
            }
        });
    }
}
