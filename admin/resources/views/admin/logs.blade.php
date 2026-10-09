@extends('layouts.base')
@section('title', 'Activity Logs')
@section('content')
<header class="topbar"><a href="{{ route('dashboard') }}">← Dashboard</a><strong>Activity Logs</strong></header>
<main style="padding:2rem;overflow-x:auto">
    <h2>Admin website</h2>
    <table class="table">
        <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr><td>{{ $log->created_at }}</td><td>{{ $log->user?->name ?? '—' }}</td><td>{{ $log->action }}</td><td>{{ $log->description }}</td><td>{{ $log->ip_address }}</td></tr>
        @empty
            <tr><td colspan="5">No activity yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $logs->links() }}</div>

    @if ($liveData)
        <h2 style="margin-top:2rem">Melai Nuts app (staff audit log)</h2>
        <p class="muted">Append-only record kept by the app's database: stock, products, refunds, loyalty and staff changes, whether made in the app or on this website. Latest 100.</p>
        @if ($appLogsError)
            <p class="muted">Not available: {{ $appLogsError }}</p>
        @else
            <table class="table">
                <thead><tr><th>When</th><th>Staff</th><th>Branch</th><th>Action</th><th>Record</th><th>Details</th></tr></thead>
                <tbody>
                @forelse ($appLogs as $l)
                    <tr><td>{{ \Carbon\Carbon::parse($l['created_at'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $l['staff_name'] ?? $l['staff_email'] ?? '—' }}</td><td>{{ $l['branch_name'] ?? '—' }}</td>
                        <td>{{ $l['action'] }}</td><td>{{ $l['entity_type'] }} {{ $l['entity_id'] }}</td>
                        <td class="muted" style="max-width:28rem;word-break:break-word">{{ json_encode($l['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</td></tr>
                @empty
                    <tr><td colspan="6">No app activity yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        @endif
    @endif
</main>
@endsection
