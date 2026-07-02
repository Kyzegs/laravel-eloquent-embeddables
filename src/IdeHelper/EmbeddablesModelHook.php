<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\IdeHelper;

use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Barryvdh\LaravelIdeHelper\Contracts\ModelHookInterface;
use Composer\ClassMapGenerator\ClassMapGenerator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Kyzegs\EloquentEmbeddables\Casts\EmbeddableCast;
use Kyzegs\EloquentEmbeddables\EmbeddableModel;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * Model hook for barryvdh/laravel-ide-helper.
 *
 * On parent models: ide-helper resolves embeddable casts through
 * {@see EmbeddableCast::get()} and annotates every embeddable as the generic
 * `EmbeddableModel|null`; this hook replaces that with the concrete embeddable
 * class and the cast's actual nullability.
 *
 * On embeddable models: an embeddable has no table for ide-helper to
 * introspect, so this hook locates the parent models embedding it (scanning
 * the same `model_locations` ide-helper scans) and lets ide-helper's own
 * machinery do the typing: {@see ModelsCommand::getPropertiesFromTable()}
 * types each parent's columns, the results are re-keyed onto the embeddable's
 * attributes, and {@see ModelsCommand::castPropertiesType()} applies the
 * embeddable's own casts on top. Maps from all embedding parents are merged;
 * the first parent to supply an attribute wins.
 *
 * Register it in config/ide-helper.php under `model_hooks`.
 */
class EmbeddablesModelHook implements ModelHookInterface
{
    public function run(ModelsCommand $command, Model $model): void
    {
        if ($model instanceof EmbeddableModel) {
            $this->typeEmbeddableAttributes($command, $model);

            return;
        }

        foreach ($model->getCasts() as $key => $cast) {
            if (! is_string($cast) || ! str_starts_with($cast, EmbeddableCast::class.':')) {
                continue;
            }

            $config = EmbeddableCast::configFor($cast);

            $command->setProperty($key, '\\'.$config['embeddable'], true, true, '', $config['nullable']);
        }
    }

    protected function typeEmbeddableAttributes(ModelsCommand $command, EmbeddableModel $model): void
    {
        $properties = new ReflectionProperty($command, 'properties');
        $methods = new ReflectionProperty($command, 'methods');
        $nullableColumns = new ReflectionProperty($command, 'nullableColumns');

        /** @var array<string, bool> $nullability */
        $nullability = $nullableColumns->getValue($command);

        $typed = [];

        foreach ($this->findEmbeddings($command, $model::class) as [$parent, $map]) {
            $propertiesSnapshot = $properties->getValue($command);
            $methodsSnapshot = $methods->getValue($command);
            $nullableSnapshot = $nullableColumns->getValue($command);

            // Let ide-helper type the parent's columns with its own
            // driver-aware schema logic, then roll the command back.
            $command->getPropertiesFromTable($parent);

            /** @var array<string, array{type: string|null, read: bool, write: bool, comment: string}> $columnProperties */
            $columnProperties = $properties->getValue($command);

            /** @var array<string, bool> $columnNullability */
            $columnNullability = $nullableColumns->getValue($command);

            $properties->setValue($command, $propertiesSnapshot);
            $methods->setValue($command, $methodsSnapshot);
            $nullableColumns->setValue($command, $nullableSnapshot);

            // Re-key the parent's typed columns to the embeddable's
            // attributes; the first parent to supply an attribute wins.
            foreach ($map as $attribute => $column) {
                if (isset($typed[$attribute]) || ! isset($columnProperties[$column])) {
                    continue;
                }

                $typed[$attribute] = true;

                $property = $columnProperties[$column];

                $command->setProperty($attribute, $property['type'], true, true, $property['comment']);

                if ($columnNullability[$column] ?? false) {
                    $nullability[$attribute] = true;
                }
            }
        }

        if ($typed === []) {
            return;
        }

        $nullableColumns->setValue($command, $nullability);

        // Apply the embeddable's own casts with ide-helper's cast logic.
        $command->castPropertiesType($model);
    }

    /**
     * Locate every parent model embedding the given class by scanning the
     * model locations ide-helper itself scans.
     *
     * @param  class-string<EmbeddableModel>  $embeddable
     * @return iterable<array{0: Model, 1: array<string, string>}> Parent instance + attribute => column map.
     */
    protected function findEmbeddings(ModelsCommand $command, string $embeddable): iterable
    {
        $seen = [];

        foreach ($this->modelClasses($command) as $class) {
            if (isset($seen[$class])) {
                continue;
            }

            $seen[$class] = true;

            try {
                $reflection = new ReflectionClass($class);

                if (
                    ! $reflection->isSubclassOf(Model::class)
                    || $reflection->isSubclassOf(EmbeddableModel::class)
                    || ! $reflection->isInstantiable()
                ) {
                    continue;
                }

                /** @var Model $parent */
                $parent = new $class;

                foreach ($parent->getCasts() as $key => $cast) {
                    if (! is_string($cast) || ! str_starts_with($cast, EmbeddableCast::class.':')) {
                        continue;
                    }

                    if (EmbeddableCast::configFor($cast)['embeddable'] === $embeddable) {
                        yield [$parent, EmbeddableCast::mapFor($cast, $key, $parent)];
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
     * Lazily yield the classes in ide-helper's model locations, so callers
     * can stop scanning as soon as they find what they need.
     *
     * @return iterable<class-string>
     */
    protected function modelClasses(ModelsCommand $command): iterable
    {
        /** @var Repository $config */
        $config = $command->getLaravel()->make('config');

        /** @var list<string> $locations */
        $locations = $config->get('ide-helper.model_locations', []);

        foreach ($locations as $location) {
            $directory = is_dir(base_path($location)) ? base_path($location) : $location;

            if (! is_dir($directory)) {
                continue;
            }

            foreach (ClassMapGenerator::createMap($directory) as $class => $path) {
                yield $class;
            }
        }
    }
}
