<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\User;
use App\Support\Backup\BackupPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** GET /app/api/backups/{backup}: the row the page polls while a run is queued or running. */
final class BackupStatusController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:backup.view')];
    }

    public function __invoke(Request $request, int $backup, BackupPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json($presenter->row(Backup::query()->with('triggeredBy')->findOrFail($backup), $user->can('backup.run')));
    }
}
