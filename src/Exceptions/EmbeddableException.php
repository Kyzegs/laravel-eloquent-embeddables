<?php

declare(strict_types=1);

namespace Kyzegs\EloquentEmbeddables\Exceptions;

use BadMethodCallException;

class EmbeddableException extends BadMethodCallException
{
    public static function cannotPersist(): self
    {
        return new self(
            'Embeddables are persisted through their parent Eloquent model. Call save() on the parent model instead.'
        );
    }

    public static function cannotRelate(): self
    {
        return new self(
            'Embeddables do not participate in relationships. Define the relationship on the parent Eloquent model instead.'
        );
    }
}
