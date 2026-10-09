<?php

namespace Tests\Feature;

use App\Models\OrderOverride;
use App\Models\User;
use App\Services\OrderQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->create(['role' => 'admin', 'has_access' => true]); }

    public function test_staff_cannot_access_payments(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'has_access' => true]);
        $this->actingAs($staff)->get('/admin/payments')->assertForbidden();
    }

    public function test_admin_sees_payments_page_with_filters(): void
    {
        $this->actingAs($this->admin())->get('/admin/payments?method=gcash')->assertOk()->assertSee('All payments');
    }

    public function test_admin_can_mark_a_cod_order_as_paid(): void
    {
        $order = collect(app(OrderQueryService::class)->allOrders())
            ->first(fn ($o) => $o['paymentMethod'] === 'cod' && $o['effectivePaymentStatus'] !== 'paid');
        $this->assertNotNull($order, 'Mock data should contain at least one unpaid COD order.');

        $this->actingAs($this->admin())->post(route('admin.payments.mark-paid', $order['id']))->assertRedirect();

        $this->assertTrue(OrderOverride::where('order_id', $order['id'])->first()->cod_marked_paid);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.cod_marked_paid']);
    }

    public function test_cannot_mark_a_non_cod_order_paid_this_way(): void
    {
        $order = collect(app(OrderQueryService::class)->allOrders())->firstWhere('paymentMethod', 'gcash');
        $this->actingAs($this->admin())->post(route('admin.payments.mark-paid', $order['id']))->assertSessionHasErrors('order');
    }
}
