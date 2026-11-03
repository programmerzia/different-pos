<?php

declare(strict_types=1);

namespace App\Actions\Backup;

use App\Exceptions\Domain\DomainException;
use App\Models\Backup;

/** The nightly run (routes/console.php): records a scheduled row and queues it; skips quietly when one is already running. */
final readonly class RunScheduledBackup
{
    public function __construct(private RequestBackup $request) {}

    public function handle(): ?Backup
    {
        try {
            return $this->request->handle(null, Backup::TRIGGER_SCHEDULED);
        } catch (DomainException) {
            return null;
        }
    }
}
