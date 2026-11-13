<?php

namespace App\Repositories\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Word-by-word search: every token must appear in at least one of the given columns
 * (own columns, or "relation.column" via whereHas). Translatable JSON columns are cast to
 * text and lower-cased so matching is case-insensitive in every locale.
 */
trait MatchesSearchTokens
{
    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $tokens
     * @param  list<string>  $columns  e.g. ['name', 'address', 'destination.name']
     */
    protected function matchTokens(Builder $query, array $tokens, array $columns): void
    {
        foreach ($tokens as $token) {
            $like = '%'.addcslashes($token, '%_\\').'%';

            $query->where(function (Builder $any) use ($columns, $like): void {
                foreach ($columns as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $relatedColumn] = explode('.', $column, 2);
                        $any->orWhereHas($relation, fn (Builder $related) => $related->whereRaw("LOWER(CAST({$relatedColumn} AS CHAR)) LIKE ?", [$like]));
                    } else {
                        $any->orWhereRaw("LOWER(CAST({$column} AS CHAR)) LIKE ?", [$like]);
                    }
                }
            });
        }
    }
}
