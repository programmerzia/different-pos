<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use RuntimeException;

/** Base for business-rule violations. Rendered as a 422 with a translatable message key. */
abstract class DomainException extends RuntimeException {}
