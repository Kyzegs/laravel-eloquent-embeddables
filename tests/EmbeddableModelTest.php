<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Tests;

use Kyzegs\EloquentEmbeddables\EmbeddableModel;
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
    #[DataProvider('persistenceMethods')]
    public function it_forbids_persistence_methods(string $method, array $arguments): void
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
     * @param  array<int, mixed>  $arguments
     */
    #[Test]
    #[DataProvider('relationshipMethods')]
    public function it_forbids_relationship_methods(string $method, array $arguments): void
    {
        $address = new Address(['city' => 'Rotterdam']);

        try {
            $address->{$method}(...$arguments);
            $this->fail("Expected {$method}() to throw an EmbeddableException.");
        } catch (EmbeddableException $e) {
            $this->assertSame(
                'Embeddables do not participate in relationships. Define the relationship on the parent Eloquent model instead.',
                $e->getMessage(),
            );
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: array<int, mixed>}>
     */
    public static function persistenceMethods(): iterable
    {
        yield 'save' => ['save', []];
        yield 'saveOrFail' => ['saveOrFail', []];
        yield 'update' => ['update', [['city' => 'X']]];
        yield 'delete' => ['delete', []];
        yield 'forceDelete' => ['forceDelete', []];
        yield 'refresh' => ['refresh', []];
        yield 'fresh' => ['fresh', []];
        yield 'push' => ['push', []];
        yield 'touch' => ['touch', []];
        yield 'touchQuietly' => ['touchQuietly', []];
        yield 'increment' => ['increment', ['verified']];
        yield 'decrement' => ['decrement', ['verified']];
        yield 'incrementQuietly' => ['incrementQuietly', ['verified']];
        yield 'decrementQuietly' => ['decrementQuietly', ['verified']];
    }

    /**
     * @return iterable<string, array{0: string, 1: array<int, mixed>}>
     */
    public static function relationshipMethods(): iterable
    {
        yield 'hasOne' => ['hasOne', [User::class]];
        yield 'hasMany' => ['hasMany', [User::class]];
        yield 'hasOneThrough' => ['hasOneThrough', [User::class, User::class]];
        yield 'hasManyThrough' => ['hasManyThrough', [User::class, User::class]];
        yield 'through' => ['through', ['users']];
        yield 'belongsTo' => ['belongsTo', [User::class]];
        yield 'belongsToMany' => ['belongsToMany', [User::class]];
        yield 'morphOne' => ['morphOne', [User::class, 'parent']];
        yield 'morphMany' => ['morphMany', [User::class, 'parent']];
        yield 'morphTo' => ['morphTo', []];
        yield 'morphToMany' => ['morphToMany', [User::class, 'parent']];
        yield 'morphedByMany' => ['morphedByMany', [User::class, 'parent']];
    }

    #[Test]
    public function it_compares_value_objects_with_equals(): void
    {
        $a = new Address(['street' => 'Coolsingel 1', 'city' => 'Rotterdam']);
        $b = new Address(['city' => 'Rotterdam', 'street' => 'Coolsingel 1']);
        $c = new Address(['street' => 'Coolsingel 1', 'city' => 'Amsterdam']);

        $this->assertTrue($a->equals($b));
        $this->assertTrue($b->equals($a));
        $this->assertFalse($a->equals($c));
        $this->assertFalse($a->equals(null));
    }

    #[Test]
    public function equals_requires_the_same_concrete_class(): void
    {
        $address = new Address(['city' => 'Rotterdam']);

        $other = new class extends EmbeddableModel
        {
            protected $fillable = ['city'];
        };

        $other->fill(['city' => 'Rotterdam']);

        $this->assertFalse($address->equals($other));
    }
}
