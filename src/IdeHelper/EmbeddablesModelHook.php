<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\IdeHelper;

use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Barryvdh\LaravelIdeHelper\Contracts\ModelHookInterface;
use Illuminate\Database\Eloquent\Model;
use Kyzegs\EloquentEmbeddables\Casts\EmbeddableCast;

/**
 * Model hook for barryvdh/laravel-ide-helper.
 *
 * Without it, ide-helper resolves embeddable casts through {@see EmbeddableCast::get()}
 * and annotates every embeddable as the generic `EmbeddableModel|null`. This hook
 * replaces that with the concrete embeddable class and the cast's actual nullability.
 *
 * Register it in config/ide-helper.php under `model_hooks`.
 */
class EmbeddablesModelHook implements ModelHookInterface
{
    public function run(ModelsCommand $command, Model $model): void
    {
        foreach ($model->getCasts() as $key => $cast) {
            if (! is_string($cast) || ! str_starts_with($cast, EmbeddableCast::class.':')) {
                continue;
            }

            $config = EmbeddableCast::configFor($cast);

            $command->setProperty($key, '\\'.$config['embeddable'], true, true, '', $config['nullable']);
        }
    }
}
