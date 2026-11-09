<?php

namespace App\Http\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HandlesResourceQueries
{
    protected function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        return min(max((int) $request->integer('per_page', $default), 1), $max);
    }

    /**
     * Apply a `sort` query param (e.g. `-created_at`) limited to an allowlist.
     *
     * @param  array<int, string>  $allowed
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default): Builder
    {
        $sort = (string) $request->string('sort', $default);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = ltrim($default, '-');
            $direction = str_starts_with($default, '-') ? 'desc' : 'asc';
        }

        return $query->orderBy($column, $direction);
    }
}
