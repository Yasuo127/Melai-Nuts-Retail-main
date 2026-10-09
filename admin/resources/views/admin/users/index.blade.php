@extends('layouts.panel')
@section('title', 'Users · Melai Nuts Admin')
@section('page-title', 'Users')
@section('panel')
<div class="block-head">
    <h2>Users</h2>
    <a class="btn-solid" href="{{ route('admin.users.create') }}">+ Create account</a>
</div>

@if (session('status'))
    <div class="card" style="border-color:#2E7D4F;background:#f1f8f2">{{ session('status') }}</div>
@endif
@if (session('error')) <div class="card" style="border-color:#C0392B;background:#fdecea">{{ session('error') }}</div> @endif
@if ($errors->any())
    <div class="card" style="border-color:#C0392B;background:#fdecea">
        <ul style="margin:0;padding-left:1.1rem">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
@endif

@if ($liveData)
    <div class="card" style="max-width:none">
        <p style="margin:0"><strong>App account link.</strong> Each login acts in the Melai Nuts app's database as the staff or owner
            account it is linked to, and sees only what that account may see (owners: all branches; staff: their branch).
            Admins link to an <em>owner</em>, staff to a <em>staff</em> account, with the same email. Register people in the app's
            User Management first.</p>
        @if ($directoryError) <p class="muted" style="margin-bottom:0">Could not load the app's staff list: {{ $directoryError }}</p>
        @elseif (! auth()->user()->staff_uid) <p class="muted" style="margin-bottom:0">Link your own login first: <code>php artisan melai:link-staff {{ auth()->user()->email }} &lt;your Firebase UID&gt;</code></p>
        @endif
    </div>
@endif

<div class="card" style="max-width:none;overflow-x:auto">
    <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Access</th><th>Status</th>@if ($liveData)<th>App account</th>@endif<th>Last login</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td><span class="status {{ $u->role === 'admin' ? 'high' : 'info' }}">{{ ucfirst($u->role) }}</span></td>
                <td><span class="status {{ $u->has_access ? 'high' : 'low' }}">{{ $u->has_access ? 'Granted' : 'Waiting' }}</span></td>
                <td><span class="status {{ $u->is_active ? 'high' : 'low' }}">{{ $u->is_active ? 'Active' : 'Deactivated' }}</span></td>
                @if ($liveData)
                    <td>
                        @if ($u->staff_uid)
                            @php $p = $directory[$u->staff_uid] ?? null; @endphp
                            <span class="status high">Linked</span>
                            <span class="muted">{{ $p ? $p['full_name'].' · '.ucfirst($p['role']).($p['branch_name'] ? ' · '.$p['branch_name'] : '') : $u->staff_uid }}</span>
                            @unless ($u->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.unlink', $u) }}" style="display:inline" onsubmit="return confirm('Unlink this login from its app account?')">
                                    @csrf<button class="btn-outline" type="submit" style="padding:.2rem .6rem">Unlink</button>
                                </form>
                            @endunless
                        @elseif ($u->role === 'driver')
                            <span class="muted">Not applicable</span>
                        @elseif (count($directory))
                            @php $wanted = $u->isAdmin() ? 'owner' : 'staff';
                                 $choices = collect($directory)->filter(fn ($s) => $s['role'] === $wanted && $s['is_active']
                                     && mb_strtolower($s['email']) === mb_strtolower($u->email)); @endphp
                            @if ($choices->isEmpty())
                                <span class="muted">No active app {{ $wanted }} with this email</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.link', $u) }}" style="display:flex;gap:.4rem">
                                    @csrf
                                    <select class="light-input" name="staff_uid">
                                        @foreach ($choices as $s) <option value="{{ $s['firebase_uid'] }}">{{ $s['full_name'] }}{{ $s['branch_name'] ? ' · '.$s['branch_name'] : '' }}</option> @endforeach
                                    </select>
                                    <button class="btn-outline" type="submit" style="padding:.2rem .6rem">Link</button>
                                </form>
                            @endif
                        @else
                            <span class="status low">Not linked</span>
                        @endif
                    </td>
                @endif
                <td>{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                <td>
                    @unless ($u->isAdmin())
                        <form method="POST" action="{{ route('admin.users.toggle-access', $u) }}" style="display:inline">
                            @csrf<button class="btn-outline" type="submit" style="padding:.3rem .8rem">{{ $u->has_access ? 'Restrict' : 'Grant access' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.users.toggle-active', $u) }}" style="display:inline">
                            @csrf<button class="btn-outline" type="submit" style="padding:.3rem .8rem">{{ $u->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                        </form>
                    @endunless
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
