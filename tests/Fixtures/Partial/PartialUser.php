<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Tests\Fixtures\Partial;

use Illuminate\Database\Eloquent\Model;
use Kyzegs\EloquentEmbeddables\Casts\EmbeddableCast;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\Address;

/**
 * Parent model embedding only part of the Address attributes, used to prove
 * the ide-helper hook merges typing from every embedding parent instead of
 * relying on whichever one a directory scan happens to find first.
 */
class PartialUser extends Model
{
    public $timestamps = false;

    protected $table = 'users';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'address' => EmbeddableCast::using(Address::class, columns: [
                'street' => 'address_street',
            ]),
        ];
    }
}
