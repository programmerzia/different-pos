<?php

declare(strict_types=1);

namespace App\Exceptions\Tenancy;

use LogicException;

/** A model belonging to another tenant/branch was about to be written. */
final class CrossTenantWrite extends LogicException
{
    public static function for(string $model, string $column, int|string|null $expected, int|string|null $actual): self
    {
        return new self(sprintf('Refusing to write %s: %s is %s but the current context is %s.', $model, $column, var_export($actual, true), var_export($expected, true)));
    }
}
