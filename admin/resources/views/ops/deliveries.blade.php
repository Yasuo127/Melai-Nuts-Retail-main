@extends('layouts.panel')
@section('title', 'Deliveries · Melai Nuts Admin')
@section('page-title', 'Deliveries')
@section('panel')
@php $tone = ['Preparing' => 'average', 'On the way' => 'info', 'Delivered' => 'high', 'Cancelled' => 'low'];
$stopTone = ['pending' => 'average', 'enRoute' => 'info', 'delivered' => 'high', 'delayed' => 'low', 'skipped' => 'low']; @endphp
@include('partials.flash')

<p class="muted">Delivery runs created and dispatched by staff in the Melai Nuts app. Riders update each stop from the app.</p>

@forelse ($deliveries as $d)
    <div class="card" style="max-width:none;overflow-x:auto">
        <div class="card-top">
            <strong>{{ $d['id'] }} · {{ $d['driver'] }}{{ $d['vehicle'] ? ' ('.$d['vehicle'].')' : '' }}</strong>
            <span class="status {{ $tone[$d['status']] ?? 'info' }}">{{ $d['status'] }}</span>
        </div>
        <p class="muted" style="margin:.25rem 0 .5rem">{{ $d['branchName'] }} · created {{ $d['createdAt']?->format('M j, g:i A') }}</p>
        <table class="table">
            <thead><tr><th>#</th><th>Order</th><th>Customer</th><th>Address</th><th>Items</th><th>ETA</th><th>Stop status</th></tr></thead>
            <tbody>
            @foreach ($d['stops'] as $s)
                <tr><td>{{ $s['sequence_index'] + 1 }}</td><td>{{ $s['order_id'] }}</td><td>{{ $s['customer_name'] }}</td><td>{{ $s['address'] }}</td>
                    <td>{{ implode(', ', $s['items'] ?? []) }}</td><td>{{ $s['eta'] ?? '—' }}</td>
                    <td><span class="status {{ $stopTone[$s['status']] ?? 'info' }}">{{ $s['status'] }}</span>
                        @if ($s['issue_reason']) <div class="muted">{{ $s['issue_reason'] }}</div> @endif</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@empty
    <div class="card">No deliveries yet.</div>
@endforelse
@endsection
