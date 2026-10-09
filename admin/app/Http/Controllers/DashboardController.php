<?php

namespace App\Http\Controllers;

use App\Contracts\DataSource;
use App\Services\DashboardService;
use App\Services\Platform;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        $period = in_array($request->query('period'), ['today', 'week', 'month'], true) ? $request->query('period') : 'today';

        return view('dashboard', $dashboard->build($period));
    }

    /** Polled by the map. Real GPS plugs in by swapping the DataSource, not this endpoint. */
    public function drivers(DataSource $data)
    {
        return response()->json($data->drivers());
    }

    /**
     * Sidebar pages. With DATA_SOURCE=supabase, Sales/Products/Inventory/Deliveries show live data
     * from the app; the rest (and every page in mock mode) are placeholders.
     */
    public function section(string $slug, Platform $platform)
    {
        abort_unless(array_key_exists($slug, config('melai.sections')), 404);
        abort_if($slug === 'users' && ! auth()->user()->isAdmin(), 403);

        if ($platform->isSupabase() && in_array($slug, ['sales', 'products', 'inventory', 'deliveries'], true)) {
            return app()->call([app(OperationsController::class), $slug]);
        }

        return view('section', ['title' => config('melai.sections')[$slug], 'slug' => $slug, 'liveData' => $platform->isSupabase()]);
    }
}
