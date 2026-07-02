<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Kyzegs\EloquentEmbeddables\EmbeddableModel;

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
     * Either pass a `prefix` plus the list of `attributes`, or an explicit
     * `columns` map of `embeddable attribute => parent column`.
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
     * @return CastsAttributes<EmbeddableModel|null, EmbeddableModel|array<string, mixed>|null>
     */
    public static function castUsing(array $arguments): CastsAttributes
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

        foreach ($this->map() as $attribute => $column) {
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
        if (is_null($value)) {
            return array_fill_keys(array_values($this->map()), null);
        }

        $raw = $this->toEmbeddable($value)->getAttributes();

        $columns = [];

        foreach ($this->map() as $attribute => $column) {
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
     * Resolve the backing parent columns for an encoded cast definition.
     *
     * @return list<string>
     */
    public static function columnsFor(string $cast): array
    {
        $config = static::decode(explode(':', $cast, 2)[1]);

        if (! empty($config['columns'])) {
            return array_values($config['columns']);
        }

        return array_values(array_map(
            static fn (string $attribute): string => $config['prefix'].$attribute,
            $config['attributes'],
        ));
    }

    /**
     * @return array<string, string> Map of embeddable attribute => parent column.
     */
    protected function map(): array
    {
        if (! empty($this->columns)) {
            return $this->columns;
        }

        $map = [];

        foreach ($this->attributes as $attribute) {
            $map[$attribute] = $this->prefix.$attribute;
        }

        return $map;
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
     * @return array<string, mixed>
     */
    protected static function decode(string $payload): array
    {
        return json_decode(base64_decode($payload), true, 512, JSON_THROW_ON_ERROR);
    }
}
