@extends('layouts.panel')
@section('title', $title.' · Melai Nuts Admin')
@section('page-title', $title)
@section('panel')
    <div class="card"><h2>{{ $title }}</h2>
        @if ($slug === 'driver-tracking' && $liveData)
            <p>The Melai Nuts app does not record rider GPS positions yet, so live tracking is not available. Delivery runs and stop
                statuses are on the <a class="link" href="{{ route('section', 'deliveries') }}">Deliveries</a> page.</p>
        @elseif ($slug === 'reports' && $liveData)
            <p>Downloadable reports are not built yet. Sales totals by branch, by day and by product are on the
                <a class="link" href="{{ route('section', 'sales') }}">Sales</a> page.</p>
        @elseif (! $liveData && in_array($slug, ['sales', 'products', 'inventory', 'deliveries'], true))
            <p>This page shows live data from the Melai Nuts app. Set <code>DATA_SOURCE=supabase</code> in <code>.env</code> to turn it on.</p>
        @else
            <p>This page is coming in a later phase.</p>
        @endif
    </div>
@endsection
