<?php

declare(strict_types=1);

require_once __DIR__.'/../Admin/AdminHelpers.php';

use App\Support\Permissions\SystemRoles;
use Inertia\Testing\AssertableInertia as Assert;

it('lists only the active branches the user is assigned to, last used first', function (): void {
    [$user, $tenant, $first] = adminActor();
    $second = addBranch($tenant, ['name' => 'Zed branch']);
    $inactive = addBranch($tenant, ['is_active' => false]);
    addBranch($tenant); // not assigned
    $user->branches()->attach([$second->id, $inactive->id]);
    $user->forceFill(['preferences' => ['last_branch_id' => $second->id]])->save();

    $this->actingAs($user)->get('/app/branch/select')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('branch/select')
        ->has('branches', 2)
        ->where('branches.0.id', $second->id)
        ->where('branches.1.id', $first->id)
        ->where('lastBranchId', $second->id));
});

it('switches branch, stores it in the session and remembers it', function (): void {
    [$user, $tenant, $first] = adminActor();
    $second = addBranch($tenant);
    $user->branches()->attach($second);

    $this->actingAs($user)->post("/app/branch/{$second->id}/switch")->assertRedirect('/app');

    expect(session('branch_id'))->toBe($second->id)
        ->and($user->refresh()->preferences['last_branch_id'])->toBe($second->id);
    $this->get('/app')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.branch_id', $second->id));
    expect($first->id)->not->toBe($second->id);
});

it('keeps the other preferences when remembering the branch', function (): void {
    [$user, $tenant] = adminActor();
    $user->forceFill(['preferences' => ['scheme' => 'rose']])->save();
    $second = addBranch($tenant);
    $user->branches()->attach($second);

    $this->actingAs($user)->post("/app/branch/{$second->id}/switch");

    expect($user->refresh()->preferences)->toBe(['scheme' => 'rose', 'last_branch_id' => $second->id]);
});

it('404s a switch to a branch the user is not assigned to, an inactive one or another tenant\'s', function (): void {
    [$user, $tenant] = adminActor();
    $unassigned = addBranch($tenant);
    $inactive = addBranch($tenant, ['is_active' => false]);
    $user->branches()->attach($inactive);
    [, , $foreign] = adminActor(SystemRoles::OWNER);

    $this->actingAs($user);
    foreach ([$unassigned, $inactive, $foreign] as $branch) {
        $this->post("/app/branch/{$branch->id}/switch")->assertNotFound();
    }
    expect($user->refresh()->preferences)->toBeNull();
});

it('requires authentication to switch', function (): void {
    [, , $branch] = adminActor();

    $this->post("/app/branch/{$branch->id}/switch")->assertRedirect('/login');
});

it('restores the last used branch on a new session instead of asking again', function (): void {
    [$user, $tenant, $first] = adminActor();
    $second = addBranch($tenant);
    $user->branches()->attach($second);
    $user->forceFill(['preferences' => ['last_branch_id' => $second->id]])->save();

    $this->actingAs($user)->get('/app')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.branch_id', $second->id));
    expect($first->id)->not->toBe($second->id);
});

it('sends a multi-branch user with no memory to the branch picker', function (): void {
    [$user, $tenant] = adminActor();
    $user->branches()->attach(addBranch($tenant));

    $this->actingAs($user)->get('/app')->assertRedirect('/app/branch/select');
});

it('ignores a remembered branch that was deactivated', function (): void {
    [$user, $tenant, $first] = adminActor();
    $second = addBranch($tenant, ['is_active' => false]);
    $user->branches()->attach($second);
    $user->forceFill(['preferences' => ['last_branch_id' => $second->id]])->save();

    $this->actingAs($user)->get('/app')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.branch_id', $first->id));
});
