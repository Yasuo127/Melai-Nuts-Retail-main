@extends('layouts.panel')
@section('title', 'Refunds · Melai Nuts Admin')
@section('page-title', 'Refunds')
@section('panel')
@php $peso = fn ($n) => '₱'.number_format($n, $liveData ? 2 : 0);
$methodLabel = fn ($m) => ['cod' => 'COD', 'cash' => 'Cash', 'gcash' => 'GCash', 'maya' => 'Maya', 'card' => 'Card'][$m] ?? strtoupper($m);
$labels = ['requested' => 'Requested', 'approved_processing' => $liveData ? 'Approved · send money back' : 'Approved · processing', 'rejected' => 'Rejected', 'refunded' => 'Refunded', 'failed' => 'Failed'];
$tone = ['requested' => 'average', 'approved_processing' => 'info', 'rejected' => 'low', 'refunded' => 'high', 'failed' => 'low'];
@endphp

@if (session('status')) <div class="card" style="border-color:#2E7D4F;background:#f1f8f2">{{ session('status') }}</div> @endif
@if (session('error')) <div class="card" style="border-color:#C0392B;background:#fdecea">{{ session('error') }}</div> @endif
@if ($errors->any())
    <div class="card" style="border-color:#C0392B;background:#fdecea">
        <ul style="margin:0;padding-left:1.1rem">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
@endif

@if ($liveData)
    <p class="muted">Refund requests made in the Melai Nuts app. Cash refunds are completed in one step; online refunds are approved here, paid back through the HitPay dashboard, then marked refunded with the HitPay reference.</p>
@endif

<form method="GET" class="card filter-row">
    <select class="light-input" name="status" onchange="this.form.submit()">
        <option value="">All statuses</option>
        @foreach ($labels as $k => $label) <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $label }}</option> @endforeach
    </select>
    <select class="light-input" name="branch" onchange="this.form.submit()">
        <option value="">All branches</option>
        @foreach ($branches as $b) <option value="{{ $b['id'] }}" @selected(($filters['branch'] ?? '') === $b['id'])>{{ $b['name'] }}</option> @endforeach
    </select>
    @if (array_filter($filters)) <a href="{{ route('admin.refunds.index') }}" class="btn-outline" style="padding:.5rem 1rem">Clear</a> @endif
</form>

<div class="grid-4" style="grid-template-columns:repeat(auto-fit,minmax(20rem,1fr))">
@forelse ($refunds as $r)
    <div class="card-b" style="text-align:left">
        <div class="card-top">
            <strong>{{ $r['id'] }}</strong>
            <span class="status {{ $tone[$r['effectiveRefundStatus']] ?? 'info' }}">{{ $labels[$r['effectiveRefundStatus']] ?? $r['effectiveRefundStatus'] }}</span>
        </div>
        @if ($r['flaggedForStockReturn']) <div class="flag">⚠ Undelivered — check stock return / cancel delivery</div> @endif
        @php $refundAmount = $liveData ? $r['refundAmount'] : $r['total']; @endphp
        <p class="muted" style="margin:.25rem 0">{{ $r['branchName'] ?? (collect($branches)->firstWhere('id', $r['branch'])['name'] ?? '—') }} · {{ $methodLabel($r['paymentMethod']) }} · {{ $r['createdAt']->format('M j, g:i A') }}</p>
        <p style="margin:.4rem 0"><strong>{{ $r['customer'] }}</strong> — {{ $peso($refundAmount) }}@if ($liveData && $refundAmount != $r['total']) <span class="muted">of {{ $peso($r['total']) }}</span>@endif</p>
        @if ($liveData && ! empty($r['refundId'])) <p class="muted" style="margin:0">Request {{ $r['refundId'] }}</p> @endif
        <p class="muted" style="margin:.2rem 0 .6rem">"{{ $r['refundReason'] }}"</p>
        @unless ($liveData)
            @if ($r['refundPhoto']) <p class="muted">📷 Photo attached</p> @else <p class="muted">No photo attached</p> @endif
        @endunless

        @if ($r['effectiveRefundStatus'] === 'requested')
            <div class="refund-actions">
                <form method="POST" action="{{ route('admin.refunds.approve', $r['id']) }}" onsubmit="return confirm('Approve this refund of {{ $peso($refundAmount) }}?')">
                    @csrf
                    @if ($r['paymentMethod'] === $cashMethod)
                        <input type="hidden" name="note" value="Refunded in cash on delivery return.">
                    @endif
                    <button class="btn-solid" type="submit" style="padding:.45rem 1rem">Approve</button>
                </form>
                <button class="btn-outline" type="button" style="padding:.45rem 1rem" onclick="document.getElementById('reject-{{ $r['id'] }}').showModal()">Reject</button>
            </div>
            <dialog id="reject-{{ $r['id'] }}" class="reject-dialog">
                <form method="POST" action="{{ route('admin.refunds.reject', $r['id']) }}">
                    @csrf
                    <p><strong>Reject refund for {{ $r['id'] }}</strong></p>
                    <textarea class="light-input" name="reason" rows="3" placeholder="Reason (required)" required></textarea>
                    <div class="btn-row" style="justify-content:flex-start;margin-top:.75rem">
                        <button class="btn-solid" type="submit" style="padding:.45rem 1rem">Confirm reject</button>
                        <button class="btn-outline" type="button" style="padding:.45rem 1rem" onclick="this.closest('dialog').close()">Cancel</button>
                    </div>
                </form>
            </dialog>
        @elseif ($r['effectiveRefundStatus'] === 'approved_processing' && $liveData)
            <form method="POST" action="{{ route('admin.refunds.complete', $r['id']) }}" class="refund-actions" style="flex-wrap:wrap">
                @csrf
                <input class="light-input" name="reference" placeholder="HitPay refund reference" required minlength="4" maxlength="120" style="flex:1;min-width:10rem">
                <button class="btn-solid" type="submit" style="padding:.45rem 1rem">Mark refunded</button>
            </form>
            <button class="btn-outline" type="button" style="padding:.4rem .9rem;margin-top:.5rem" onclick="document.getElementById('reject-{{ $r['id'] }}').showModal()">Reject</button>
            <dialog id="reject-{{ $r['id'] }}" class="reject-dialog">
                <form method="POST" action="{{ route('admin.refunds.reject', $r['id']) }}">
                    @csrf
                    <p><strong>Reject refund for {{ $r['id'] }}</strong></p>
                    <textarea class="light-input" name="reason" rows="3" placeholder="Reason (required, sent to the customer)" required></textarea>
                    <div class="btn-row" style="justify-content:flex-start;margin-top:.75rem">
                        <button class="btn-solid" type="submit" style="padding:.45rem 1rem">Confirm reject</button>
                        <button class="btn-outline" type="button" style="padding:.45rem 1rem" onclick="this.closest('dialog').close()">Cancel</button>
                    </div>
                </form>
            </dialog>
        @elseif ($r['effectiveRefundStatus'] === 'approved_processing')
            <form method="POST" action="{{ route('admin.refunds.retry', $r['id']) }}">
                @csrf<button class="btn-outline" type="submit" style="padding:.4rem .9rem">Retry with PayMongo</button>
            </form>
        @elseif ($r['override']?->refund_note)
            <p class="muted">Note: {{ $r['override']->refund_note }}</p>
        @endif
    </div>
@empty
    <div class="card">No refund requests match these filters.</div>
@endforelse
</div>
@endsection
