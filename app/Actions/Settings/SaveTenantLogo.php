<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sets (or with null, removes) the current business's logo: tenants.branding.logo_path on the public disk.
 * The new file is written before the row changes and removed again if the write fails; the previous file
 * is deleted only after the row is committed, so a failure never leaves the tenant pointing at nothing.
 */
final readonly class SaveTenantLogo
{
    public function __construct(private TenantContext $context) {}

    public function handle(User $actor, ?UploadedFile $logo): Tenant
    {
        $disk = Storage::disk('public');
        $tenantId = $this->context->tenantId();
        $newPath = null;

        if ($logo instanceof UploadedFile) {
            $extension = strtolower($logo->guessExtension() ?? $logo->getClientOriginalExtension());
            $extension = $extension === 'jpeg' ? 'jpg' : $extension;
            $stored = $disk->putFileAs("tenants/{$tenantId}", $logo, 'logo-'.Str::lower(Str::random(12)).'.'.$extension);
            if (! is_string($stored)) {
                throw new RuntimeException('The logo could not be stored.');
            }
            $newPath = $stored;
        }

        try {
            [$tenant, $oldPath] = Transaction::run(function () use ($actor, $tenantId, $newPath): array {
                $tenant = Tenant::query()->findOrFail($tenantId);
                $branding = $tenant->branding ?? [];
                $oldPath = is_string($branding['logo_path'] ?? null) ? $branding['logo_path'] : null;

                if ($newPath === null) {
                    unset($branding['logo_path']);
                } else {
                    $branding['logo_path'] = $newPath;
                }
                $tenant->branding = $branding === [] ? null : $branding;
                $tenant->save();

                Audit::log('settings.logo_updated', $tenant, ['action' => $newPath === null ? 'removed' : 'uploaded'], $actor);

                return [$tenant, $oldPath];
            });
        } catch (Throwable $e) {
            if ($newPath !== null) {
                $disk->delete($newPath);
            }

            throw $e;
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            $disk->delete($oldPath);
        }

        return $tenant;
    }
}
