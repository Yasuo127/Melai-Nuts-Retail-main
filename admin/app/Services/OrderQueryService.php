<?php

namespace App\Services;

use App\Contracts\DataSource;
use App\Models\OrderOverride;
use Carbon\Carbon;

/**
 * Orders come from Firestore (via DataSource) and are read-only there. Refund decisions and
 * manual COD confirmations are admin actions, stored in order_overrides (Supabase-style), and
 * merged on top here so every page sees one consistent "effective" order.
 */
class OrderQueryService
{
    private ?array $all = null;

    public function __construct(private DataSource $data) {}

    /** Days of orders the Payments/Refunds pages work with (mock data covers 35 days). */
    private function windowDays(): int
    {
        return config('melai.data_source') === 'supabase' ? (int) config('melai.supabase.order_window_days', 90) : 35;
    }

    /**
     * All orders in the window, merged with any admin overrides. Overrides only exist in mock
     * mode: with Supabase every admin action is written to the shared database itself.
     */
    public function allOrders(): array
    {
        return $this->all ??= $this->loadAll();
    }

    /** Drop the per-request cache after an action changed data. */
    public function refresh(): void
    {
        $this->all = null;
    }

    private function loadAll(): array
    {
        $orders = $this->data->orders(now()->subDays($this->windowDays()), now());
        $overrides = OrderOverride::whereIn('order_id', array_column($orders, 'id'))->get()->keyBy('order_id');

        return array_map(function ($o) use ($overrides) {
            $ov = $overrides->get($o['id']);
            $o['effectivePaymentStatus'] = ($ov?->cod_marked_paid) ? 'paid' : $o['paymentStatus'];
            $o['effectiveRefundStatus'] = $ov?->refund_status ?? $o['refundStatus'];
            $o['refundAmountRequested'] = $ov?->refund_amount ?? $o['refundAmount'];
            $o['flaggedForStockReturn'] = (bool) ($ov?->flagged_for_stock_return);
            $o['codMarkedPaid'] = (bool) ($ov?->cod_marked_paid);
            $o['override'] = $ov;
            return $o;
        }, $orders);
    }

    public function find(string $orderId): ?array
    {
        return collect($this->allOrders())->firstWhere('id', $orderId);
    }

    /** Filters: status, method, branch, from, to (all optional). */
    public function filter(array $filters): array
    {
        return collect($this->allOrders())->filter(function ($o) use ($filters) {
            if (! empty($filters['status']) && $o['effectivePaymentStatus'] !== $filters['status']) { return false; }
            if (! empty($filters['method']) && $o['paymentMethod'] !== $filters['method']) { return false; }
            if (! empty($filters['branch']) && $o['branch'] !== $filters['branch']) { return false; }
            if (! empty($filters['from']) && $o['createdAt']->lt(Carbon::parse($filters['from'])->startOfDay())) { return false; }
            if (! empty($filters['to']) && $o['createdAt']->gt(Carbon::parse($filters['to'])->endOfDay())) { return false; }
            return true;
        })->values()->all();
    }

    /** Refunds queue: effective refund status, with the same filters plus refund-status. */
    public function refundQueue(array $filters): array
    {
        return collect($this->allOrders())->filter(function ($o) use ($filters) {
            if (! $o['effectiveRefundStatus']) { return false; }
            if (! empty($filters['status']) && $o['effectiveRefundStatus'] !== $filters['status']) { return false; }
            if (! empty($filters['method']) && $o['paymentMethod'] !== $filters['method']) { return false; }
            if (! empty($filters['branch']) && $o['branch'] !== $filters['branch']) { return false; }
            return true;
        })->sortByDesc('createdAt')->values()->all();
    }

    public function pendingRefundsCount(): int
    {
        return collect($this->allOrders())->where('effectiveRefundStatus', 'requested')->count();
    }

    /** Totals per payment method, for the Payments page summary row. */
    public function totalsByMethod(array $orders): array
    {
        return collect($orders)->where('effectivePaymentStatus', 'paid')
            ->groupBy('paymentMethod')->map(fn ($g) => $g->sum('total'))->all();
    }
}
