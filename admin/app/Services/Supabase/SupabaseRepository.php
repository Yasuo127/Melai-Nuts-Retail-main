<?php

namespace App\Services\Supabase;

use Carbon\CarbonInterface;

/**
 * One method per Supabase function the admin portal uses, always acting as the signed-in
 * user's linked staff account. Read functions are defined in
 * supabase/migrations/20261009000000_admin_portal.sql; actions reuse the app's own staff
 * functions (staff_confirm_payment, staff_set_product_active, ...).
 */
class SupabaseRepository
{
    private array $memo = [];

    public function __construct(private SupabaseGateway $gateway, private StaffIdentity $identity) {}

    private function call(string $sql, array $bindings = []): mixed
    {
        return $this->gateway->call($this->identity->uid(), $sql, $bindings);
    }

    private function memo(string $key, \Closure $load): mixed
    {
        return array_key_exists($key, $this->memo) ? $this->memo[$key] : ($this->memo[$key] = $load());
    }

    private function forget(): void
    {
        $this->memo = [];
    }

    // ---- reads ------------------------------------------------------------------------

    public function orders(CarbonInterface $from, CarbonInterface $to, ?string $branchId = null): array
    {
        // Microsecond precision: a second-precision "to" would hide orders placed in the current second.
        [$f, $t] = [$from->format('Y-m-d\TH:i:s.uP'), $to->format('Y-m-d\TH:i:s.uP')];

        return $this->memo("orders:$f:$t:$branchId", fn () => $this->call(
            'select public.admin_portal_orders(?::timestamptz, ?::timestamptz, ?::uuid)::text as r',
            [$f, $t, $branchId],
        ) ?? []);
    }

    public function stock(): array
    {
        return $this->memo('stock', fn () => $this->call('select public.admin_portal_stock()::text as r') ?? []);
    }

    public function products(): array
    {
        return $this->call('select public.admin_portal_products()::text as r') ?? [];
    }

    public function stockMovements(?string $branchId, int $limit = 100): array
    {
        return $this->call('select public.admin_portal_stock_movements(?::uuid, ?::int)::text as r', [$branchId, $limit]) ?? [];
    }

    public function inventory(string $branchId): array
    {
        return $this->call('select public.staff_get_inventory(?::uuid)::text as r', [$branchId]) ?? [];
    }

    public function deliveries(?string $branchId = null): array
    {
        return $this->memo('deliveries:'.$branchId, fn () => $this->call('select public.staff_get_deliveries(?::uuid)::text as r', [$branchId]) ?? []);
    }

    /** Owner only (raises otherwise). */
    public function salesSummary(int $days): array
    {
        return $this->call('select public.owner_sales_summary(?::int)::text as r', [$days]) ?? [];
    }

    public function loyaltySummary(): array
    {
        return $this->memo('loyalty-summary', fn () => $this->call('select public.admin_portal_loyalty_summary()::text as r') ?? []);
    }

    public function loyaltyMembers(?string $search, int $limit = 200): array
    {
        return $this->call('select public.admin_portal_loyalty_members(?::text, ?::int)::text as r', [$search, $limit]) ?? [];
    }

    public function loyaltyMember(string $firebaseUid): ?array
    {
        return $this->call('select public.admin_portal_loyalty_member(?::text)::text as r', [$firebaseUid]);
    }

    public function loyaltySettings(): array
    {
        return $this->call('select public.admin_portal_loyalty_settings()::text as r') ?? [];
    }

    public function auditLogs(int $limit = 100, ?string $before = null): array
    {
        return $this->call('select public.admin_portal_audit_logs(?::int, ?::timestamptz)::text as r', [$limit, $before]) ?? [];
    }

    /** Owner only. */
    public function staffDirectory(): array
    {
        return $this->call('select public.admin_portal_staff_directory()::text as r') ?? [];
    }

    // ---- actions (each one transaction; the SQL functions lock rows and check permissions) ----

    /** Confirms a pending payment (cash needs no reference; other methods need one). */
    public function confirmPayment(string $orderId, ?string $reference = null): void
    {
        $this->call('select public.staff_confirm_payment(?::text, ?::text)::text as r', [$orderId, $reference]);
        $this->forget();
    }

    /** $action: approve | approve_cash | complete | reject. */
    public function reviewRefund(string $refundId, string $action, ?string $note = null): array
    {
        $result = $this->call('select public.admin_portal_review_refund(?::text, ?::text, ?::text)::text as r', [$refundId, $action, $note]);
        $this->forget();

        return $result ?? [];
    }

    public function adjustLoyalty(string $firebaseUid, int $points, string $reason, string $requestKey): array
    {
        return $this->call('select public.owner_adjust_loyalty_points(?::text, ?::int, ?::text, ?::text)::text as r',
            [$firebaseUid, $points, $reason, $requestKey]) ?? [];
    }

    public function updateLoyaltySettings(float $earnPesosPerPoint, float $pointsPerPeso, float $maxDiscountPercent): array
    {
        return $this->call('select public.owner_update_loyalty_settings(?::numeric, ?::numeric, ?::numeric)::text as r',
            [$earnPesosPerPoint, $pointsPerPeso, $maxDiscountPercent]) ?? [];
    }

    public function setProductActive(string $productId, bool $active): void
    {
        $this->call('select public.staff_set_product_active(?::uuid, ?::boolean)::text as r', [$productId, $active ? 'true' : 'false']);
    }
}
