<?php

namespace App\Http\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait HandlesBulkDeletion
{
    /**
     * Validate an incoming `{ ids: [] }` payload against a table.
     *
     * @return array<int, int>
     */
    protected function validateBulkIds(Request $request, string $table): array
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', "exists:{$table},id"],
        ]);

        return array_values(array_unique($validated['ids']));
    }

    /**
     * Run a per-model deletion handler, collecting how many were deleted and
     * which were skipped (with a human-readable reason).
     *
     * The handler receives each model and must perform the delete itself,
     * returning null on success or a reason string to skip it.
     *
     * @param  Collection<int, Model>  $models
     * @return array{deleted:int, skipped:array<int, array{id:int, reason:string}>}
     */
    protected function runBulkDelete($models, Closure $handle): array
    {
        $deleted = 0;
        $skipped = [];

        foreach ($models as $model) {
            $reason = $handle($model);

            if ($reason === null) {
                $deleted++;
            } else {
                $skipped[] = ['id' => $model->id, 'reason' => $reason];
            }
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }
}
