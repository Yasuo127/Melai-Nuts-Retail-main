<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustLoyaltyPointsRequest;
use App\Http\Requests\UpdateLoyaltySettingsRequest;
use App\Services\LoyaltyService;
use App\Services\Platform;
use App\Services\Supabase\SupabaseLoyalty;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Loyalty: any logged-in user with access can view; only admin can edit settings or adjust points. */
class LoyaltyController extends Controller
{
    public function __construct(private LoyaltyService $loyalty, private Platform $platform) {}

    private function live(): ?SupabaseLoyalty
    {
        return $this->platform->isSupabase() ? app(SupabaseLoyalty::class) : null;
    }

    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        if ($live = $this->live()) {
            return view('admin.loyalty.index', ['members' => $live->members($q), 'settings' => $live->settings(), 'q' => $q, 'liveData' => true]);
        }

        return view('admin.loyalty.index', [
            'members' => $this->loyalty->members($request->query('q')),
            'settings' => $this->loyalty->settings(),
            'q' => $q,
            'liveData' => false,
        ]);
    }

    public function show(string $member)
    {
        $m = ($live = $this->live()) ? $live->find($member) : $this->loyalty->find($member);
        abort_if(! $m, 404);

        // A fresh key per page view: submitting the same form twice applies the adjustment once.
        return view('admin.loyalty.show', ['member' => $m, 'liveData' => (bool) $live, 'requestKey' => Str::random(32)]);
    }

    public function updateSettings(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($live = $this->live()) {
            $data = $request->validate([
                'earn_pesos_per_point' => ['required', 'numeric', 'min:1', 'max:100000'],
                'points_per_peso' => ['required', 'numeric', 'gt:0', 'max:10000'],
                'max_discount_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            ], [
                'earn_pesos_per_point.min' => 'Earn rate must be at least PHP 1 per point.',
                'points_per_peso.gt' => 'Redeem rate must be more than 0 points per peso.',
                'max_discount_percent.max' => 'Maximum discount cannot be more than 100%.',
            ]);
            $live->updateSettings($data, $request->user());

            return back()->with('status', 'Loyalty settings updated. New orders in the app use them right away.');
        }

        $this->loyalty->updateSettings(app(UpdateLoyaltySettingsRequest::class)->validated());

        return back()->with('status', 'Loyalty settings updated.');
    }

    public function adjust(AdjustLoyaltyPointsRequest $request, string $member)
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($live = $this->live()) {
            $key = $request->validate(['request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/']])['request_key'];
            $m = $live->find($member);
            abort_if(! $m, 404);
            $applied = $live->adjust($m, $request->integer('points'), (string) $request->string('reason'), $key, $request->user());

            return back()->with('status', $applied ? 'Points adjustment saved for '.$m['name'].'.' : 'That adjustment was already saved.');
        }

        $m = $this->loyalty->find($member);
        abort_if(! $m, 404);

        $this->loyalty->adjust($m, $request->integer('points'), $request->string('reason'), $request->user());

        return back()->with('status', 'Points adjustment saved for '.$m['name'].'.');
    }
}
