<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_forgot_link_and_password_toggle_script(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'))->assertSee('js/password-toggle.js');
    }

    public function test_full_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'staf@example.com']);

        $this->post(route('password.email'), ['email' => 'STAF@example.com'])->assertSessionHas('success');

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email, 'password' => 'PasswordBaru123', 'password_confirmation' => 'PasswordBaru123',
        ])->assertRedirect(route('login'));

        $this->post('/login', ['email' => $user->email, 'password' => 'PasswordBaru123'])->assertRedirect();
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset', 'user_id' => null]);

        // Token sekali pakai
        $this->post(route('logout'));
        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email, 'password' => 'PasswordLain123', 'password_confirmation' => 'PasswordLain123',
        ])->assertSessionHasErrors('email');
    }

    public function test_unknown_or_inactive_email_gets_same_message_and_no_mail(): void
    {
        Notification::fake();
        $inactive = User::factory()->create(['email' => 'nonaktif@example.com', 'is_active' => false]);

        $this->post(route('password.email'), ['email' => 'tidakada@example.com'])->assertSessionHas('success');
        $this->post(route('password.email'), ['email' => 'nonaktif@example.com'])->assertSessionHas('success');

        Notification::assertNothingSent();
    }
}
