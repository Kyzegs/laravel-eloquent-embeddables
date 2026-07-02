<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Kyzegs\EloquentEmbeddables\Casts\EmbeddableCast;
use Kyzegs\EloquentEmbeddables\Exceptions\EmbeddableException;

/**
 * Base class for embeddable value objects.
 *
 * An embeddable has no table and no identity of its own. It is hydrated from,
 * and persisted through, the columns of a parent Eloquent model via
 * {@see EmbeddableCast}.
 *
 * It reuses Eloquent's attribute machinery — fillable/guarded, casts,
 * accessors, mutators, hidden, visible, appends, toArray()/toJson() and
 * default attributes — while refusing every persistence and relationship
 * operation: those belong to the parent model.
 *
 * Query builder construction (newQuery()/newModelQuery()) is deliberately NOT
 * blocked: tooling such as barryvdh/laravel-ide-helper needs it to introspect
 * the model, and executing such a query still fails at the database level
 * since no table backs the embeddable.
 */
abstract class EmbeddableModel extends Model
{
    /**
     * An embeddable has no timestamps of its own.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * An embeddable has no auto-incrementing identity of its own.
     *
     * @var bool
     */
    public $incrementing = false;

    // -- Persistence is owned by the parent model -----------------------------

    /** @param array<string, mixed> $options */
    public function save(array $options = [])
    {
        throw EmbeddableException::cannotPersist();
    }

    /** @param array<string, mixed> $options */
    public function saveOrFail(array $options = [])
    {
        throw EmbeddableException::cannotPersist();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw EmbeddableException::cannotPersist();
    }

    public function delete()
    {
        throw EmbeddableException::cannotPersist();
    }

    public function forceDelete()
    {
        throw EmbeddableException::cannotPersist();
    }

    public function push()
    {
        throw EmbeddableException::cannotPersist();
    }

    public function refresh()
    {
        throw EmbeddableException::cannotPersist();
    }

    /** @param  array<int, string>|string  $with */
    public function fresh($with = [])
    {
        throw EmbeddableException::cannotPersist();
    }

    /** @param  array<int, string>|string|null  $attribute */
    public function touch($attribute = null)
    {
        throw EmbeddableException::cannotPersist();
    }

    /** @param  array<int, string>|string|null  $attribute */
    public function touchQuietly($attribute = null)
    {
        throw EmbeddableException::cannotPersist();
    }

    /** @param  array<string, mixed>  $extra */
    public function increment($column, $amount = 1, array $extra = [])
    {
        throw EmbeddableException::cannotPersist();
    }

    /** @param  array<string, mixed>  $extra */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw EmbeddableException::cannotPersist();
    }

    /** @param  array<string, mixed>  $extra */
    public function incrementQuietly($column, $amount = 1, array $extra = [])
    {
        throw EmbeddableException::cannotPersist();
    }

    /** @param  array<string, mixed>  $extra */
    public function decrementQuietly($column, $amount = 1, array $extra = [])
    {
        throw EmbeddableException::cannotPersist();
    }

    // -- Embeddables do not participate in relationships ----------------------

    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        throw EmbeddableException::cannotRelate();
    }

    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        throw EmbeddableException::cannotRelate();
    }

    public function hasOneThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null)
    {
        throw EmbeddableException::cannotRelate();
    }

    public function hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null)
    {
        throw EmbeddableException::cannotRelate();
    }

    /**
     * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  string|HasMany<TIntermediateModel, covariant $this>|HasOne<TIntermediateModel, covariant $this>  $relationship
     * @return never
     */
    public function through($relationship)
    {
        throw EmbeddableException::cannotRelate();
    }

    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        throw EmbeddableException::cannotRelate();
    }

    public function belongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null)
    {
        throw EmbeddableException::cannotRelate();
    }

    public function morphOne($related, $name, $type = null, $id = null, $localKey = null)
    {
        throw EmbeddableException::cannotRelate();
    }

    public function morphMany($related, $name, $type = null, $id = null, $localKey = null)
    {
        throw EmbeddableException::cannotRelate();
    }

    public function morphTo($name = null, $type = null, $id = null, $ownerKey = null)
    {
        throw EmbeddableException::cannotRelate();
    }

    public function morphToMany($related, $name, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null, $inverse = false)
    {
        throw EmbeddableException::cannotRelate();
    }

    public function morphedByMany($related, $name, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null)
    {
        throw EmbeddableException::cannotRelate();
    }

    // -- Value-object comparison ----------------------------------------------

    /**
     * Determine whether this embeddable equals another: the same concrete
     * class with identical raw attributes, regardless of attribute order.
     */
    public function equals(?EmbeddableModel $other): bool
    {
        if ($other === null || $other::class !== static::class) {
            return false;
        }

        $ours = $this->getAttributes();
        $theirs = $other->getAttributes();

        ksort($ours);
        ksort($theirs);

        return $ours === $theirs;
    }
}
