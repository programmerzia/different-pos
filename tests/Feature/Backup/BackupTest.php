<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Admin/AdminHelpers.php';

use App\Actions\Backup\RequestBackup;
use App\Actions\Backup\RunBackup;
use App\Actions\Backup\RunScheduledBackup;
use App\Backup\BackupRunner;
use App\Exceptions\Domain\InvalidAdminData;
use App\Jobs\RunBackupJob;
use App\Models\Backup;
use App\Support\Permissions\SystemRoles;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Backup\FakeBackupRunner;

// The page components are built by the owner (npm run build); these tests only assert the Inertia props.
beforeEach(fn () => $this->withoutVite());

function fakeRunner(?string $failWith = null): FakeBackupRunner
{
    $runner = new FakeBackupRunner($failWith);
    app()->instance(BackupRunner::class, $runner);

    return $runner;
}

// Actions

it('records a queued run, audits it and dispatches the job', function (): void {
    Queue::fake();
    [$owner] = adminActor();

    $backup = app(RequestBackup::class)->handle($owner);

    expect($backup->status)->toBe(Backup::STATUS_QUEUED)->and($backup->trigger)->toBe(Backup::TRIGGER_MANUAL)->and($backup->triggered_by)->toBe($owner->id);
    Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $job): bool => $job->backupId === $backup->id);
    expect(Activity::query()->where('event', 'backup.requested')->where('subject_id', $backup->id)->exists())->toBeTrue();
});

it('completes the row from the runner and cleans up old archives', function (): void {
    $runner = fakeRunner();
    $backup = Backup::query()->create(['status' => Backup::STATUS_QUEUED]);

    $done = app(RunBackup::class)->handle($backup);

    expect($done->status)->toBe(Backup::STATUS_COMPLETED)->and($done->disk)->toBe('local')->and($done->path)->toBe('POS/2026-11-03-02-00-00.zip')
        ->and($done->size_bytes)->toBe(14_200_000)->and($done->started_at)->not->toBeNull()->and($done->finished_at)->not->toBeNull()
        ->and($runner->runs)->toBe(1)->and($runner->cleanups)->toBe(1);
});

it('records a failed run with its error instead of throwing', function (): void {
    fakeRunner('mysqldump: Got error: 1045');
    $backup = Backup::query()->create(['status' => Backup::STATUS_QUEUED]);

    $done = app(RunBackup::class)->handle($backup);

    expect($done->status)->toBe(Backup::STATUS_FAILED)->and($done->error)->toContain('1045')->and($done->path)->toBeNull();
});

it('refuses a second run while one is queued or running, and the job skips a row that is no longer queued', function (): void {
    Queue::fake();
    $runner = fakeRunner();
    [$owner] = adminActor();
    app(RequestBackup::class)->handle($owner);

    expect(fn () => app(RequestBackup::class)->handle($owner))->toThrow(InvalidAdminData::class, 'already running');

    $failed = Backup::query()->create(['status' => Backup::STATUS_FAILED]);
    expect(app(RunBackup::class)->handle($failed)->status)->toBe(Backup::STATUS_FAILED)->and($runner->runs)->toBe(0);
});

it('schedules a nightly row and skips quietly when one is pending', function (): void {
    Queue::fake();
    $first = app(RunScheduledBackup::class)->handle();
    expect($first)->not->toBeNull()->and($first?->trigger)->toBe(Backup::TRIGGER_SCHEDULED)->and($first?->triggered_by)->toBeNull();

    expect(app(RunScheduledBackup::class)->handle())->toBeNull();
});

// Controller + authorization

it('needs backup.view for the page and backup.run to start or download', function (): void {
    $users = array_map(static fn (string $role) => adminActor($role)[0], [SystemRoles::MANAGER, SystemRoles::CASHIER, SystemRoles::ACCOUNTANT]);
    foreach ($users as $user) {
        $this->actingAs($user)->get('/app/settings/backups')->assertForbidden();
        $this->post('/app/settings/backups')->assertForbidden();
        $this->get('/app/settings/backups/1/download')->assertForbidden();
        $this->getJson('/app/api/backups/1')->assertForbidden();
    }
});

it('shows the history and policy to an Owner and runs a backup on demand', function (): void {
    $runner = fakeRunner(); // the sync queue runs the job inline
    [$owner] = adminActor();

    $this->actingAs($owner)->get('/app/settings/backups')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('backup/index')->where('can_run', true)->where('policy.retention_days', 30)->has('backups', 0));

    $this->post('/app/settings/backups')->assertRedirect();

    $backup = Backup::query()->latest('id')->firstOrFail();
    expect($backup->status)->toBe(Backup::STATUS_COMPLETED)->and($backup->triggered_by)->toBe($owner->id)->and($runner->runs)->toBe(1);

    $this->get('/app/settings/backups')->assertInertia(fn (Assert $page) => $page
        ->has('backups', 1)->where('backups.0.status', 'completed')->where('backups.0.triggered_by', $owner->name)->where('backups.0.filename', '2026-11-03-02-00-00.zip'));
    $this->getJson("/app/api/backups/{$backup->id}")->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('size_bytes', 14_200_000);
});

it('downloads a local archive and never one that lives on a cloud disk', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('POS/a.zip', 'zip-bytes');
    [$owner] = adminActor();
    $local = Backup::query()->create(['status' => Backup::STATUS_COMPLETED, 'disk' => 'local', 'path' => 'POS/a.zip', 'size_bytes' => 9]);
    $cloud = Backup::query()->create(['status' => Backup::STATUS_COMPLETED, 'disk' => 's3', 'path' => 'POS/b.zip', 'size_bytes' => 9]);
    $missing = Backup::query()->create(['status' => Backup::STATUS_COMPLETED, 'disk' => 'local', 'path' => 'POS/gone.zip', 'size_bytes' => 9]);

    $this->actingAs($owner)->get("/app/settings/backups/{$local->id}/download")->assertOk()->assertHeader('content-disposition', 'attachment; filename=a.zip');
    $this->get("/app/settings/backups/{$cloud->id}/download")->assertNotFound();
    $this->get("/app/settings/backups/{$missing->id}/download")->assertNotFound();

    $this->get('/app/settings/backups')->assertInertia(fn (Assert $page) => $page
        ->where('backups.2.download_url', route('settings.backups.download', $local))->where('backups.1.download_url', null));
});

it('is installation-wide: every Owner sees the same history, read-only without backup.run', function (): void {
    Backup::query()->create(['status' => Backup::STATUS_COMPLETED, 'disk' => 'local', 'path' => 'POS/a.zip', 'size_bytes' => 9]);
    [$ownerA] = adminActor();
    [$ownerB] = adminActor();

    $this->actingAs($ownerA)->get('/app/settings/backups')->assertInertia(fn (Assert $page) => $page->has('backups', 1));
    $this->actingAs($ownerB)->get('/app/settings/backups')->assertInertia(fn (Assert $page) => $page->has('backups', 1));
});
