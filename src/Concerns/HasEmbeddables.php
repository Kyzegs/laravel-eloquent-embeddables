<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Concerns;

use Kyzegs\EloquentEmbeddables\Casts\EmbeddableCast;

/**
 * Optional convenience trait for parent models.
 *
 * It is NOT required to use embeddables — reading, writing and mutating all
 * work with only the cast defined in casts(). The trait only cleans up
 * (de)serialization: each embeddable is appended to the model's array/JSON
 * output as a nested object, and its backing columns are hidden so the public
 * shape stays clean while the database keeps plain columns.
 */
trait HasEmbeddables
{
    public function initializeHasEmbeddables(): void
    {
        // Merge the casts() method results in explicitly: trait initializer
        // order is not guaranteed (it changed in Laravel 13 / PHP 8.5), so
        // initializeHasAttributes() may not have merged them yet.
        foreach (array_merge($this->getCasts(), $this->casts()) as $key => $cast) {
            if (! is_string($cast) || ! str_starts_with($cast, EmbeddableCast::class.':')) {
                continue;
            }

            if (! in_array($key, $this->appends, true)) {
                $this->appends[] = $key;
            }

            $this->makeHidden(EmbeddableCast::columnsFor($cast));
        }
    }
}
