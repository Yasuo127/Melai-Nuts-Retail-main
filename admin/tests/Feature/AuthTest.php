<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function reg(array $o = []): array
    {
        return array_merge(['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com',
            'password' => 'Str0ng!Pass', 'password_confirmation' => 'Str0ng!Pass'], $o);
    }

    private function admin(): User { return User::factory()->create(['role' => 'admin', 'has_access' => true]); }
    private function staff(): User { return User::factory()->create(['role' => 'staff', 'has_access' => false]); }

    public function test_landing_shows_welcome_admin(): void
    {
        $this->get('/')->assertOk()->assertSee('WELCOME ADMIN');
    }

    public function test_empty_fields(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
        $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password', 'password_confirmation']);
    }

    public function test_invalid_email(): void
    {
        $this->post('/register', $this->reg(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'nope', 'password' => 'x'])->assertSessionHasErrors('email');
    }

    public function test_duplicate_email_is_case_insensitive(): void
    {
        User::factory()->create(['email' => 'juan@example.com']);
        $this->post('/register', $this->reg(['email' => '  JUAN@Example.com ']))->assertSessionHasErrors('email');
    }

    public function test_weak_passwords_rejected(): void
    {
        foreach (['Ab1!', 'alllowercase1!', 'ALLUPPERCASE1!', 'NoNumbers!!Aa', 'NoSymbols123Aa'] as $pw) {
            $this->post('/register', $this->reg(['password' => $pw, 'password_confirmation' => $pw]))
                ->assertSessionHasErrors('password');
        }
    }

    public function test_mismatched_passwords(): void
    {
        $this->post('/register', $this->reg(['password_confirmation' => 'Different1!']))
            ->assertSessionHasErrors('password_confirmation');
    }

    public function test_register_creates_staff_without_access_and_logs_in(): void
    {
        $this->post('/register', $this->reg(['email' => ' Juan@Example.com ', 'role' => 'admin']))
            ->assertRedirect(route('waiting'));

        $user = User::firstWhere('email', 'juan@example.com');
        $this->assertSame('staff', $user->role);            // role can't be injected
        $this->assertFalse($user->has_access);
        $this->assertTrue(Hash::check('Str0ng!Pass', $user->password));
        $this->assertStringStartsWith('$2y$', $user->password); // bcrypt
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.created', 'user_id' => $user->id]);
    }

    public function test_new_account_only_sees_waiting_page(): void
    {
        $this->actingAs($this->staff());
        $this->get('/waiting')->assertOk()->assertSee('Waiting for admin access');
        $this->get('/dashboard')->assertRedirect(route('waiting'));
    }

    public function test_staff_gets_403_on_admin_pages(): void
    {
        $this->actingAs($this->staff())->get('/admin/activity-logs')->assertForbidden();
    }

    public function test_admin_reaches_dashboard_and_logs(): void
    {
        $this->actingAs($this->admin());
        $this->get('/dashboard')->assertOk();
        $this->get('/admin/activity-logs')->assertOk();
    }

    public function test_wrong_credentials_use_generic_message(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'Str0ng!Pass']);
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['auth' => 'Invalid email or password.']);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['auth' => 'Invalid email or password.']);
    }

    public function test_lockout_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'Str0ng!Pass']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'a@example.com', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'Str0ng!Pass']);
        $this->assertGuest();
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('auth'));
    }

    public function test_login_success_logs_activity(): void
    {
        $u = User::factory()->create(['email' => 'a@example.com', 'password' => 'Str0ng!Pass', 'has_access' => true]);
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'Str0ng!Pass'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->assertTrue(ActivityLog::where('action', 'auth.login')->exists());
    }

    public function test_deactivated_user_cannot_login(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'Str0ng!Pass', 'is_active' => false]);
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'Str0ng!Pass'])->assertSessionHasErrors('auth');
        $this->assertGuest();
    }

    public function test_guests_are_redirected_and_logged_in_users_are_kept_out_of_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/activity-logs')->assertRedirect(route('login'));
        $this->actingAs($this->admin())->get('/login')->assertRedirect(route('dashboard'));
        $this->get('/register')->assertRedirect(route('dashboard'));
    }

    public function test_logout_invalidates_session(): void
    {
        $this->actingAs($this->admin())->post('/logout')->assertRedirect(route('landing'));
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
