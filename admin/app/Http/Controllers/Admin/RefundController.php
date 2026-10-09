<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveRefundRequest;
use App\Http\Requests\RejectRefundRequest;
use App\Models\OrderOverride;
use App\Services\ActivityLogger;
use App\Services\LoyaltyService;
use App\Services\OrderQueryService;
use App\Services\PayMongo\PayMongoService;
use App\Services\Platform;
use App\Services\Supabase\SupabaseRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Refund queue. With DATA_SOURCE=supabase every decision is made by the database
 * (admin_portal_review_refund -> staff_review_refund): it checks the branch and the
 * refunds permission, enforces the refund state machine, returns stock/points and marks
 * the payment refunded on completion, notifies the customer and writes the audit log.
 * Mock mode keeps the original local-override + PayMongo flow.
 */
class RefundController extends Controller
{
    public function __construct(private OrderQueryService $orders, private PayMongoService $payMongo, private LoyaltyService $loyalty, private Platform $platform) {}

    public function index(Request $request)
    {
        $filters = $request->only(['status', 'method', 'branch']);

        return view('admin.refunds.index', [
            'refunds' => $this->orders->refundQueue($filters),
            'branches' => $this->platform->branches(),
            'cashMethod' => $this->platform->cashMethod(),
            'liveData' => $this->platform->isSupabase(),
            'filters' => $filters,
        ]);
    }

    public function approve(ApproveRefundRequest $request, string $orderId)
    {
        $order = $this->orders->find($orderId);
        abort_if(! $order, 404);

        $this->guardRefundable($order);

        if ($this->platform->isSupabase()) {
            return $this->approveInSupabase($request, $order);
        }

        $amount = $request->integer('amount') ?: $order['total'];
        if ($amount > $order['total']) {
            throw ValidationException::withMessages(['amount' => 'Refund amount cannot exceed the amount paid.']);
        }

        $flagStock = ! in_array($order['status'], ['completed', 'delivered'], true);

        if ($order['paymentMethod'] === 'cod') {
            // COD: admin records the refund manually. No PayMongo call, nothing to wait for.
            $this->saveOverride($orderId, [
                'refund_status' => 'refunded', 'refund_amount' => $amount, 'refund_note' => $request->input('note'),
                'refund_processed_by' => $request->user()->id, 'refund_processed_at' => now(), 'flagged_for_stock_return' => $flagStock,
            ]);
            ActivityLogger::log('refund.approved_cod', "Refunded COD order {$orderId}", null, ['order_id' => $orderId, 'amount' => $amount]);
            $this->deductLoyaltyFor($order, $orderId, $amount);

            return back()->with('status', "Refund of ₱{$amount} recorded for order {$orderId}.");
        }

        // Online payment: call PayMongo, then wait for a webhook to actually confirm the refund.
        $result = $this->payMongo->refund(paymentReference: $orderId, amountPesos: $amount, orderId: $orderId);

        $this->saveOverride($orderId, [
            'refund_status' => 'approved_processing', 'refund_amount' => $amount, 'refund_reference' => $result['reference'],
            'refund_processed_by' => $request->user()->id, 'refund_processed_at' => now(), 'flagged_for_stock_return' => $flagStock,
        ]);

        ActivityLogger::log($result['ok'] ? 'refund.approved' : 'refund.api_error',
            "Refund requested for order {$orderId}".($result['ok'] ? '' : ' (PayMongo error: '.$result['error'].')'),
            null, ['order_id' => $orderId, 'amount' => $amount]);

        if (! $result['ok']) {
            return back()->with('error', "PayMongo couldn't be reached: {$result['error']}. The request is saved — you can retry.");
        }

        return back()->with('status', "Refund submitted to PayMongo for order {$orderId}. It will show as refunded once PayMongo confirms.");
    }

    private function approveInSupabase(ApproveRefundRequest $request, array $order)
    {
        $amount = $order['refundAmount'];
        // The amount was computed by the database from the items the customer returned; it cannot be changed here.
        if ($request->filled('amount') && (float) $request->input('amount') !== (float) $amount) {
            throw ValidationException::withMessages(['amount' => 'The refund amount comes from the customer\'s request in the app (₱'.number_format($amount, 2).') and cannot be changed here.']);
        }

        $repo = app(SupabaseRepository::class);
        $isCash = $order['paymentMethod'] === 'cash';
        $repo->reviewRefund($order['refundId'], $isCash ? 'approve_cash' : 'approve', $request->input('note'));
        $this->orders->refresh();

        ActivityLogger::log($isCash ? 'refund.approved_cash' : 'refund.approved', "Approved refund {$order['refundId']} for order {$order['id']}", null, [
            'order_id' => $order['id'], 'refund_id' => $order['refundId'], 'amount' => $amount, 'source' => 'supabase',
        ]);

        return back()->with('status', $isCash
            ? 'Cash refund of ₱'.number_format($amount, 2)." recorded for order {$order['id']}. Stock and points were returned by the app."
            : 'Refund approved for order '.$order['id'].'. Send ₱'.number_format($amount, 2).' back through HitPay, then use "Mark refunded" with the HitPay refund reference.');
    }

    /** Supabase only: the money for an approved online refund has been sent back through HitPay. */
    public function complete(Request $request, string $orderId)
    {
        abort_unless($this->platform->isSupabase(), 404);
        $data = $request->validate(['reference' => ['required', 'string', 'min:4', 'max:120']],
            ['reference.required' => 'Enter the refund reference from HitPay.']);

        $order = $this->orders->find($orderId);
        abort_if(! $order, 404);
        if ($order['effectiveRefundStatus'] !== 'approved_processing') {
            throw ValidationException::withMessages(['refund' => 'Only an approved refund can be marked as refunded.']);
        }

        app(SupabaseRepository::class)->reviewRefund($order['refundId'], 'complete', $data['reference']);
        $this->orders->refresh();
        ActivityLogger::log('refund.completed', "Marked refund {$order['refundId']} for order {$orderId} as refunded", null, [
            'order_id' => $orderId, 'refund_id' => $order['refundId'], 'reference' => $data['reference'], 'source' => 'supabase',
        ]);

        return back()->with('status', "Refund for order {$orderId} marked as refunded.");
    }

    /** Resubmits to PayMongo without re-approving, for a refund stuck in approved_processing after an earlier API error. */
    public function retry(Request $request, string $orderId)
    {
        abort_if($this->platform->isSupabase(), 404); // PayMongo is not the app's gateway

        $order = $this->orders->find($orderId);
        abort_if(! $order || $order['effectiveRefundStatus'] !== 'approved_processing', 404);

        $result = $this->payMongo->refund($orderId, $order['refundAmountRequested'] ?? $order['total'], $orderId);
        $this->saveOverride($orderId, ['refund_reference' => $result['reference']]);
        ActivityLogger::log($result['ok'] ? 'refund.retry_submitted' : 'refund.retry_failed', "Retried PayMongo refund for {$orderId}", null, ['order_id' => $orderId]);

        return back()->with($result['ok'] ? 'status' : 'error', $result['ok'] ? 'Refund resubmitted to PayMongo.' : "Retry failed: {$result['error']}");
    }

    public function reject(RejectRefundRequest $request, string $orderId)
    {
        $order = $this->orders->find($orderId);
        abort_if(! $order, 404);

        if ($this->platform->isSupabase()) {
            // Approved-but-unpaid refunds may still be rejected (the database allows approved -> rejected).
            if (! in_array($order['effectiveRefundStatus'], ['requested', 'approved_processing'], true)) {
                throw ValidationException::withMessages(['refund' => 'This refund request has already been processed, or there is no active request for this order.']);
            }
            app(SupabaseRepository::class)->reviewRefund($order['refundId'], 'reject', (string) $request->string('reason'));
            $this->orders->refresh();
            ActivityLogger::log('refund.rejected', "Rejected refund {$order['refundId']} for order {$orderId}: {$request->input('reason')}", null, [
                'order_id' => $orderId, 'refund_id' => $order['refundId'], 'source' => 'supabase',
            ]);

            return back()->with('status', "Refund request for order {$orderId} rejected. The customer was told the reason.");
        }

        $this->guardRefundable($order);

        $this->saveOverride($orderId, [
            'refund_status' => 'rejected', 'refund_note' => $request->string('reason'),
            'refund_processed_by' => $request->user()->id, 'refund_processed_at' => now(),
        ]);
        ActivityLogger::log('refund.rejected', "Rejected refund for order {$orderId}: {$request->input('reason')}", null, ['order_id' => $orderId]);

        return back()->with('status', "Refund request for order {$orderId} rejected.");
    }

    /** Shared checks for approve/reject: must be a genuine, single, outstanding request on a paid order. */
    private function guardRefundable(array $order): void
    {
        if ($order['effectiveRefundStatus'] !== 'requested') {
            throw ValidationException::withMessages(['refund' => 'This refund request has already been processed, or there is no active request for this order.']);
        }
        if (! in_array($order['paymentStatus'], ['paid', 'refunded'], true)) {
            throw ValidationException::withMessages(['refund' => 'Only paid orders can be refunded.']);
        }
    }

    private function saveOverride(string $orderId, array $attrs): OrderOverride
    {
        $override = OrderOverride::updateOrCreate(['order_id' => $orderId], $attrs);
        $this->orders->refresh();

        return $override;
    }

    /** When a refund completes, claw back the points earned on that order (flags the member if it goes negative). */
    private function deductLoyaltyFor(array $order, string $orderId, int $amount): void
    {
        $member = $this->loyalty->findMemberByName($order['customer'] ?? '');
        if (! $member) {
            return;
        }
        $points = intdiv($amount, max(1, $this->loyalty->settings()->earn_rate_pesos));
        $this->loyalty->deductForRefund($member['id'], $member['name'], $orderId, $points);
    }
}
