<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderOverride;
use App\Services\ActivityLogger;
use App\Services\LoyaltyService;
use App\Services\OrderQueryService;
use App\Services\Platform;
use App\Services\Supabase\SupabaseRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(private OrderQueryService $orders, private LoyaltyService $loyalty, private Platform $platform) {}

    public function index(Request $request)
    {
        $filters = $request->only(['status', 'method', 'branch', 'from', 'to']);
        $orders = $this->orders->filter($filters);

        return view('admin.payments.index', [
            'orders' => collect($orders)->sortByDesc('createdAt')->values()->all(),
            'totals' => $this->orders->totalsByMethod($orders),
            'failedOrPending' => collect($orders)->whereIn('effectivePaymentStatus', ['failed', 'pending'])->values()->all(),
            'branches' => $this->platform->branches(),
            'methods' => $this->platform->paymentMethods(),
            'cashMethod' => $this->platform->cashMethod(),
            'liveData' => $this->platform->isSupabase(),
            'filters' => $filters,
        ]);
    }

    /** Only admin, only for cash (COD / counter), and only once the cash has actually been collected. */
    public function markPaid(Request $request, string $orderId)
    {
        $order = $this->orders->find($orderId);
        abort_if(! $order, 404);

        if ($order['paymentMethod'] !== $this->platform->cashMethod()) {
            throw ValidationException::withMessages(['order' => 'Only cash-on-delivery orders can be marked paid this way.']);
        }
        if ($order['effectivePaymentStatus'] === 'paid') {
            throw ValidationException::withMessages(['order' => 'This order is already marked as paid.']);
        }

        if ($this->platform->isSupabase()) {
            // Same function the staff app uses: locks the order + payment, checks the branch,
            // refuses if already paid/cancelled, notifies the customer. Points are awarded by the
            // database when the order is completed, so nothing loyalty-related happens here.
            app(SupabaseRepository::class)->confirmPayment($orderId);
            $this->orders->refresh();
            ActivityLogger::log('payment.cash_confirmed', "Confirmed cash payment for order {$orderId}", null, [
                'order_id' => $orderId, 'amount' => $order['total'], 'by' => $request->user()->id, 'source' => 'supabase',
            ]);

            return back()->with('status', "Order {$orderId} marked as paid.");
        }

        OrderOverride::updateOrCreate(['order_id' => $orderId], [
            'cod_marked_paid' => true, 'cod_marked_paid_by' => $request->user()->id, 'cod_marked_paid_at' => now(),
        ]);
        $this->orders->refresh();

        ActivityLogger::log('payment.cod_marked_paid', "Marked COD order {$orderId} as paid", null, [
            'order_id' => $orderId, 'amount' => $order['total'], 'by' => $request->user()->id,
        ]);

        // Earning only happens once an order is paid/delivered. For COD that's now; online orders are
        // awarded by the mobile app itself. Idempotent by order id, so marking paid twice never double-awards.
        if ($member = $this->loyalty->findMemberByName($order['customer'] ?? '')) {
            $this->loyalty->awardForOrder($member['id'], $member['name'], $orderId, (int) $order['total']);
        }

        return back()->with('status', "Order {$orderId} marked as paid.");
    }
}
