@extends('layouts.panel')
@section('title', 'Dashboard · Melai Nuts Admin')
@section('page-title', 'Dashboard')
@section('panel')
@php $label = ['low' => 'Low', 'average' => 'Average', 'high' => 'High', 'normal' => 'Normal', 'plenty' => 'Plenty']; $peso = fn ($n) => '₱'.number_format($n); @endphp

<section class="stat-row">
    <div class="stat"><span>Pending refunds</span><strong>{{ $pendingRefunds }}</strong></div>
    <div class="stat"><span>Active loyalty members</span><strong>{{ number_format($loyalty['members']) }}</strong></div>
    <div class="stat"><span>Points issued this month</span><strong>{{ number_format($loyalty['issued_month']) }}</strong></div>
    <div class="stat"><span>Points redeemed this month</span><strong>{{ number_format($loyalty['redeemed_month']) }}</strong></div>
</section>

<section class="block">
    <div class="block-head">
        <h2>Sales per branch</h2>
        <div class="tabs">@foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $k => $v)
            <a href="?period={{ $k }}" class="{{ $period === $k ? 'active' : '' }}">{{ $v }}</a>@endforeach</div>
    </div>
    <div class="grid-4">
        @foreach ($sales as $s)
            <div class="card-b">
                <div class="card-top"><strong>{{ $s['name'] }}</strong><span class="status {{ $s['status'] }}">{{ $label[$s['status']] }}</span></div>
                <div class="big">{{ $peso($s['net']) }}</div>
                <div class="muted">{{ $s['pct'] }}% of {{ $peso($s['target']) }} target</div>
                <div class="bar"><span class="{{ $s['status'] }}" style="width: {{ min(100, $s['pct'] / $maxPct * 100) }}%"></span></div>
            </div>
        @endforeach
    </div>
    <p class="muted">Paid orders only, minus refunded amounts{{ $liveData ? ' (live from the Melai Nuts app)' : ' (demo data)' }}. Targets and thresholds: config/melai.php.</p>
</section>

<section class="block">
    <h2>Stock levels per branch</h2>
    @if ($liveData) <p class="muted">Share of products above their restock threshold in each branch.</p> @endif
    <div class="grid-4">
        @foreach ($stock as $s)
            <div class="card-b {{ $s['lowest'] ? 'lowest' : '' }}">
                <div class="card-top"><strong>{{ $s['name'] }}</strong><span class="status {{ $s['status'] }}">{{ $s['status'] === 'low' ? 'Low stock' : $label[$s['status']] }}</span></div>
                <div class="big">{{ $s['pct'] }}%</div>
                @if ($s['lowest']) <div class="flag">Lowest stock</div> @endif
                @forelse ($s['lowItems'] as $i) <div class="muted">Running low: {{ $i['product'] }} ({{ $i['qty'] }} left)</div>
                @empty <div class="muted">No products running low.</div> @endforelse
            </div>
        @endforeach
    </div>
</section>

<section class="block">
    <h2>Driver and delivery tracking</h2>
    @if ($liveData)
        <p class="muted">Deliveries come from the Melai Nuts app. The app does not record rider GPS positions yet, so the map shows branch locations only.</p>
    @endif
    <div class="track">
        @if (count($mapBranches))
            <div id="map" class="map"
                 x-data="driverMap(@js($mapBranches), @js($drivers), '{{ route('dashboard.drivers') }}')"></div>
        @else
            <div class="card muted">Add branch map positions in config/melai.php to show the map.</div>
        @endif
        <div class="deliveries">
            <table class="table">
                <thead><tr><th>Driver</th><th>{{ $liveData ? 'Branch' : 'To' }}</th><th>Items</th><th>Status</th><th>ETA</th></tr></thead>
                <tbody>
                @forelse ($deliveries as $d)
                    <tr><td>{{ $d['driver'] }}</td><td>{{ $d['branchName'] ?? (collect($branches)->firstWhere('id', $d['branch'])['name'] ?? '—') }}</td><td>{{ $d['items'] }}</td>
                        <td><span class="status {{ ['Preparing' => 'average', 'On the way' => 'info', 'Delivered' => 'high', 'Cancelled' => 'low'][$d['status']] ?? 'info' }}">{{ $d['status'] }}</span></td>
                        <td>{{ in_array($d['status'], ['Delivered', 'Cancelled'], true) ? '—' : ($d['eta'] ? $d['eta']->format('g:i A') : ($d['etaLabel'] ?? '—')) }}</td></tr>
                @empty
                    <tr><td colspan="5">No deliveries yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('alpine:init', () => {
    // Map of branches + drivers. Refreshes every 10s from the drivers endpoint (real GPS data plugs in via the DataSource).
    Alpine.data('driverMap', (branches, drivers, url) => ({
        markers: {},
        init() {
            const map = L.map(this.$el).setView([branches[0].lat, branches[0].lng], 11);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors' }).addTo(map);
            branches.forEach(b => L.circleMarker([b.lat, b.lng], { radius: 8, color: '#6B4220', fillColor: '#E7B96E', fillOpacity: 1 }).addTo(map).bindTooltip(b.name));
            const draw = list => list.forEach(d => {
                const tip = `${d.name} (${d.status})`;
                if (this.markers[d.driverId]) { this.markers[d.driverId].setLatLng([d.lat, d.lng]).setTooltipContent(tip); }
                else { this.markers[d.driverId] = L.marker([d.lat, d.lng]).addTo(map).bindTooltip(tip); }
            });
            draw(drivers);
            setInterval(() => fetch(url, { headers: { Accept: 'application/json' } }).then(r => r.ok ? r.json() : []).then(draw).catch(() => {}), 10000);
        },
    }));
});
</script>
@endsection
