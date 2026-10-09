@extends('layouts.panel')
@section('title', 'Create Account · Melai Nuts Admin')
@section('page-title', 'Create account')
@section('panel')
<div class="card" style="max-width:32rem">
    <h2>Create a staff or driver account</h2>
    <p class="muted">This account gets access immediately — no waiting page.</p>

    @if ($errors->any())
        <div class="alert" style="color:#C0392B;background:#fdecea;border-color:#f5b7b1">
            <ul style="margin:0;padding-left:1.1rem">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="field"><label>Full name</label><input class="light-input" name="name" value="{{ old('name') }}"></div>
        <div class="field"><label>Email</label><input class="light-input" type="email" name="email" value="{{ old('email') }}"></div>
        <div class="field">
            <label>Role</label>
            <select class="light-input" name="role">
                <option value="staff" @selected(old('role') === 'staff')>Staff</option>
                <option value="driver" @selected(old('role') === 'driver')>Driver</option>
            </select>
        </div>
        <div class="field"><label>Password</label><input class="light-input" type="password" name="password"></div>
        <div class="field"><label>Confirm password</label><input class="light-input" type="password" name="password_confirmation"></div>
        <button class="btn-solid" type="submit">Create account</button>
        <a class="btn-outline" href="{{ route('admin.users.index') }}">Cancel</a>
    </form>
</div>
@endsection
