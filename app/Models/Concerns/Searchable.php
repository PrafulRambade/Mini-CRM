<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a `search()` scope that matches a term against the model's
 * `$searchable` columns using a safely-escaped LIKE comparison.
 */
trait Searchable
{
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $q) use ($like) {
            foreach ($this->searchable as $column) {
                $q->orWhere($this->qualifyColumn($column), 'like', $like);
            }
        });
    }
}
