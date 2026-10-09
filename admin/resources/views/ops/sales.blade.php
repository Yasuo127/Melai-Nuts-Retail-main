@extends('layouts.panel')
@section('title', 'Sales · Melai Nuts Admin')
@section('page-title', 'Sales')
@section('panel')
@php $peso = fn ($n) => '₱'.number_format((float) $n, 2); $maxDay = max(1, collect($summary['daily'] ?? [])->max('revenue') ?? 0); @endphp
@include('partials.flash')

<div class="block-head">
    <h2>Sales by branch</h2>
    <div class="tabs">@foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $k => $v)
        <a href="?days={{ $k }}" class="{{ $days === $k ? 'active' : '' }}">{{ $v }}</a>@endforeach</div>
</div>
<p class="muted">Completed orders (online, pickup and POS) from the Melai Nuts app, including orders with a pending refund request{{ $isOwner ? '' : ' — your branch only' }}.</p>

<div class="card" style="max-width:none;overflow-x:auto">
    <table class="table">
        <thead><tr><th>Branch</th><th>Today</th><th>Last 7 days</th><th>Last {{ $days }} days</th><th>Orders today</th><th>Orders ({{ $days }} days)</th></tr></thead>
        <tbody>
        @forelse ($summary['branches'] ?? [] as $b)
            <tr><td>{{ $b['name'] }}</td><td>{{ $peso($b['today_revenue']) }}</td><td>{{ $peso($b['week_revenue']) }}</td>
                <td><strong>{{ $peso($b['window_revenue']) }}</strong></td><td>{{ $b['orders_today'] }}</td><td>{{ $b['orders_window'] }}</td></tr>
        @empty
            <tr><td colspan="6">No branches yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="block-head"><h2>Daily revenue</h2></div>
<div class="card" style="max-width:none;overflow-x:auto">
    <table class="table">
        <tbody>
        @foreach (array_reverse($summary['daily'] ?? []) as $d)
            <tr><td style="width:8rem">{{ \Carbon\Carbon::parse($d['date'])->format('D, M j') }}</td>
                <td><div class="bar" style="margin:0"><span class="high" style="width: {{ round($d['revenue'] / $maxDay * 100) }}%"></span></div></td>
                <td style="width:8rem;text-align:right">{{ $peso($d['revenue']) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>

@if ($isOwner)
<div class="block-head"><h2>Top products ({{ $days }} days)</h2></div>
<div class="card" style="max-width:none;overflow-x:auto">
    <table class="table">
        <thead><tr><th>Product</th><th>Units</th><th>Revenue</th></tr></thead>
        <tbody>
        @forelse ($summary['products'] ?? [] as $p)
            <tr><td>{{ $p['product_name'] }}</td><td>{{ $p['units'] }}</td><td>{{ $peso($p['revenue']) }}</td></tr>
        @empty
            <tr><td colspan="3">No sales in this period.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
@endsection
