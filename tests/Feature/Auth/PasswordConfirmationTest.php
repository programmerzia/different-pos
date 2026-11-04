<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page): AssertableInertia => $page
            ->component('auth/confirm-password'),
        );
    }

    public function test_password_confirmation_requires_authentication(): void
    {
        $response = $this->get(route('password.confirm'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_right_password_confirms(): void
    {
        $user = User::factory()->create(['password' => 'secret-password-1']);

        $response = $this->actingAs($user)->post('/user/confirm-password', ['password' => 'secret-password-1']);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('auth.password_confirmed_at');
    }

    public function test_the_wrong_password_does_not_confirm(): void
    {
        $user = User::factory()->create(['password' => 'secret-password-1']);

        $response = $this->actingAs($user)->post('/user/confirm-password', ['password' => 'wrong-password']);

        $response->assertSessionHasErrors('password');
        $response->assertSessionMissing('auth.password_confirmed_at');
    }
}
