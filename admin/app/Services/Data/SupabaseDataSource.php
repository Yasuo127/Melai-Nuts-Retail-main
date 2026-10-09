<?php

namespace App\Services\Data;

use App\Contracts\DataSource;
use App\Services\Supabase\SupabaseRepository;
use Carbon\Carbon;

/**
 * Real data from the mobile app's Supabase database, mapped into the shapes the dashboard,
 * Payments, Refunds and Loyalty pages already use. Read-only: admin actions go through
 * SupabaseRepository's action methods (Supabase functions), never through local tables.
 */
class SupabaseDataSource implements DataSource
{
    /** payments.status -> admin payment status */
    private const PAYMENT = ['success' => 'paid', 'refunded' => 'refunded', 'pending' => 'pending', 'processing' => 'pending', 'failed' => 'failed'];

    /** refund_requests.status -> admin refund status */
    private const REFUND = ['pending' => 'requested', 'approved' => 'approved_processing', 'processing' => 'approved_processing',
        'completed' => 'refunded', 'rejected' => 'rejected'];

    /** deliveries.status -> dashboard label */
    private const DELIVERY = ['pending' => 'Preparing', 'optimized' => 'Preparing', 'dispatched' => 'On the way', 'inTransit' => 'On the way',
        'completed' => 'Delivered', 'cancelled' => 'Cancelled'];

    public function __construct(private SupabaseRepository $repo) {}

    private function date(?string $v): ?Carbon
    {
        return $v ? Carbon::parse($v)->setTimezone(config('app.timezone')) : null;
    }

    public function orders(Carbon $from, Carbon $to): array
    {
        return array_map(fn (array $o) => $this->mapOrder($o), $this->repo->orders($from, $to));
    }

    public function mapOrder(array $o): array
    {
        $pay = $o['payment'] ?? null;
        $refund = $o['refund'] ?? null;
        $payStatus = self::PAYMENT[$pay['status'] ?? 'pending'] ?? 'pending';

        return [
            'id' => $o['id'],
            'branch' => $o['branch_id'],
            'branchName' => $o['branch_name'],
            'items' => array_map(fn ($i) => $i['quantity'].'x '.$i['product_name']
                .(in_array($i['variant_label'] ?? '', ['', 'Regular'], true) ? '' : ' · '.$i['variant_label']), $o['items'] ?? []),
            'total' => (float) $o['total'],
            'status' => $o['status'],
            'createdAt' => $this->date($o['created_at']),
            'paymentStatus' => $payStatus,
            'paymentMethod' => $pay['method'] ?? 'unknown',
            'paymentLabel' => $o['payment_label'] ?? null,
            'paymentId' => $pay['id'] ?? null,
            'paymentReference' => $pay['reference_number'] ?? null,
            // The app has no separate "paid at" column; the payment row's last update is when it was confirmed.
            'paidAt' => in_array($payStatus, ['paid', 'refunded'], true) ? $this->date($pay['updated_at'] ?? null) : null,
            'refundStatus' => $refund ? (self::REFUND[$refund['status']] ?? null) : null,
            'refundRawStatus' => $refund['status'] ?? null,
            'refundId' => $refund['id'] ?? null,
            'refundAmount' => $refund ? (float) $refund['amount'] : null,
            'customer' => $o['customer_name'] ?? 'Customer',
            'customerUid' => $o['customer_uid'] ?? null,
            'refundReason' => $refund ? trim($refund['reason'].(($refund['notes'] ?? '') !== '' ? ' — '.$refund['notes'] : '')) : null,
            'refundPhoto' => null, // the app does not upload refund photos
            'isPos' => (bool) ($o['is_pos'] ?? false),
            'isDelivery' => (bool) ($o['is_delivery'] ?? false),
        ];
    }

    /** No par level exists in the app; 'par' is null and the dashboard uses restock thresholds instead. */
    public function stock(): array
    {
        $out = [];
        foreach ($this->repo->stock() as $branch) {
            $out[$branch['branch_id']] = array_map(fn ($i) => [
                'product' => $i['product_name'].(in_array($i['variant_label'] ?? '', ['', 'Regular'], true) ? '' : ' · '.$i['variant_label']),
                'qty' => (int) $i['quantity'], 'par' => null, 'reorder' => (int) $i['restock_threshold'],
            ], $branch['items'] ?? []);
        }

        return $out;
    }

    /** The app does not record rider GPS positions, so there is nothing to put on the map. */
    public function drivers(): array
    {
        return [];
    }

    public function deliveries(): array
    {
        return array_map(function (array $d) {
            $stops = $d['stops'] ?? [];
            $next = collect($stops)->first(fn ($s) => in_array($s['status'], ['pending', 'enRoute', 'delayed'], true));
            $items = collect($stops)->flatMap(fn ($s) => $s['items'] ?? [])->all();

            return [
                'id' => $d['id'], 'driverId' => $d['id'], 'driver' => $d['rider_name'], 'vehicle' => $d['vehicle'] ?? null,
                'branch' => $d['branch_id'], 'branchName' => $d['branch'],
                'items' => count($stops).' stop'.(count($stops) === 1 ? '' : 's').($items ? ' · '.implode(', ', array_slice($items, 0, 3)).(count($items) > 3 ? ', …' : '') : ''),
                'status' => self::DELIVERY[$d['status']] ?? ucfirst($d['status']),
                'rawStatus' => $d['status'],
                'eta' => null, 'etaLabel' => $next['eta'] ?? null,
                'createdAt' => $this->date($d['created_at'] ?? null),
                'stops' => $stops,
            ];
        }, $this->repo->deliveries());
    }

    public function loyalty(): array
    {
        $s = $this->repo->loyaltySummary();

        return ['members' => (int) ($s['members'] ?? 0), 'issued_month' => (int) ($s['issued_month'] ?? 0),
            'redeemed_month' => (int) ($s['redeemed_month'] ?? 0)];
    }

    public function loyaltyMembers(): array
    {
        return array_map(fn ($m) => [
            'id' => $m['id'], 'name' => $m['name'], 'email' => $m['email'], 'cardNumber' => $m['card_number'] ?? '—',
            'tier' => null, 'pointsHistory' => [],
        ], $this->repo->loyaltyMembers(null, 1000));
    }

    public function pendingRefunds(): int
    {
        $from = now()->subDays(config('melai.supabase.order_window_days', 90));

        return collect($this->orders($from, now()))->where('refundStatus', 'requested')->count();
    }
}
