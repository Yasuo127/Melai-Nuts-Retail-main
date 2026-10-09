<?php

namespace App\Http\Controllers;

use App\Models\OrderOverride;
use App\Services\ActivityLogger;
use App\Services\LoyaltyService;
use App\Services\OrderQueryService;
use App\Services\PayMongo\PayMongoService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(private PayMongoService $payMongo, private OrderQueryService $orders, private LoyaltyService $loyalty) {}

    /**
     * PayMongo calls this once a refund actually completes. Never trust a client to report
     * payment/refund status directly — only this verified webhook may do it.
     */
    public function paymongo(Request $request)
    {
        // Mock mode only. The mobile app's online payments go through HitPay, whose webhook is the
        // Supabase Edge Function supabase/functions/hitpay-webhook — not this endpoint.
        abort_if(config('melai.data_source') === 'supabase', 404);
        abort_unless($this->payMongo->verifyWebhookSignature($request), 400, 'Invalid signature.');

        $type = $request->input('data.attributes.type');
        $orderId = $request->input('data.attributes.data.attributes.metadata.order_id');

        if ($type === 'refund.updated' && $orderId) {
            $status = $request->input('data.attributes.data.attributes.status'); // succeeded | failed | pending
            $override = OrderOverride::where('order_id', $orderId)->first();

            if ($override && $status === 'succeeded') {
                $override->update(['refund_status' => 'refunded']);
                ActivityLogger::log('refund.confirmed', "PayMongo confirmed refund for order {$orderId}", null, ['order_id' => $orderId]);

                $order = $this->orders->find($orderId);
                $member = $order ? $this->loyalty->findMemberByName($order['customer'] ?? '') : null;
                if ($order && $member) {
                    $points = intdiv((int) ($override->refund_amount ?? $order['total']), max(1, $this->loyalty->settings()->earn_rate_pesos));
                    $this->loyalty->deductForRefund($member['id'], $member['name'], $orderId, $points);
                }
            } elseif ($override && $status === 'failed') {
                $override->update(['refund_status' => 'failed']);
                ActivityLogger::log('refund.failed', "PayMongo reported a failed refund for order {$orderId}", null, ['order_id' => $orderId]);
            }
        }

        return response()->json(['received' => true]);
    }
}
