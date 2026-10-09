<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * End-to-end tests of the admin portal against a real Postgres that has the Melai Nuts
 * Supabase schema + migrations (built by tests/Supabase/prepare-test-db.sh).
 *
 * "App" actions (customer checkout, refund requests) are made the way the Flutter app makes
 * them: calling the same SQL functions as the `authenticated` role with a Firebase UID.
 * The portal is exercised through real HTTP requests with DATA_SOURCE=supabase, connected
 * as the unprivileged `melai_admin_portal` role.
 *
 * Skipped unless SUPABASE_TEST_ADMIN_URL and SUPABASE_TEST_PORTAL_URL are set.
 */
#[Group('supabase')]
class SupabaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $sfx;
    private array $fx = [];

    protected function setUp(): void
    {
        parent::setUp();
        $admin = env('SUPABASE_TEST_ADMIN_URL');
        $portal = env('SUPABASE_TEST_PORTAL_URL');
        if (! $admin || ! $portal) {
            $this->markTestSkipped('Set SUPABASE_TEST_ADMIN_URL and SUPABASE_TEST_PORTAL_URL (see tests/Supabase/prepare-test-db.sh).');
        }

        config([
            'melai.data_source' => 'supabase',
            'database.connections.supabase' => ['driver' => 'pgsql', 'url' => $portal, 'sslmode' => 'disable', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public'],
            'database.connections.supabase_admin' => ['driver' => 'pgsql', 'url' => $admin, 'sslmode' => 'disable', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public'],
        ]);
        DB::purge('supabase');
        DB::purge('supabase_admin');

        $this->sfx = strtolower(Str::random(8));
        $this->seedApp();
    }

    // ---- fixtures: written as the database owner, like the one-time SQL bootstrap ----------

    private function db()
    {
        return DB::connection('supabase_admin');
    }

    /** Run SQL as an app user (customer or staff), exactly like the Flutter app through Supabase. */
    private function asAppUser(string $uid, string $sql, array $bindings = []): mixed
    {
        return $this->db()->transaction(function ($db) use ($uid, $sql, $bindings) {
            $db->select("select set_config('request.jwt.claims', ?, true)", [json_encode(['sub' => $uid, 'role' => 'authenticated', 'email' => "$uid@test.local", 'email_verified' => true])]);
            $db->statement('set local role authenticated');
            $row = (array) $db->selectOne($sql, $bindings);

            return reset($row);
        });
    }

    private function seedApp(): void
    {
        $s = $this->sfx;
        $db = $this->db();
        foreach (['a' => "IT-$s Calamba", 'b' => "IT-$s Los Banos"] as $k => $name) {
            $this->fx["branch_$k"] = $db->selectOne('insert into public.branches (name, address, delivery_fee) values (?, ?, 30) returning id', [$name, 'addr'])->id;
            $this->fx["branch_{$k}_name"] = $name;
        }
        $cat = $db->selectOne("insert into public.product_categories (label) values (?) returning id", ["IT-$s Nuts"])->id;
        $this->fx['product'] = $db->selectOne("insert into public.products (category_id, name, price, unit) values (?, ?, 100, 'pack') returning id", [$cat, "IT-$s Garlic Peanuts"])->id;
        $this->fx['variant'] = $db->selectOne("insert into public.product_variants (product_id, label, price) values (?, 'Regular', 100) returning id", [$this->fx['product']])->id;
        foreach (['a', 'b'] as $k) {
            $db->insert('insert into public.branch_inventory (branch_id, product_id, variant_id, quantity) values (?, ?, ?, 50)',
                [$this->fx["branch_$k"], $this->fx['product'], $this->fx['variant']]);
        }

        $this->fx['owner'] = "it-own-$s";
        $this->fx['staff_a'] = "it-sa-$s";
        $this->fx['customer'] = "it-c-$s";
        $db->insert("insert into public.staff_members (firebase_uid, full_name, email, role, branch_id, account_status, can_manage_inventory, can_review_refunds)
                     values (?, 'IT Owner', ?, 'owner', null, 'active', true, true),
                            (?, 'IT Staff A', ?, 'staff', ?, 'active', true, false)",
            [$this->fx['owner'], "owner-$s@test.local", $this->fx['staff_a'], "staff-a-$s@test.local", $this->fx['branch_a']]);
        $db->insert("insert into public.customer_profiles (firebase_uid, full_name, email, phone, rfid_card_number) values (?, ?, ?, '0917', ?)",
            [$this->fx['customer'], "IT Customer $s", "cust-$s@test.local", "CARD-$s"]);
        $db->statement("insert into public.loyalty_cart_settings (id, points_per_peso, max_discount_percent) values ('default', 1, 50) on conflict (id) do nothing");
    }

    /** Customer checkout through the app's own functions. Returns the order id. */
    private function placeOrder(string $branch, string $method = 'Cash on Counter Pickup', int $qty = 2): string
    {
        $cart = json_decode($this->asAppUser($this->fx['customer'],
            "select public.sync_customer_cart(?::uuid, jsonb_build_array(jsonb_build_object('product_id', ?::text, 'variant_id', ?::text, 'quantity', ?::int)), null, false)::text",
            [$this->fx["branch_$branch"], $this->fx['product'], $this->fx['variant'], $qty]), true)['cart_id'];

        return $this->asAppUser($this->fx['customer'], 'select public.place_order(?::uuid, false, null, ?, \'\', ?)', [$cart, $method, Str::uuid()->toString()]);
    }

    private function completeOrder(string $orderId, ?string $reference = null): void
    {
        $this->asAppUser($this->fx['owner'], 'select public.staff_confirm_payment(?, ?)::text', [$orderId, $reference]);
        foreach (['confirmed', 'preparing', 'readyForPickup', 'completed'] as $st) {
            $this->asAppUser($this->fx['owner'], 'select public.staff_update_order_status(?, ?)::text', [$orderId, $st]);
        }
    }

    private function requestRefund(string $orderId, int $qty = 2): string
    {
        return $this->asAppUser($this->fx['customer'],
            "select public.request_refund(?, 'Damaged pack', '', jsonb_build_array(jsonb_build_object('product_name', ?::text, 'variant_label', 'Regular', 'quantity', ?::int)))",
            [$orderId, "IT-{$this->sfx} Garlic Peanuts", $qty]);
    }

    private function portalAdmin(bool $linked = true): User
    {
        $u = User::factory()->create(['email' => "owner-{$this->sfx}@test.local", 'role' => 'admin', 'has_access' => true]);
        if ($linked) {
            $u->forceFill(['staff_uid' => $this->fx['owner']])->save();
        }

        return $u;
    }

    private function portalStaff(): User
    {
        $u = User::factory()->create(['email' => "staff-a-{$this->sfx}@test.local", 'role' => 'staff', 'has_access' => true]);
        $u->forceFill(['staff_uid' => $this->fx['staff_a']])->save();

        return $u;
    }

    // ---- linking -------------------------------------------------------------------------

    public function test_unlinked_admin_sees_a_clear_not_linked_page(): void
    {
        $this->actingAs($this->portalAdmin(linked: false))->get('/dashboard')
            ->assertStatus(409)->assertSee('Account not linked')->assertSee('melai:link-staff');
    }

    public function test_link_command_verifies_with_supabase(): void
    {
        $admin = $this->portalAdmin(linked: false);

        $this->artisan('melai:link-staff', ['email' => $admin->email, 'uid' => $this->fx['staff_a']])
            ->expectsOutputToContain("does not match")->assertFailed();
        $this->artisan('melai:link-staff', ['email' => $admin->email, 'uid' => 'no-such-uid'])
            ->expectsOutputToContain('not registered')->assertFailed();
        $this->artisan('melai:link-staff', ['email' => $admin->email, 'uid' => $this->fx['owner']])
            ->expectsOutputToContain('Linked')->assertSuccessful();

        $this->assertSame($this->fx['owner'], $admin->fresh()->staff_uid);
        $this->assertTrue(ActivityLog::where('action', 'account.staff_linked')->exists());
    }

    public function test_admin_cannot_link_staff_login_to_an_owner_account(): void
    {
        $admin = $this->portalAdmin();
        $staffLogin = User::factory()->create(['email' => "owner-x-{$this->sfx}@test.local", 'role' => 'staff', 'has_access' => true]);

        $this->actingAs($admin)->from('/admin/users')->post(route('admin.users.link', $staffLogin), ['staff_uid' => $this->fx['owner']])
            ->assertSessionHasErrors('staff_uid');
        $this->assertNull($staffLogin->fresh()->staff_uid);
    }

    public function test_deactivating_the_app_account_cuts_off_the_portal(): void
    {
        $admin = $this->portalAdmin();
        $this->actingAs($admin)->get('/dashboard')->assertOk();

        $this->db()->update("update public.staff_members set account_status = 'inactive' where firebase_uid = ?", [$this->fx['owner']]);
        $this->actingAs($admin)->get('/dashboard')->assertStatus(403)->assertSee('deactivated');
    }

    // ---- shared data: what the app does shows up in the portal ---------------------------

    public function test_dashboard_and_payments_show_the_apps_real_orders(): void
    {
        $order = $this->placeOrder('a');
        $admin = $this->portalAdmin();

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee($this->fx['branch_a_name'])->assertSee($this->fx['branch_b_name'])->assertDontSee('Demo data');

        $this->actingAs($admin)->get('/admin/payments?status=pending')->assertOk()->assertSee($order)->assertSee('Mark as paid');
    }

    public function test_marking_cash_paid_writes_to_the_apps_database_once(): void
    {
        $order = $this->placeOrder('a');
        $admin = $this->portalAdmin();

        $this->actingAs($admin)->post(route('admin.payments.mark-paid', $order))->assertSessionHas('status');
        $this->assertSame('success', $this->db()->selectOne('select status from public.payments where order_id = ?', [$order])->status);
        $this->assertTrue((bool) $this->db()->selectOne("select exists (select 1 from public.notifications where firebase_uid = ? and category = 'payment') as e", [$this->fx['customer']])->e);

        // Second click: refused, nothing changes.
        $this->actingAs($admin)->post(route('admin.payments.mark-paid', $order))->assertSessionHasErrors('order');
        $this->assertSame(1, (int) $this->db()->selectOne('select count(*) as n from public.payments where order_id = ?', [$order])->n);
    }

    public function test_cash_refund_is_completed_in_the_app_with_stock_and_points_handled_by_the_database(): void
    {
        $order = $this->placeOrder('a');
        $this->completeOrder($order);
        $pointsBefore = (int) $this->db()->selectOne('select points_balance from public.loyalty_accounts where firebase_uid = ?', [$this->fx['customer']])->points_balance;
        $this->assertSame(4, $pointsBefore, 'PHP 200 at 1 point per PHP 50');
        $refund = $this->requestRefund($order);
        $admin = $this->portalAdmin();

        $this->actingAs($admin)->get('/admin/refunds')->assertOk()->assertSee($order)->assertSee($refund);
        $this->actingAs($admin)->post(route('admin.refunds.approve', $order), ['amount' => 1])->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post(route('admin.refunds.approve', $order))->assertSessionHas('status');

        $row = $this->db()->selectOne('select r.status as refund, o.status as ord, p.status as pay from public.refund_requests r
            join public.orders o on o.id = r.order_id join public.payments p on p.order_id = o.id where r.id = ?', [$refund]);
        $this->assertSame(['completed', 'refunded', 'refunded'], [$row->refund, $row->ord, $row->pay]);
        $this->assertSame(0, (int) $this->db()->selectOne('select points_balance from public.loyalty_accounts where firebase_uid = ?', [$this->fx['customer']])->points_balance);
        $this->assertSame(1, (int) $this->db()->selectOne("select count(*) as n from public.staff_audit_logs where entity_id = ? and action = 'refund.approve_cash'", [$refund])->n);
        $this->assertTrue(ActivityLog::where('action', 'refund.approved_cash')->exists());

        // Approving again is refused by the portal and, underneath, by the database.
        $this->actingAs($admin)->post(route('admin.refunds.approve', $order))->assertSessionHasErrors('refund');
    }

    public function test_online_refund_is_approved_then_completed_with_a_hitpay_reference(): void
    {
        $order = $this->placeOrder('b', 'GCash E-Wallet');
        $this->completeOrder($order, 'GCASH-REF-'.$this->sfx);
        $refund = $this->requestRefund($order, 1);
        $admin = $this->portalAdmin();

        $this->actingAs($admin)->post(route('admin.refunds.approve', $order))->assertSessionHas('status');
        $this->assertSame('approved', $this->db()->selectOne('select status from public.refund_requests where id = ?', [$refund])->status);
        $this->actingAs($admin)->get('/admin/refunds')->assertSee('Mark refunded');

        $this->actingAs($admin)->post(route('admin.refunds.complete', $order), ['reference' => ''])->assertSessionHasErrors('reference');
        $this->actingAs($admin)->post(route('admin.refunds.complete', $order), ['reference' => 'HP-RF-12345'])->assertSessionHas('status');
        $this->assertSame('completed', $this->db()->selectOne('select status from public.refund_requests where id = ?', [$refund])->status);
        $this->assertSame('100.00', $this->db()->selectOne('select amount from public.refund_requests where id = ?', [$refund])->amount, 'partial refund of 1 of 2 packs');
    }

    public function test_rejecting_a_refund_tells_the_customer_why(): void
    {
        $order = $this->placeOrder('a');
        $this->completeOrder($order);
        $refund = $this->requestRefund($order);
        $admin = $this->portalAdmin();

        $this->actingAs($admin)->post(route('admin.refunds.reject', $order), ['reason' => 'Seal was broken by customer'])->assertSessionHas('status');
        $this->assertSame('rejected', $this->db()->selectOne('select status from public.refund_requests where id = ?', [$refund])->status);
        $this->assertTrue((bool) $this->db()->selectOne("select exists (select 1 from public.notifications where firebase_uid = ? and body like '%Seal was broken%') as e", [$this->fx['customer']])->e);
    }

    // ---- loyalty -------------------------------------------------------------------------

    public function test_loyalty_adjustment_is_applied_once_and_visible_to_the_customer(): void
    {
        $admin = $this->portalAdmin();
        $this->db()->insert('insert into public.loyalty_accounts (firebase_uid, points_balance, lifetime_points) values (?, 5, 5)', [$this->fx['customer']]);

        $this->actingAs($admin)->get('/loyalty?q='.urlencode("CARD-{$this->sfx}"))->assertOk()->assertSee("IT Customer {$this->sfx}");
        $key = Str::random(32);
        $url = route('admin.loyalty.adjust', $this->fx['customer']);

        $this->actingAs($admin)->post($url, ['points' => -6, 'reason' => 'Correction', 'request_key' => $key])->assertSessionHas('error');
        $this->actingAs($admin)->post($url, ['points' => 20, 'reason' => 'Birthday bonus', 'request_key' => $key])->assertSessionHas('status', fn ($v) => str_contains($v, 'saved for'));
        $this->actingAs($admin)->post($url, ['points' => 20, 'reason' => 'Birthday bonus', 'request_key' => $key])->assertSessionHas('status', 'That adjustment was already saved.');

        $this->assertSame(25, (int) $this->db()->selectOne('select points_balance from public.loyalty_accounts where firebase_uid = ?', [$this->fx['customer']])->points_balance);
        $this->assertSame('1', (string) $this->asAppUser($this->fx['customer'], "select count(*)::text from public.loyalty_transactions where source = 'admin_adjust'"));
        $this->actingAs($admin)->get(route('loyalty.show', $this->fx['customer']))->assertOk()->assertSee('Birthday bonus')->assertSee('Manual adjustment');
    }

    public function test_loyalty_settings_change_the_points_of_the_next_app_order(): void
    {
        $admin = $this->portalAdmin();
        $this->actingAs($admin)->put(route('admin.loyalty.settings.update'), ['earn_pesos_per_point' => 0, 'points_per_peso' => 1, 'max_discount_percent' => 50])
            ->assertSessionHasErrors('earn_pesos_per_point');
        $this->actingAs($admin)->put(route('admin.loyalty.settings.update'), ['earn_pesos_per_point' => 20, 'points_per_peso' => 1, 'max_discount_percent' => 50])
            ->assertSessionHas('status');

        $order = $this->placeOrder('a'); // PHP 200
        $this->assertSame(10, (int) $this->db()->selectOne('select points_earned from public.orders where id = ?', [$order])->points_earned);

        $this->db()->update("update public.loyalty_cart_settings set earn_pesos_per_point = 50 where id = 'default'"); // leave the shared test DB as found
    }

    // ---- branch scoping, catalog, inventory ------------------------------------------------

    public function test_branch_staff_only_see_their_branch(): void
    {
        $orderA = $this->placeOrder('a');
        $orderB = $this->placeOrder('b');
        $staff = $this->portalStaff();

        $this->actingAs($staff)->get('/dashboard')->assertOk()->assertSee($this->fx['branch_a_name'])->assertDontSee($this->fx['branch_b_name']);
        $this->actingAs($staff)->get('/s/inventory?branch='.$this->fx['branch_b'])->assertOk()
            ->assertSee($this->fx['branch_a_name'])->assertDontSee($this->fx['branch_b_name']);
        $this->actingAs($staff)->get('/s/sales')->assertOk()->assertSee('your branch only');
        // Admin-only pages stay admin-only for portal staff.
        $this->actingAs($staff)->get('/admin/payments')->assertForbidden();

        $owner = $this->portalAdmin();
        $this->actingAs($owner)->get('/admin/payments')->assertSee($orderA)->assertSee($orderB);
    }

    public function test_hiding_a_product_hides_it_from_app_customers(): void
    {
        $admin = $this->portalAdmin();
        $this->actingAs($admin)->get('/s/products')->assertOk()->assertSee("IT-{$this->sfx} Garlic Peanuts");

        $this->actingAs($admin)->post(route('ops.products.toggle', $this->fx['product']), ['active' => 0])->assertSessionHas('status');
        $visibleToCustomer = $this->asAppUser($this->fx['customer'], 'select count(*) from public.products where id = ?', [$this->fx['product']]);
        $this->assertSame(0, (int) $visibleToCustomer);

        $this->actingAs($admin)->post(route('ops.products.toggle', $this->fx['product']), ['active' => 1])->assertSessionHas('status');
        $this->assertSame(1, (int) $this->asAppUser($this->fx['customer'], 'select count(*) from public.products where id = ?', [$this->fx['product']]));
    }

    public function test_inventory_reflects_app_sales_and_audit_log_shows_app_actions(): void
    {
        $this->placeOrder('a', 'Cash on Counter Pickup', 3); // checkout deducts stock: 50 -> 47
        $admin = $this->portalAdmin();

        $this->actingAs($admin)->get('/s/inventory?branch='.$this->fx['branch_a'])->assertOk()->assertSee('<strong>47</strong>', false);

        $this->actingAs($admin)->post(route('ops.products.toggle', $this->fx['product']), ['active' => 0]);
        $this->actingAs($admin)->get('/admin/activity-logs')->assertOk()->assertSee('product_active_changed')->assertSee('IT Owner');
        $this->actingAs($admin)->post(route('ops.products.toggle', $this->fx['product']), ['active' => 1]);
    }

    public function test_unreachable_database_shows_a_friendly_error(): void
    {
        config(['database.connections.supabase.url' => 'postgresql://melai_admin_portal:x@127.0.0.1:1/nowhere']);
        DB::purge('supabase');

        $this->actingAs($this->portalAdmin())->get('/s/products')->assertStatus(503)->assertSee('could not be reached');
    }
}
