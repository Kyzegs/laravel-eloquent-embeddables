<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kyzegs\EloquentEmbeddables\Casts\EmbeddableCast;

/**
 * Parent model using the explicit column-map form and no trait, so its
 * array/JSON output keeps the flat columns.
 *
 * @property int $id
 * @property string|null $name
 * @property-read Address|null $address
 * @property-write Address|array<string, mixed>|null $address
 */
class UserWithColumns extends Model
{
    public $timestamps = false;

    protected $table = 'users';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'address' => EmbeddableCast::using(
                Address::class,
                columns: [
                    'street' => 'address_street',
                    'city' => 'address_city',
                    'postal_code' => 'address_postal_code',
                    'country' => 'address_country',
                ],
                nullable: true,
            ),
        ];
    }
}
