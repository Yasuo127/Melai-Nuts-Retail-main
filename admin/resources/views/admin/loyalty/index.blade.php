@extends('layouts.panel')
@section('title', 'Loyalty · Melai Nuts Admin')
@section('page-title', 'Loyalty')
@section('panel')
@php $peso = fn ($n) => '₱'.number_format($n); @endphp

@if (session('status')) <div class="card" style="border-color:#2E7D4F;background:#f1f8f2">{{ session('status') }}</div> @endif
@if (session('error')) <div class="card" style="border-color:#C0392B;background:#fdecea">{{ session('error') }}</div> @endif
@if ($errors->any())
    <div class="card" style="border-color:#C0392B;background:#fdecea">
        <ul style="margin:0;padding-left:1.1rem">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
@endif

@if (auth()->user()->isAdmin() && $liveData)
<div class="block-head"><h2>Settings</h2></div>
<div class="card" style="max-width:none">
    <p class="muted" style="margin-top:0">These are the rules the Melai Nuts app uses at checkout. Changes apply to new orders right away.</p>
    <form method="POST" action="{{ route('admin.loyalty.settings.update') }}" class="filter-row">
        @csrf @method('PUT')
        <div class="field" style="margin:0">
            <label>Earn rate (PHP spent per 1 point)</label>
            <input class="light-input" type="number" min="1" step="0.01" name="earn_pesos_per_point" value="{{ old('earn_pesos_per_point', $settings['earn_pesos_per_point'] ?? 50) }}">
        </div>
        <div class="field" style="margin:0">
            <label>Redeem rate (points per PHP 1 off)</label>
            <input class="light-input" type="number" min="0.01" step="0.01" name="points_per_peso" value="{{ old('points_per_peso', $settings['points_per_peso'] ?? 1) }}">
        </div>
        <div class="field" style="margin:0">
            <label>Max discount from points (% of order)</label>
            <input class="light-input" type="number" min="1" max="100" step="0.01" name="max_discount_percent" value="{{ old('max_discount_percent', $settings['max_discount_percent'] ?? 100) }}">
        </div>
        <button class="btn-solid" type="submit" style="align-self:flex-end">Save settings</button>
    </form>
    <p class="muted" style="margin-bottom:0">Points expiry is not supported by the app yet, so it is not offered here.</p>
</div>
@elseif (auth()->user()->isAdmin())
<div class="block-head"><h2>Settings</h2></div>
<div class="card" style="max-width:none">
    <form method="POST" action="{{ route('admin.loyalty.settings.update') }}" class="filter-row">
        @csrf @method('PUT')
        <div class="field" style="margin:0">
            <label>Earn rate (PHP per point)</label>
            <input class="light-input" type="number" min="1" name="earn_rate_pesos" value="{{ old('earn_rate_pesos', $settings->earn_rate_pesos) }}">
        </div>
        <div class="field" style="margin:0">
            <label>Redeem rate (points per PHP 1 off)</label>
            <input class="light-input" type="number" min="1" name="redeem_points_per_peso" value="{{ old('redeem_points_per_peso', $settings->redeem_points_per_peso) }}">
        </div>
        <div class="field" style="margin:0">
            <label>Max redeem per order (points)</label>
            <input class="light-input" type="number" min="0" name="max_redeem_per_order" value="{{ old('max_redeem_per_order', $settings->max_redeem_per_order) }}">
        </div>
        <div class="field" style="margin:0">
            <label>Points expiry (months)</label>
            <input class="light-input" type="number" min="1" name="expiry_months" value="{{ old('expiry_months', $settings->expiry_months) }}">
        </div>
        <button class="btn-solid" type="submit" style="align-self:flex-end">Save settings</button>
    </form>
</div>
@endif

<div class="block-head">
    <h2>Members</h2>
    <form method="GET" style="display:flex;gap:.5rem">
        <input class="light-input" type="search" name="q" value="{{ $q }}" placeholder="Search name, email or card number">
        <button class="btn-outline" type="submit" style="padding:.5rem 1rem">Search</button>
    </form>
</div>

<div class="card" style="max-width:none;overflow-x:auto">
    <table class="table">
        <thead><tr><th>Member</th><th>Card #</th>@unless ($liveData)<th>Tier</th>@endunless<th>Earned</th><th>Redeemed</th><th>Refunded</th>@if ($liveData)<th>Adjusted</th>@endif<th>Balance</th><th></th></tr></thead>
        <tbody>
        @forelse ($members as $m)
            <tr>
                <td>{{ $m['name'] }}<br><span class="muted">{{ $m['email'] }}</span></td>
                <td>{{ $m['cardNumber'] }}</td>
                @unless ($liveData)<td><span class="status info">{{ $m['tier'] }}</span></td>@endunless
                <td>{{ $m['earned'] }}</td>
                <td>{{ $m['redeemed'] }}</td>
                <td>{{ $m['refunded'] }}</td>
                @if ($liveData)<td>{{ $m['adjusted'] > 0 ? '+' : '' }}{{ $m['adjusted'] }}</td>@endif
                <td><strong>{{ $m['balance'] }}</strong></td>
                <td><a class="btn-outline" style="padding:.3rem .8rem" href="{{ route('loyalty.show', $m['id']) }}">View</a></td>
            </tr>
        @empty
            <tr><td colspan="9">No members match that search.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
