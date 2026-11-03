<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('renders the sign-in pages in the language chosen by the guest locale cookie', function (): void {
    $this->withUnencryptedCookie('locale', 'bn')->get('/login')
        ->assertOk()->assertSee('<html lang="bn"', false)
        ->assertInertia(fn (Assert $page) => $page->where('preferences.locale', 'bn'));
    $this->withUnencryptedCookie('locale', 'bn')->get('/forgot-password')->assertSee('<html lang="bn"', false);
});

it('ignores an unsupported or missing locale cookie', function (): void {
    $this->withUnencryptedCookie('locale', 'fr')->get('/login')
        ->assertInertia(fn (Assert $page) => $page->where('preferences.locale', 'en'));
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('preferences.locale', 'en'));
});
