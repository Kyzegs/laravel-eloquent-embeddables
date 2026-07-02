<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables;

use Illuminate\Database\Eloquent\Model;
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

    public function newQuery()
    {
        throw EmbeddableException::cannotPersist();
    }

    public function newModelQuery()
    {
        throw EmbeddableException::cannotPersist();
    }

    public function newQueryWithoutScopes()
    {
        throw EmbeddableException::cannotPersist();
    }

    // -- Embeddables do not participate in relationships ----------------------

    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        throw EmbeddableException::cannotPersist();
    }

    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        throw EmbeddableException::cannotPersist();
    }

    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        throw EmbeddableException::cannotPersist();
    }

    public function belongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null)
    {
        throw EmbeddableException::cannotPersist();
    }

    public function morphOne($related, $name, $type = null, $id = null, $localKey = null)
    {
        throw EmbeddableException::cannotPersist();
    }

    public function morphMany($related, $name, $type = null, $id = null, $localKey = null)
    {
        throw EmbeddableException::cannotPersist();
    }

    public function morphTo($name = null, $type = null, $id = null, $ownerKey = null)
    {
        throw EmbeddableException::cannotPersist();
    }
}
