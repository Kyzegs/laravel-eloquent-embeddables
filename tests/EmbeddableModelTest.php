<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Tests;

use Kyzegs\EloquentEmbeddables\Exceptions\EmbeddableException;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\Address;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class EmbeddableModelTest extends TestCase
{
    #[Test]
    public function it_behaves_like_an_eloquent_attribute_bag(): void
    {
        $address = new Address([
            'street' => 'Coolsingel 1',
            'city' => 'Rotterdam',
            'postal_code' => '3012 AA',
            'country' => 'NL',
        ]);

        $this->assertSame('Rotterdam', $address->city);

        $address->verified = '1';
        $this->assertTrue($address->verified);

        $this->assertSame([
            'street' => 'Coolsingel 1',
            'city' => 'Rotterdam',
            'postal_code' => '3012 AA',
            'country' => 'NL',
            'verified' => true,
        ], $address->toArray());
    }

    #[Test]
    public function it_respects_fillable_on_mass_assignment(): void
    {
        $address = new Address(['street' => 'Coolsingel 1', 'verified' => true]);

        $this->assertSame('Coolsingel 1', $address->street);
        // `verified` is not fillable, so mass assignment must not set it.
        $this->assertFalse($address->getAttributes()['verified'] ?? false);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    #[Test]
    #[DataProvider('forbiddenMethods')]
    public function it_forbids_persistence_and_relationship_methods(string $method, array $arguments): void
    {
        $address = new Address(['city' => 'Rotterdam']);

        try {
            $address->{$method}(...$arguments);
            $this->fail("Expected {$method}() to throw an EmbeddableException.");
        } catch (EmbeddableException $e) {
            $this->assertSame(
                'Embeddables are persisted through their parent Eloquent model. Call save() on the parent model instead.',
                $e->getMessage(),
            );
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: array<int, mixed>}>
     */
    public static function forbiddenMethods(): iterable
    {
        yield 'save' => ['save', []];
        yield 'update' => ['update', [['city' => 'X']]];
        yield 'delete' => ['delete', []];
        yield 'forceDelete' => ['forceDelete', []];
        yield 'refresh' => ['refresh', []];
        yield 'fresh' => ['fresh', []];
        yield 'push' => ['push', []];
        yield 'newQuery' => ['newQuery', []];
        yield 'newModelQuery' => ['newModelQuery', []];
        yield 'hasOne' => ['hasOne', [User::class]];
        yield 'hasMany' => ['hasMany', [User::class]];
        yield 'belongsTo' => ['belongsTo', [User::class]];
        yield 'belongsToMany' => ['belongsToMany', [User::class]];
        yield 'morphOne' => ['morphOne', [User::class, 'parent']];
        yield 'morphMany' => ['morphMany', [User::class, 'parent']];
        yield 'morphTo' => ['morphTo', []];
    }
}
