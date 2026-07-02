<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Kyzegs\EloquentEmbeddables\Casts\EmbeddableCast;
use Kyzegs\EloquentEmbeddables\EmbeddableModel;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\Address;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\User;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\UserWithColumns;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\UserZeroConfig;
use PHPUnit\Framework\Attributes\Test;

class EmbeddableCastTest extends TestCase
{
    #[Test]
    public function it_reads_parent_columns_into_an_embeddable(): void
    {
        $this->seedAddress();

        $user = User::firstOrFail();

        $this->assertInstanceOf(Address::class, $user->address);
        $this->assertSame('Coolsingel 1', $user->address->street);
        $this->assertSame('Rotterdam', $user->address->city);
        $this->assertSame('Coolsingel 1, 3012 AA Rotterdam, NL', $user->address->fullAddress());
    }

    #[Test]
    public function it_applies_the_embeddable_casts_when_reading(): void
    {
        $this->seedAddress(['address_verified' => 1]);

        $user = User::firstOrFail();

        $this->assertTrue($user->address?->verified);
    }

    #[Test]
    public function it_returns_null_when_all_columns_are_null_and_nullable(): void
    {
        DB::table('users')->insert(['name' => 'Empty']);

        $user = User::firstOrFail();

        $this->assertNull($user->address);
    }

    #[Test]
    public function it_can_be_assigned_an_array(): void
    {
        $user = User::create(['name' => 'Sebastiaan']);

        $user->address = [
            'street' => 'Coolsingel 1',
            'city' => 'Rotterdam',
            'postal_code' => '3012 AA',
            'country' => 'NL',
        ];

        $user->save();

        $row = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotNull($row);
        $this->assertSame('Coolsingel 1', $row->address_street);
        $this->assertSame('Rotterdam', $row->address_city);
        $this->assertSame('3012 AA', $row->address_postal_code);
        $this->assertSame('NL', $row->address_country);
    }

    #[Test]
    public function it_can_be_assigned_an_embeddable_instance(): void
    {
        $user = User::create(['name' => 'Sebastiaan']);

        $user->address = new Address([
            'street' => 'Coolsingel 1',
            'city' => 'Rotterdam',
            'postal_code' => '3012 AA',
            'country' => 'NL',
        ]);

        $user->save();
        $user->refresh();

        $this->assertSame('Rotterdam', $user->address?->city);
    }

    #[Test]
    public function it_syncs_direct_mutations_back_to_the_parent(): void
    {
        $this->seedAddress();

        $user = User::firstOrFail();

        $this->assertNotNull($user->address);
        $user->address->city = 'Amsterdam';

        $this->assertTrue($user->isDirty('address_city'));

        $user->save();

        $this->assertSame('Amsterdam', DB::table('users')->where('id', $user->id)->value('address_city'));
        $this->assertSame('Amsterdam', User::firstOrFail()->address?->city);
    }

    #[Test]
    public function it_casts_and_persists_mutated_attributes(): void
    {
        $this->seedAddress();

        $user = User::firstOrFail();

        $this->assertNotNull($user->address);
        $user->address->verified = '1';

        $this->assertTrue($user->address->verified);

        $user->save();

        $this->assertEquals(1, DB::table('users')->where('id', $user->id)->value('address_verified'));

        $user->refresh();
        $this->assertTrue($user->address?->verified);
    }

    #[Test]
    public function it_can_be_nulled_on_a_nullable_embeddable(): void
    {
        $this->seedAddress();

        $user = User::firstOrFail();
        $user->address = null;
        $user->save();

        $row = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotNull($row);
        $this->assertNull($row->address_street);
        $this->assertNull($row->address_city);
        $this->assertNull($row->address_postal_code);
        $this->assertNull($row->address_country);

        $user->refresh();
        $this->assertNull($user->address);
    }

    #[Test]
    public function it_supports_explicit_column_mapping(): void
    {
        $this->seedAddress();

        $user = UserWithColumns::firstOrFail();

        $this->assertNotNull($user->address);
        $this->assertSame('Rotterdam', $user->address->city);

        $user->address->city = 'Amsterdam';
        $user->save();

        $this->assertSame('Amsterdam', DB::table('users')->where('id', $user->id)->value('address_city'));
    }

    #[Test]
    public function it_serializes_as_a_nested_object_with_the_trait(): void
    {
        $this->seedAddress(['address_verified' => 1]);

        $array = User::firstOrFail()->toArray();

        $this->assertArrayHasKey('address', $array);
        $this->assertSame('Rotterdam', $array['address']['city']);
        $this->assertTrue($array['address']['verified']);

        // Backing columns are hidden by the trait.
        $this->assertArrayNotHasKey('address_city', $array);
        $this->assertArrayNotHasKey('address_street', $array);
    }

    #[Test]
    public function it_keeps_flat_columns_without_the_trait(): void
    {
        $this->seedAddress();

        $array = UserWithColumns::firstOrFail()->toArray();

        $this->assertArrayHasKey('address_city', $array);
        $this->assertArrayNotHasKey('address', $array);
    }

    #[Test]
    public function it_exposes_the_decoded_cast_config(): void
    {
        $cast = EmbeddableCast::using(
            Address::class,
            prefix: 'address_',
            attributes: ['street', 'city'],
            nullable: true,
        );

        $config = EmbeddableCast::configFor($cast);

        $this->assertSame(Address::class, $config['embeddable']);
        $this->assertSame('address_', $config['prefix']);
        $this->assertSame(['street', 'city'], $config['attributes']);
        $this->assertSame([], $config['columns']);
        $this->assertTrue($config['nullable']);
    }

    #[Test]
    public function it_exposes_the_attribute_to_column_map(): void
    {
        $prefixed = EmbeddableCast::using(Address::class, prefix: 'address_', attributes: ['street', 'city']);

        $this->assertSame([
            'street' => 'address_street',
            'city' => 'address_city',
        ], EmbeddableCast::mapFor($prefixed, 'address', new User));

        $mapped = EmbeddableCast::using(Address::class, columns: ['street' => 'street_line']);

        $this->assertSame(['street' => 'street_line'], EmbeddableCast::mapFor($mapped, 'address', new User));
    }

    #[Test]
    public function it_discovers_columns_from_the_schema_with_zero_config(): void
    {
        $this->seedAddress(['address_verified' => 1]);

        $user = UserZeroConfig::firstOrFail();

        $this->assertInstanceOf(Address::class, $user->address);
        $this->assertSame('Coolsingel 1', $user->address->street);
        $this->assertSame('Rotterdam', $user->address->city);
        $this->assertTrue($user->address->verified);

        $user->address->city = 'Amsterdam';
        $user->save();

        $this->assertSame('Amsterdam', DB::table('users')->where('id', $user->id)->value('address_city'));
    }

    #[Test]
    public function it_writes_and_serializes_zero_config_embeddables(): void
    {
        $user = UserZeroConfig::create(['name' => 'Sebastiaan']);

        $user->address = ['street' => 'Coolsingel 1', 'city' => 'Rotterdam'];
        $user->save();

        $this->assertSame('Rotterdam', DB::table('users')->where('id', $user->id)->value('address_city'));

        $array = UserZeroConfig::firstOrFail()->toArray();

        $this->assertSame('Rotterdam', $array['address']['city']);
        $this->assertArrayNotHasKey('address_city', $array);
    }

    #[Test]
    public function it_maps_zero_config_casts_from_the_schema(): void
    {
        $cast = EmbeddableCast::using(Address::class, nullable: true);

        $this->assertSame([
            'street' => 'address_street',
            'city' => 'address_city',
            'postal_code' => 'address_postal_code',
            'country' => 'address_country',
            'verified' => 'address_verified',
        ], EmbeddableCast::mapFor($cast, 'address', new UserZeroConfig));
    }

    #[Test]
    public function it_falls_back_to_fillable_and_casts_when_the_schema_has_no_matches(): void
    {
        $cast = EmbeddableCast::using(Address::class, prefix: 'addr_');

        $this->assertSame([
            'street' => 'addr_street',
            'city' => 'addr_city',
            'postal_code' => 'addr_postal_code',
            'country' => 'addr_country',
            'verified' => 'addr_verified',
        ], EmbeddableCast::mapFor($cast, 'address', new User));
    }

    #[Test]
    public function it_rejects_combining_columns_with_the_prefix_form(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmbeddableCast::using(
            Address::class,
            prefix: 'address_',
            columns: ['street' => 'address_street'],
        );
    }

    #[Test]
    public function it_rejects_an_unresolvable_column_map(): void
    {
        $bare = new class extends EmbeddableModel {};

        $cast = EmbeddableCast::using($bare::class, prefix: 'nope_');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to resolve the column map');

        EmbeddableCast::mapFor($cast, 'address', new class extends Model
        {
            protected $table = 'users';
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedAddress(array $overrides = []): void
    {
        DB::table('users')->insert(array_merge([
            'name' => 'Sebastiaan',
            'address_street' => 'Coolsingel 1',
            'address_city' => 'Rotterdam',
            'address_postal_code' => '3012 AA',
            'address_country' => 'NL',
        ], $overrides));
    }
}
