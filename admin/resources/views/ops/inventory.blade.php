@extends('layouts.panel')
@section('title', 'Inventory · Melai Nuts Admin')
@section('page-title', 'Inventory')
@section('panel')
@include('partials.flash')

<form method="GET" class="card filter-row" style="max-width:none">
    <label for="branch"><strong>Branch</strong></label>
    <select class="light-input" id="branch" name="branch" onchange="this.form.submit()">
        @foreach ($branches as $b) <option value="{{ $b['id'] }}" @selected($branchId === $b['id'])>{{ $b['name'] }}</option> @endforeach
    </select>
</form>
<p class="muted">Live stock from the Melai Nuts app. Receiving, adjusting and transferring stock are done in the app (batches are tracked first-expiry-first-out), and every change appears in the history below.</p>

<div class="card" style="max-width:none;overflow-x:auto">
    <table class="table">
        <thead><tr><th>Product</th><th>Variant</th><th>In stock</th><th>Restock at</th><th>In batches</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($inventory['items'] ?? [] as $i)
            @php $state = $i['quantity'] <= 0 ? ['low', 'Out of stock'] : ($i['quantity'] <= $i['restock_threshold'] ? ['average', 'Low'] : ['high', 'OK']); @endphp
            <tr><td>{{ $i['product_name'] }}</td><td>{{ $i['variant_label'] ?: 'Regular' }}</td><td><strong>{{ $i['quantity'] }}</strong></td>
                <td>{{ $i['restock_threshold'] }}</td><td>{{ $i['batched_quantity'] }}</td>
                <td><span class="status {{ $state[0] }}">{{ $state[1] }}</span></td></tr>
        @empty
            <tr><td colspan="6">No products in this branch yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="block-head"><h2>Recent stock changes</h2></div>
<div class="card" style="max-width:none;overflow-x:auto">
    <table class="table">
        <thead><tr><th>When</th><th>Product</th><th>Change</th><th>Type</th><th>Reason / reference</th><th>By</th></tr></thead>
        <tbody>
        @forelse ($movements as $m)
            <tr><td>{{ \Carbon\Carbon::parse($m['created_at'])->setTimezone(config('app.timezone'))->format('M j, g:i A') }}</td>
                <td>{{ $m['product_name'] ?? '—' }}{{ ($m['variant_label'] ?? '') && $m['variant_label'] !== 'Regular' ? ' ('.$m['variant_label'].')' : '' }}</td>
                <td><strong>{{ $m['quantity_change'] > 0 ? '+' : '' }}{{ $m['quantity_change'] }}</strong></td>
                <td>{{ str_replace('_', ' ', $m['movement_type']) }}</td>
                <td class="muted">{{ $m['reason'] ?: '' }} {{ $m['reference'] ? '· '.$m['reference'] : '' }}</td>
                <td>{{ $m['staff_name'] ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="6">No stock changes recorded yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
