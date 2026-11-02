<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Exceptions\Domain\DomainException;
use Illuminate\Validation\ValidationException;

/** Turns a business-rule violation from an Action into a form error instead of a 500. */
trait TranslatesDomainErrors
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function guarded(callable $callback, string $field = 'form'): mixed
    {
        try {
            return $callback();
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }
}
