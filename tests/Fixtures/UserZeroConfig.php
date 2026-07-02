<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kyzegs\EloquentEmbeddables\Casts\EmbeddableCast;
use Kyzegs\EloquentEmbeddables\Concerns\HasEmbeddables;

/**
 * Parent model using the zero-config form: no prefix (defaults to the cast
 * key + '_') and no attributes (discovered from the table's schema).
 *
 * @property int $id
 * @property string|null $name
 * @property-read Address|null $address
 * @property-write Address|array<string, mixed>|null $address
 */
class UserZeroConfig extends Model
{
    use HasEmbeddables;

    public $timestamps = false;

    protected $table = 'users';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'address' => EmbeddableCast::using(Address::class, nullable: true),
        ];
    }
}
