<?php

namespace App\Services\PayMongo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around PayMongo. In 'test' mode with no real secret key configured,
 * refund() simulates a successful API acceptance instead of calling out, so the
 * admin flow can be built and tested before real PayMongo test keys exist.
 * CHANGE: once you have real test keys in .env, this starts making real test-mode calls.
 */
class PayMongoService
{
    private function hasRealKey(): bool
    {
        $key = config('melai.paymongo.secret_key');
        return filled($key) && $key !== 'sk_test_replace_me';
    }

    /**
     * Ask PayMongo to refund a payment. Returns ['ok' => bool, 'reference' => ?string, 'error' => ?string].
     * $paymentReference would be the original PayMongo payment/source id (not modeled yet in mock orders,
     * so we pass the order id as metadata so the webhook can match it back to an order).
     */
    public function refund(string $paymentReference, int $amountPesos, string $orderId): array
    {
        if (! $this->hasRealKey()) {
            // No real PayMongo test key yet: simulate PayMongo accepting the refund request.
            Log::info('[PayMongo mock] refund accepted', compact('paymentReference', 'amountPesos', 'orderId'));
            return ['ok' => true, 'reference' => 'mock_rf_'.strtoupper(uniqid()), 'error' => null];
        }

        try {
            $response = Http::withBasicAuth(config('melai.paymongo.secret_key'), '')
                ->post('https://api.paymongo.com/v1/refunds', [
                    'data' => ['attributes' => [
                        'amount' => $amountPesos * 100, // PayMongo uses centavos
                        'payment_id' => $paymentReference,
                        'reason' => 'requested_by_customer',
                        'metadata' => ['order_id' => $orderId],
                    ]],
                ]);

            if ($response->failed()) {
                return ['ok' => false, 'reference' => null, 'error' => $response->json('errors.0.detail', 'PayMongo rejected the refund request.')];
            }

            return ['ok' => true, 'reference' => $response->json('data.id'), 'error' => null];
        } catch (\Throwable $e) {
            Log::error('[PayMongo] refund call failed: '.$e->getMessage());
            return ['ok' => false, 'reference' => null, 'error' => 'Could not reach PayMongo. Check your connection and retry.'];
        }
    }

    /** HMAC signature check. CHANGE: PayMongo's exact header/format per their webhook docs once you wire real keys. */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('melai.paymongo.webhook_secret');
        if (blank($secret) || $secret === 'whsec_replace_me') {
            Log::warning('[PayMongo mock] webhook secret not configured; rejecting webhook for safety.');
            return false;
        }

        $header = $request->header('Paymongo-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }
}
