<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Tests;

use Illuminate\Support\Facades\DB;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\Address;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\User;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\UserWithColumns;
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
