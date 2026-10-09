@extends('layouts.base')
@section('body-class', 'light-body panel-body')
@section('content')
@php
    $nav = [['Dashboard', route('dashboard'), 'dashboard'], ['Sales', route('section', 'sales'), 's/sales'], ['Products', route('section', 'products'), 's/products'],
        ['Inventory', route('section', 'inventory'), 's/inventory'], ['Deliveries', route('section', 'deliveries'), 's/deliveries'],
        ['Driver Tracking', route('section', 'driver-tracking'), 's/driver-tracking'], ['Payments', route('admin.payments.index'), 'admin/payments'],
        ['Refunds', route('admin.refunds.index'), 'admin/refunds'], ['Loyalty', route('loyalty.index'), 'loyalty*'], ['Reports', route('section', 'reports'), 's/reports']];
    if (auth()->user()->isAdmin()) { $nav[] = ['Users', route('admin.users.index'), 'admin/users*']; $nav[] = ['Activity Logs', route('admin.logs'), 'admin/activity-logs']; }
    $platform = app(\App\Services\Platform::class);
    // The badge must never break a page (e.g. the "not linked" or "database unreachable" pages).
    try { $pending = auth()->user()->isAdmin() ? app(\App\Services\OrderQueryService::class)->pendingRefundsCount() : 0; }
    catch (\Throwable $e) { $pending = 0; }
@endphp
<div class="panel" x-data="{ open: false }">
    <aside class="sidebar" :class="{ open }">
        <div class="brand">@include('partials.logo') <span>Melai Nuts</span></div>
        <nav>
            @foreach ($nav as [$label, $url, $match])
                <a href="{{ $url }}" class="{{ request()->is($match) ? 'active' : '' }}">{{ $label }}
                    @if ($label === 'Refunds' && $pending) <span class="badge-count">{{ $pending }}</span> @endif</a>
            @endforeach
        </nav>
    </aside>
    <div class="panel-main">
        <header class="topbar">
            <button class="menu-btn" @click="open = !open" aria-label="Menu">☰</button>
            <strong>@yield('page-title')</strong>
            <div class="topbar-right">
                @if (! $platform->isSupabase()) <span class="pill-branch" title="DATA_SOURCE=mock: numbers on these pages are demo data, not the app's real data">Demo data</span> @endif
                <a href="{{ auth()->user()->isAdmin() ? route('admin.refunds.index', ['status' => 'requested']) : route('dashboard') }}" class="bell" title="Pending refunds">🔔 @if ($pending)<span class="badge-count">{{ $pending }}</span>@endif</a>
                <span>{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-outline" type="submit">Logout</button></form>
            </div>
        </header>
        <main class="panel-content">@yield('panel')</main>
    </div>
</div>
@endsection
