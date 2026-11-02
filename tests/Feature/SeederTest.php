<?php

declare(strict_types=1);

use App\Models\User;

it('the demo seeder yields an owner who can open the dashboard', function (): void {
    $this->seed();
    $this->seed();

    $owner = User::query()->where('email', 'owner@demo.test')->firstOrFail();

    $this->actingAs($owner)->get('/app')->assertOk();
});
