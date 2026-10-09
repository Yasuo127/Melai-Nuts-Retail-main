<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->create(['role' => 'admin', 'has_access' => true]); }

    private function payload(array $o = []): array
    {
        return array_merge(['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'role' => 'staff',
            'password' => 'Str0ng!Pass', 'password_confirmation' => 'Str0ng!Pass'], $o);
    }

    public function test_staff_cannot_reach_user_management(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'has_access' => true]);
        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
        $this->actingAs($staff)->post('/admin/users', $this->payload())->assertForbidden();
    }

    public function test_admin_creates_staff_account_with_access_already_granted(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', $this->payload())
            ->assertRedirect(route('admin.users.index'));

        $user = User::firstWhere('email', 'ana@example.com');
        $this->assertNotNull($user);
        $this->assertSame('staff', $user->role);
        $this->assertTrue($user->has_access); // granted immediately, unlike self-registration
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Str0ng!Pass', $user->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.created_by_admin']);
    }

    public function test_admin_can_create_a_driver_account(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->payload(['email' => 'driver@example.com', 'role' => 'driver']));
        $this->assertSame('driver', User::firstWhere('email', 'driver@example.com')->role);
    }

    public function test_duplicate_email_rejected(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $this->actingAs($this->admin())->post('/admin/users', $this->payload())->assertSessionHasErrors('email');
    }

    public function test_invalid_role_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->payload(['role' => 'admin']))->assertSessionHasErrors('role');
    }

    public function test_admin_can_toggle_access_and_active_for_staff_not_for_admin(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['role' => 'staff', 'has_access' => false, 'is_active' => true]);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.users.toggle-access', $staff));
        $this->assertTrue($staff->fresh()->has_access);

        $this->actingAs($admin)->post(route('admin.users.toggle-active', $staff));
        $this->assertFalse($staff->fresh()->is_active);

        $this->actingAs($admin)->post(route('admin.users.toggle-access', $otherAdmin))->assertForbidden();
    }
}
