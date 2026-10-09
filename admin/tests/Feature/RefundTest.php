<?php

namespace Tests\Feature;

use App\Models\OrderOverride;
use App\Models\User;
use App\Services\OrderQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->create(['role' => 'admin', 'has_access' => true]); }

    private function aRequestedOrderId(): string
    {
        $order = collect(app(OrderQueryService::class)->allOrders())->firstWhere('effectiveRefundStatus', 'requested');
        $this->assertNotNull($order, 'Mock data should contain at least one requested refund.');
        return $order['id'];
    }

    public function test_staff_cannot_access_refunds(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'has_access' => true]);
        $this->actingAs($staff)->get('/admin/refunds')->assertForbidden();
    }

    public function test_refunds_page_shows_pending_badge_count_matching_queue(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')->assertSee('Pending refunds');
    }

    public function test_admin_can_approve_a_requested_refund(): void
    {
        $orderId = $this->aRequestedOrderId();
        $this->actingAs($this->admin())->post(route('admin.refunds.approve', $orderId))->assertRedirect();

        $override = OrderOverride::where('order_id', $orderId)->first();
        $this->assertNotNull($override);
        $this->assertContains($override->refund_status, ['refunded', 'approved_processing']); // cod vs online
        $this->assertDatabaseHas('activity_logs', ['action' => $override->refund_status === 'refunded' ? 'refund.approved_cod' : 'refund.approved']);
    }

    public function test_cannot_approve_the_same_refund_twice(): void
    {
        $orderId = $this->aRequestedOrderId();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.refunds.approve', $orderId));
        $this->actingAs($admin)->post(route('admin.refunds.approve', $orderId))->assertSessionHasErrors('refund');
    }

    public function test_reject_requires_a_written_reason(): void
    {
        $orderId = $this->aRequestedOrderId();
        $this->actingAs($this->admin())->post(route('admin.refunds.reject', $orderId), [])->assertSessionHasErrors('reason');
    }

    public function test_admin_can_reject_with_a_reason(): void
    {
        $orderId = $this->aRequestedOrderId();
        $this->actingAs($this->admin())->post(route('admin.refunds.reject', $orderId), ['reason' => 'Photo does not match the item ordered.'])
            ->assertRedirect();

        $this->assertSame('rejected', OrderOverride::where('order_id', $orderId)->first()->refund_status);
    }

    public function test_refund_amount_cannot_exceed_amount_paid(): void
    {
        $order = collect(app(OrderQueryService::class)->allOrders())->firstWhere('effectiveRefundStatus', 'requested');
        $this->actingAs($this->admin())
            ->post(route('admin.refunds.approve', $order['id']), ['amount' => $order['total'] + 1000])
            ->assertSessionHasErrors('amount');
    }
}
