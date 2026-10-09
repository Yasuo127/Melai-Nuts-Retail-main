<?php

namespace Tests\Unit;

use App\Support\SalesCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class SalesCalculatorTest extends TestCase
{
    private function order(string $branch, string $pay, float $total, ?string $refund = null, ?float $amt = null): array
    {
        return ['branch' => $branch, 'paymentStatus' => $pay, 'total' => $total, 'paidAt' => Carbon::parse('2026-09-28 10:00'),
            'refundStatus' => $refund, 'refundAmount' => $amt];
    }

    public function test_counts_only_paid_orders_and_subtracts_refunds(): void
    {
        $orders = [
            $this->order('a', 'paid', 500), $this->order('a', 'pending', 900), $this->order('a', 'failed', 700),
            $this->order('a', 'refunded', 300, 'refunded', 300), $this->order('a', 'paid', 400, 'refunded', 150),
            $this->order('b', 'paid', 999),
        ];
        $net = SalesCalculator::net($orders, 'a', Carbon::parse('2026-09-28 00:00'), Carbon::parse('2026-09-28 23:59'));
        $this->assertSame(750.0, $net); // 500 + (300-300) + (400-150)
    }

    public function test_status_thresholds(): void
    {
        $this->assertSame('low', SalesCalculator::status(600, 1000, 0.7, 1.0));
        $this->assertSame('average', SalesCalculator::status(700, 1000, 0.7, 1.0));
        $this->assertSame('average', SalesCalculator::status(1000, 1000, 0.7, 1.0));
        $this->assertSame('high', SalesCalculator::status(1001, 1000, 0.7, 1.0));
    }
}
