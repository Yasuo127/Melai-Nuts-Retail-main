<?php

namespace App\Services;

use App\Contracts\DataSource;
use App\Support\SalesCalculator;

class DashboardService
{
    public function __construct(private DataSource $data, private OrderQueryService $orderQuery, private Platform $platform) {}

    public function build(string $period): array
    {
        $now = now();
        $from = match ($period) { 'week' => $now->copy()->startOfWeek(), 'month' => $now->copy()->startOfMonth(), default => $now->copy()->startOfDay() };
        $days = max(1, (int) $from->diffInDays($now->copy()->startOfDay()) + 1); // target grows with elapsed days
        $orders = $this->data->orders($from, $now);
        $branches = $this->platform->branches();

        $sales = collect($branches)->map(function ($b) use ($orders, $from, $now, $days) {
            $net = SalesCalculator::net($orders, $b['id'], $from, $now);
            $target = $b['daily_target'] * $days;
            return ['name' => $b['name'], 'net' => $net, 'target' => $target, 'pct' => $target ? round($net / $target * 100) : 0,
                'status' => SalesCalculator::status($net, $target)];
        })->all();

        return [
            'period' => $period, 'sales' => $sales, 'maxPct' => max(100, collect($sales)->max('pct') ?? 0),
            'stock' => $this->stock($branches), 'deliveries' => $this->data->deliveries(),
            'drivers' => $this->data->drivers(), 'branches' => $branches,
            'mapBranches' => array_values(array_filter($branches, fn ($b) => $b['lat'] !== null && $b['lng'] !== null)),
            'pendingRefunds' => $this->orderQuery->pendingRefundsCount(), 'loyalty' => $this->data->loyalty(),
            'liveData' => $this->platform->isSupabase(),
        ];
    }

    private function stock(array $branches): array
    {
        $cfg = config('melai.stock');
        $all = $this->data->stock();
        $out = collect($branches)->map(function ($b) use ($cfg, $all) {
            $items = collect($all[$b['id']] ?? []);
            // Mock data has a par level per product. The app has none, only a restock threshold,
            // so there the ratio is the share of products above their threshold.
            $ratio = $items->contains(fn ($i) => $i['par'] === null)
                ? ($items->count() ? $items->filter(fn ($i) => $i['qty'] > $i['reorder'])->count() / $items->count() : 0)
                : ($items->sum('par') ? $items->sum('qty') / $items->sum('par') : 0);
            return ['name' => $b['name'], 'pct' => round($ratio * 100), 'ratio' => $ratio,
                'status' => $ratio < $cfg['low_below'] ? 'low' : ($ratio >= $cfg['plenty_from'] ? 'plenty' : 'normal'),
                'lowItems' => $items->filter(fn ($i) => $i['qty'] <= $i['reorder'])->values()->all(), 'lowest' => false];
        })->all();

        $min = collect($out)->min('ratio');
        return array_map(fn ($s) => $s['ratio'] === $min && count($out) > 1 ? [...$s, 'lowest' => true] : $s, $out);
    }
}
