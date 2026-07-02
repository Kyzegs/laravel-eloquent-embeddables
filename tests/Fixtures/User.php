<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kyzegs\EloquentEmbeddables\Casts\EmbeddableCast;
use Kyzegs\EloquentEmbeddables\Concerns\HasEmbeddables;

/**
 * Parent model using the prefix form + the optional HasEmbeddables trait
 * (so toArray()/toJson() expose a clean nested object).
 *
 * @property int $id
 * @property string|null $name
 * @property-read Address|null $address
 * @property-write Address|array<string, mixed>|null $address
 */
class User extends Model
{
    use HasEmbeddables;

    public $timestamps = false;

    protected $table = 'users';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'address' => EmbeddableCast::using(
                Address::class,
                prefix: 'address_',
                attributes: [
                    'street',
                    'city',
                    'postal_code',
                    'country',
                    'verified',
                ],
                nullable: true,
            ),
        ];
    }
}
