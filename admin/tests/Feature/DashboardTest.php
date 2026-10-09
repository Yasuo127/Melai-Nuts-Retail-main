<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_all_sections_for_each_period(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'has_access' => true]));
        foreach (['today', 'week', 'month'] as $p) {
            $this->get("/dashboard?period=$p")->assertOk()
                ->assertSee('Sales per branch')->assertSee('Stock levels per branch')
                ->assertSee('Driver and delivery tracking')->assertSee('Pending refunds')->assertSee('Lowest stock');
        }
    }

    public function test_drivers_endpoint_returns_json_and_needs_access(): void
    {
        $this->getJson('/dashboard/drivers.json')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['has_access' => false]))->get('/dashboard/drivers.json')->assertRedirect(route('waiting'));
        $this->actingAs(User::factory()->create(['has_access' => true]))
            ->getJson('/dashboard/drivers.json')->assertOk()->assertJsonStructure([['driverId', 'lat', 'lng', 'updatedAt']]);
    }

}
