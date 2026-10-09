<?php

namespace App\Support;

use Carbon\Carbon;

class SalesCalculator
{
    /** Net sales for a branch: paid orders only (a refunded order was paid once), minus refunded amounts. */
    public static function net(array $orders, string $branchId, Carbon $from, Carbon $to): float
    {
        $net = 0.0;
        foreach ($orders as $o) {
            if ($o['branch'] !== $branchId || ! in_array($o['paymentStatus'], ['paid', 'refunded'], true)) { continue; }
            if (! $o['paidAt'] || $o['paidAt']->lt($from) || $o['paidAt']->gt($to)) { continue; }
            $net += $o['total'];
            if ($o['refundStatus'] === 'refunded') { $net -= $o['refundAmount'] ?? $o['total']; }
        }
        return round($net, 2);
    }

    /** low | average | high, using thresholds from config('melai.sales'). */
    public static function status(float $net, float $target, ?float $lowBelow = null, ?float $highAbove = null): string
    {
        $lowBelow ??= config('melai.sales.low_below');
        $highAbove ??= config('melai.sales.high_above');
        $ratio = $target > 0 ? $net / $target : 0;
        return $ratio < $lowBelow ? 'low' : ($ratio > $highAbove ? 'high' : 'average');
    }
}
