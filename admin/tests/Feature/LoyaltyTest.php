<?php

namespace Tests\Feature;

use App\Models\LoyaltyEntry;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->create(['role' => 'admin', 'has_access' => true]); }

    private function staff(): User { return User::factory()->create(['role' => 'staff', 'has_access' => true]); }

    private function aMember(): array { return app(LoyaltyService::class)->members()[0]; }

    public function test_members_list_shows_search_and_totals(): void
    {
        $this->actingAs($this->staff())->get('/loyalty')->assertOk()
            ->assertSee('Members')->assertSee('Balance');
    }

    public function test_member_detail_page_shows_points_history(): void
    {
        $member = $this->aMember();
        $this->actingAs($this->staff())->get(route('loyalty.show', $member['id']))->assertOk()->assertSee('Points history');
    }

    public function test_staff_cannot_edit_loyalty_settings(): void
    {
        $this->actingAs($this->staff())
            ->put(route('admin.loyalty.settings.update'), ['earn_rate_pesos' => 2, 'redeem_points_per_peso' => 10, 'max_redeem_per_order' => 500, 'expiry_months' => 12])
            ->assertForbidden();
    }

    public function test_admin_can_edit_loyalty_settings(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.loyalty.settings.update'), ['earn_rate_pesos' => 2, 'redeem_points_per_peso' => 20, 'max_redeem_per_order' => 300, 'expiry_months' => 6])
            ->assertRedirect();

        $this->assertDatabaseHas('loyalty_settings', ['earn_rate_pesos' => 2, 'redeem_points_per_peso' => 20]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'loyalty.settings_updated']);
    }

    public function test_staff_cannot_adjust_points(): void
    {
        $member = $this->aMember();
        $this->actingAs($this->staff())
            ->post(route('admin.loyalty.adjust', $member['id']), ['points' => 10, 'reason' => 'Goodwill gesture'])
            ->assertForbidden();
    }

    public function test_manual_adjustment_requires_a_reason(): void
    {
        $member = $this->aMember();
        $this->actingAs($this->admin())
            ->post(route('admin.loyalty.adjust', $member['id']), ['points' => 10])
            ->assertSessionHasErrors('reason');
    }

    public function test_points_must_be_a_whole_number(): void
    {
        $member = $this->aMember();
        $this->actingAs($this->admin())
            ->post(route('admin.loyalty.adjust', $member['id']), ['points' => 10.5, 'reason' => 'Oops'])
            ->assertSessionHasErrors('points');
    }

    public function test_admin_can_add_points_manually(): void
    {
        $member = $this->aMember();
        $this->actingAs($this->admin())
            ->post(route('admin.loyalty.adjust', $member['id']), ['points' => 25, 'reason' => 'Apology for late delivery'])
            ->assertRedirect();

        $this->assertDatabaseHas('loyalty_entries', ['member_id' => $member['id'], 'points' => 25, 'type' => 'adjusted']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'loyalty.adjusted']);
    }

    public function test_cannot_deduct_more_points_than_the_balance(): void
    {
        $member = $this->aMember();
        $tooMany = $member['balance'] + 1000;
        $this->actingAs($this->admin())
            ->post(route('admin.loyalty.adjust', $member['id']), ['points' => -$tooMany, 'reason' => 'Testing overdraw'])
            ->assertSessionHasErrors('points');
    }

    public function test_awarding_points_for_an_order_is_idempotent(): void
    {
        $member = $this->aMember();
        $service = app(LoyaltyService::class);

        $service->awardForOrder($member['id'], $member['name'], 'order-123', 500);
        $service->awardForOrder($member['id'], $member['name'], 'order-123', 500); // same order again

        $this->assertSame(1, LoyaltyEntry::where(['member_id' => $member['id'], 'order_id' => 'order-123', 'type' => 'earned'])->count());
    }

    public function test_deducting_points_for_a_refund_flags_a_negative_balance(): void
    {
        $member = $this->aMember();
        $service = app(LoyaltyService::class);

        // Deduct far more than this member currently has, so the balance goes negative and gets flagged.
        $service->deductForRefund($member['id'], $member['name'], 'order-999', $member['balance'] + 500);

        $this->assertDatabaseHas('loyalty_entries', [
            'member_id' => $member['id'], 'order_id' => 'order-999', 'type' => 'refunded', 'flagged_negative' => true,
        ]);
    }
}
