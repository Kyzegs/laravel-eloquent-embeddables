<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Tests\Fixtures;

use Kyzegs\EloquentEmbeddables\EmbeddableModel;

/**
 * @property string|null $street
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $country
 * @property-read bool|null $verified
 * @property-write mixed $verified
 */
class Address extends EmbeddableModel
{
    protected $fillable = [
        'street',
        'city',
        'postal_code',
        'country',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
        ];
    }

    public function fullAddress(): string
    {
        return trim("{$this->street}, {$this->postal_code} {$this->city}, {$this->country}");
    }
}
