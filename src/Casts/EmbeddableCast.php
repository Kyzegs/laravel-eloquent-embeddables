<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Kyzegs\EloquentEmbeddables\EmbeddableModel;
use Throwable;

/**
 * Casts a group of normal parent columns into a rich embeddable object.
 *
 * Reading maps the parent's columns onto the embeddable's attributes; writing
 * flattens the embeddable back into those columns. Because Eloquent re-invokes
 * {@see self::set()} for cached cast objects on save, direct mutations to the
 * embeddable (`$user->address->city = '...'`) are synced to the parent too.
 *
 * @implements CastsAttributes<EmbeddableModel|null, EmbeddableModel|array<string, mixed>|null>
 *
 * @phpstan-consistent-constructor
 */
class EmbeddableCast implements Castable, CastsAttributes, SerializesCastableAttributes
{
    /**
     * Column listings per connection + table, so zero-config discovery costs
     * at most one schema query per table per process.
     *
     * @var array<string, list<string>>
     */
    protected static array $schemaColumns = [];

    /** @var array<string, string>|null */
    protected ?array $resolvedMap = null;

    /**
     * @param  class-string<EmbeddableModel>  $embeddable
     * @param  list<string>  $attributes
     * @param  array<string, string>  $columns
     */
    public function __construct(
        protected string $embeddable,
        protected ?string $prefix = null,
        protected array $attributes = [],
        protected array $columns = [],
        protected bool $nullable = false,
    ) {}

    /**
     * Build the cast definition for a parent model's casts() array.
     *
     * All arguments besides the class are optional. Without them, the column
     * map is resolved lazily per {@see self::resolveMap()}: the prefix
     * defaults to the cast key + '_', and the attributes are discovered from
     * the parent table's schema (falling back to the embeddable's fillable
     * attributes and cast keys).
     *
     * @param  class-string<EmbeddableModel>  $class
     * @param  list<string>  $attributes
     * @param  array<string, string>  $columns
     */
    public static function using(
        string $class,
        ?string $prefix = null,
        array $attributes = [],
        array $columns = [],
        bool $nullable = false,
    ): string {
        if ($columns !== [] && ($prefix !== null || $attributes !== [])) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] embeddable cast accepts either a prefix/attributes or an explicit columns map, not both.',
                $class,
            ));
        }

        return static::class.':'.static::encode([
            'embeddable' => $class,
            'prefix' => $prefix,
            'attributes' => $attributes,
            'columns' => $columns,
            'nullable' => $nullable,
        ]);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    public static function castUsing(array $arguments): static
    {
        $config = static::decode($arguments[0]);

        return new static(
            $config['embeddable'],
            $config['prefix'],
            $config['attributes'],
            $config['columns'],
            $config['nullable'],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?EmbeddableModel
    {
        $raw = [];
        $allNull = true;

        foreach ($this->map($model, $key) as $attribute => $column) {
            $raw[$attribute] = $attributes[$column] ?? null;

            if (! is_null($raw[$attribute])) {
                $allNull = false;
            }
        }

        if ($this->nullable && $allNull) {
            return null;
        }

        $class = $this->embeddable;

        return (new $class)->setRawAttributes($raw, sync: true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $map = $this->map($model, $key);

        if (is_null($value)) {
            return array_fill_keys(array_values($map), null);
        }

        $raw = $this->toEmbeddable($value)->getAttributes();

        $columns = [];

        foreach ($map as $attribute => $column) {
            $columns[$column] = $raw[$attribute] ?? null;
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return $value instanceof EmbeddableModel ? $value->toArray() : $value;
    }

    /**
     * Decode the configuration for an encoded cast definition.
     *
     * @return array{embeddable: class-string<EmbeddableModel>, prefix: string|null, attributes: list<string>, columns: array<string, string>, nullable: bool}
     */
    public static function configFor(string $cast): array
    {
        return static::decode(explode(':', $cast, 2)[1]);
    }

    /**
     * Resolve the attribute => parent column map for an encoded cast definition.
     *
     * @return array<string, string>
     */
    public static function mapFor(string $cast, string $key, Model $model): array
    {
        return static::castUsing([explode(':', $cast, 2)[1]])->map($model, $key);
    }

    /**
     * Resolve the backing parent columns for an encoded cast definition.
     *
     * @return list<string>
     */
    public static function columnsFor(string $cast, string $key, Model $model): array
    {
        return array_values(static::mapFor($cast, $key, $model));
    }

    /**
     * @return array<string, string> Map of embeddable attribute => parent column.
     */
    protected function map(Model $model, string $key): array
    {
        return $this->resolvedMap ??= $this->resolveMap($model, $key);
    }

    /**
     * Resolve the column map, most explicit configuration first:
     *
     * 1. An explicit `columns` map.
     * 2. Explicit `attributes`, prefixed.
     * 3. The parent table's columns matching the prefix (schema discovery).
     * 4. The embeddable's fillable attributes and cast keys, prefixed.
     *
     * The prefix defaults to the cast key + '_'.
     *
     * @return array<string, string>
     */
    protected function resolveMap(Model $model, string $key): array
    {
        if ($this->columns !== []) {
            return $this->columns;
        }

        $prefix = $this->prefix ?? $key.'_';

        if ($this->attributes !== []) {
            return static::prefixed($this->attributes, $prefix);
        }

        $discovered = [];

        foreach (static::tableColumns($model) as $column) {
            if (str_starts_with($column, $prefix) && strlen($column) > strlen($prefix)) {
                $discovered[substr($column, strlen($prefix))] = $column;
            }
        }

        if ($discovered !== []) {
            return $discovered;
        }

        $embeddable = new $this->embeddable;

        $attributes = array_values(array_unique(array_merge(
            $embeddable->getFillable(),
            array_keys($embeddable->getCasts()),
        )));

        if ($attributes !== []) {
            return static::prefixed($attributes, $prefix);
        }

        throw new InvalidArgumentException(sprintf(
            'Unable to resolve the column map for the [%s] embeddable on [%s.%s]: no attributes or columns were configured, no [%s*] columns exist on the [%s] table, and the embeddable declares no fillable attributes or casts.',
            $this->embeddable,
            $model::class,
            $key,
            $prefix,
            $model->getTable(),
        ));
    }

    /**
     * @param  list<string>  $attributes
     * @return array<string, string>
     */
    protected static function prefixed(array $attributes, string $prefix): array
    {
        $map = [];

        foreach ($attributes as $attribute) {
            $map[$attribute] = $prefix.$attribute;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    protected static function tableColumns(Model $model): array
    {
        $key = ($model->getConnectionName() ?? 'default').'.'.$model->getTable();

        if (! array_key_exists($key, static::$schemaColumns)) {
            try {
                static::$schemaColumns[$key] = $model->getConnection()
                    ->getSchemaBuilder()
                    ->getColumnListing($model->getTable());
            } catch (Throwable) {
                // No usable connection (e.g. constructing models offline);
                // resolveMap() falls back to the embeddable's own attributes.
                static::$schemaColumns[$key] = [];
            }
        }

        return static::$schemaColumns[$key];
    }

    protected function toEmbeddable(mixed $value): EmbeddableModel
    {
        $class = $this->embeddable;

        if ($value instanceof $class) {
            return $value;
        }

        if ($value instanceof EmbeddableModel) {
            $value = $value->getAttributes();
        }

        if (is_array($value)) {
            return new $class($value);
        }

        throw new InvalidArgumentException(sprintf(
            'The [%s] embeddable can only be set from null, an array, or an instance of [%s]; got [%s].',
            $class,
            EmbeddableModel::class,
            get_debug_type($value),
        ));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected static function encode(array $config): string
    {
        return base64_encode(json_encode($config, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{embeddable: class-string<EmbeddableModel>, prefix: string|null, attributes: list<string>, columns: array<string, string>, nullable: bool}
     */
    protected static function decode(string $payload): array
    {
        /** @var array{embeddable: class-string<EmbeddableModel>, prefix: string|null, attributes: list<string>, columns: array<string, string>, nullable: bool} */
        return json_decode(base64_decode($payload), true, 512, JSON_THROW_ON_ERROR);
    }
}
