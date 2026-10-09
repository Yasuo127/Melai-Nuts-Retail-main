<?php

namespace App\Http\Controllers;

use App\Contracts\DataSource;
use App\Services\ActivityLogger;
use App\Services\Platform;
use App\Services\Supabase\StaffIdentity;
use App\Services\Supabase\SupabaseRepository;
use Illuminate\Http\Request;

/**
 * Sales, Products, Inventory and Deliveries pages, live from the Melai Nuts app's database
 * (DATA_SOURCE=supabase). Everything is scoped by Supabase to the linked account: owners see
 * all branches, staff only their own.
 */
class OperationsController extends Controller
{
    public function __construct(private SupabaseRepository $repo, private StaffIdentity $identity, private Platform $platform) {}

    public function sales(Request $request, DataSource $data)
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;

        if ($this->identity->isOwner()) {
            $summary = $this->repo->salesSummary($days);
        } else {
            // Same rule as owner_sales_summary (completed or refund-requested orders, by order date),
            // limited by the database to the staff member's own branch.
            $from = now()->startOfDay()->subDays($days - 1);
            $orders = collect($data->orders($from, now()))->whereIn('status', ['completed', 'refundRequested']);
            $weekFrom = now()->startOfDay()->subDays(6);
            $summary = [
                'days' => $days,
                'branches' => collect($this->platform->branches())->map(fn ($b) => [
                    'name' => $b['name'],
                    'today_revenue' => $orders->where('branch', $b['id'])->filter(fn ($o) => $o['createdAt']->isToday())->sum('total'),
                    'week_revenue' => $orders->where('branch', $b['id'])->filter(fn ($o) => $o['createdAt']->gte($weekFrom))->sum('total'),
                    'window_revenue' => $orders->where('branch', $b['id'])->sum('total'),
                    'orders_today' => $orders->where('branch', $b['id'])->filter(fn ($o) => $o['createdAt']->isToday())->count(),
                    'orders_window' => $orders->where('branch', $b['id'])->count(),
                ])->all(),
                'products' => [],
                'daily' => collect(range($days - 1, 0))->map(fn ($i) => [
                    'date' => now()->subDays($i)->toDateString(),
                    'revenue' => $orders->filter(fn ($o) => $o['createdAt']->isSameDay(now()->subDays($i)))->sum('total'),
                ])->all(),
            ];
        }

        return view('ops.sales', ['summary' => $summary, 'days' => $days, 'isOwner' => $this->identity->isOwner()]);
    }

    public function products()
    {
        return view('ops.products', ['products' => $this->repo->products()]);
    }

    public function toggleProduct(Request $request, string $product)
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $product), 404);
        $active = $request->boolean('active');
        $this->repo->setProductActive($product, $active);
        ActivityLogger::log('product.active_changed', ($active ? 'Showed' : 'Hid')." product {$product} in the app", null, [
            'product_id' => $product, 'is_active' => $active, 'source' => 'supabase',
        ]);

        return back()->with('status', $active ? 'Product is now available in the app.' : 'Product is now hidden in the app.');
    }

    public function inventory(Request $request)
    {
        $branches = $this->platform->branches();
        $branchId = collect($branches)->pluck('id')->contains($request->query('branch')) ? $request->query('branch') : ($branches[0]['id'] ?? null);
        abort_if(! $branchId, 404, 'No branch is available to this account.');

        return view('ops.inventory', [
            'branches' => $branches, 'branchId' => $branchId,
            'inventory' => $this->repo->inventory($branchId),
            'movements' => $this->repo->stockMovements($branchId, 50),
        ]);
    }

    public function deliveries(DataSource $data)
    {
        return view('ops.deliveries', ['deliveries' => $data->deliveries()]);
    }
}
