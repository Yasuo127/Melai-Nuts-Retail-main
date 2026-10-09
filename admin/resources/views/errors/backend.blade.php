@extends('layouts.panel')
@section('title', $title.' · Melai Nuts Admin')
@section('page-title', $title)
@section('panel')
    <div class="card">
        <h2>{{ $title }}</h2>
        <p>{{ $message }}</p>
        @if ($title === 'Account not linked')
            <p class="muted">Pages with the app's business data act as your staff or owner account in the Melai Nuts app.
                @if (auth()->user()->isAdmin())
                    Link this login on the <a class="link" href="{{ route('admin.users.index') }}">Users</a> page, or for the first admin run
                    <code>php artisan melai:link-staff {{ auth()->user()->email }} &lt;Firebase UID&gt;</code>.
                @else
                    Ask an administrator to link your login to your staff account in the app.
                @endif
            </p>
        @endif
        <a class="btn-outline" style="padding:.4rem 1rem" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}">Go back</a>
    </div>
@endsection
