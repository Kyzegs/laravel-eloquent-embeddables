<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Tests;

use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\PendingCommand;
use Kyzegs\EloquentEmbeddables\Casts\EmbeddableCast;
use Kyzegs\EloquentEmbeddables\IdeHelper\EmbeddablesModelHook;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\Address;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\User;
use Kyzegs\EloquentEmbeddables\Tests\Fixtures\UserWithColumns;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

class EmbeddablesModelHookTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [IdeHelperServiceProvider::class];
    }

    #[Test]
    public function it_integrates_with_the_ide_helper_models_command(): void
    {
        config()->set('ide-helper.model_hooks', [EmbeddablesModelHook::class]);

        $filename = tempnam(sys_get_temp_dir(), 'ide-helper-models');
        $this->assertNotFalse($filename);

        $command = $this->artisan('ide-helper:models', [
            'model' => [User::class],
            '--filename' => $filename,
            '--nowrite' => true,
            // The User fixture carries hand-written @property tags, which
            // ide-helper keeps unless reset; reset so the generated (hooked)
            // properties are observable.
            '--reset' => true,
        ]);

        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful();
        $command->run();

        $contents = (string) file_get_contents($filename);
        unlink($filename);

        $this->assertStringContainsString(
            '@property \\'.Address::class.'|null $address',
            $contents,
        );
    }

    #[Test]
    public function it_sets_the_concrete_embeddable_type_on_the_parent(): void
    {
        $properties = $this->runHook(new User);

        $this->assertArrayHasKey('address', $properties);
        $this->assertSame('\\'.Address::class.'|null', $properties['address']['type']);
        $this->assertTrue($properties['address']['read']);
        $this->assertTrue($properties['address']['write']);
    }

    #[Test]
    public function it_supports_the_explicit_column_form(): void
    {
        $properties = $this->runHook(new UserWithColumns);

        $this->assertSame('\\'.Address::class.'|null', $properties['address']['type'] ?? null);
    }

    #[Test]
    public function it_omits_null_for_non_nullable_embeddables(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';

            protected function casts(): array
            {
                return [
                    'address' => EmbeddableCast::using(
                        Address::class,
                        prefix: 'address_',
                        attributes: ['street', 'city'],
                    ),
                ];
            }
        };

        $properties = $this->runHook($model);

        $this->assertSame('\\'.Address::class, $properties['address']['type'] ?? null);
    }

    #[Test]
    public function it_ignores_models_without_embeddable_casts(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';

            protected function casts(): array
            {
                return ['address_verified' => 'boolean'];
            }
        };

        $this->assertSame([], $this->runHook($model));
    }

    /**
     * @return array<string, array{type: string, read: bool, write: bool, comment: string}>
     */
    private function runHook(Model $model): array
    {
        $app = $this->app;
        $this->assertNotNull($app);

        $command = $app->make(ModelsCommand::class);
        $command->setLaravel($app);

        (new EmbeddablesModelHook)->run($command, $model);

        /** @var array<string, array{type: string, read: bool, write: bool, comment: string}> */
        return (new ReflectionProperty($command, 'properties'))->getValue($command);
    }
}
