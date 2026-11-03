<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Actions\Backup\RequestBackup;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\User;
use App\Support\Backup\BackupPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * /app/settings/backups: the installation's backup history (backup.view, read-only) and
 * "Backup Database Now" + archive download (backup.run). The archive is installation-wide (every tenant):
 * the page says so, and only Owners hold these permissions by default.
 */
final class BackupController extends Controller implements HasMiddleware
{
    use TranslatesDomainErrors;

    public static function middleware(): array
    {
        return [
            new Middleware('can:backup.view', only: ['index']),
            new Middleware('can:backup.run', only: ['store', 'download']),
        ];
    }

    public function index(Request $request, BackupPresenter $presenter): Response
    {
        $backups = Backup::query()->with('triggeredBy')->orderByDesc('id')->limit(60)->get();

        return Inertia::render('backup/index', [
            'backups' => $backups->map(fn (Backup $b): array => $presenter->row($b, $this->actor($request)->can('backup.run')))->values()->all(),
            'pending' => $backups->first(static fn (Backup $b): bool => $b->isPending())?->id,
            'policy' => $presenter->policy(),
            'can_run' => $this->actor($request)->can('backup.run'),
        ]);
    }

    public function store(Request $request, RequestBackup $request_backup): RedirectResponse
    {
        $this->guarded(fn (): Backup => $request_backup->handle($this->actor($request)));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.backup_started')]);

        return back();
    }

    public function download(Request $request, int $backup): StreamedResponse
    {
        $this->actor($request);
        $model = Backup::query()->findOrFail($backup);
        abort_unless($model->isDownloadable() && $model->disk !== null && $model->path !== null, 404);
        $disk = Storage::disk($model->disk);
        abort_unless($disk->exists($model->path), 404);

        return $disk->download($model->path, basename($model->path));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
