@extends('layouts.base')
@section('title', 'Waiting for admin access')
@section('content')
<main class="center-page"><div class="card">
    @include('partials.logo')
    <h1>Waiting for admin access</h1>
    <p>Your account <strong>{{ auth()->user()->email }}</strong> was created. An administrator needs to give you access before you can use the console.</p>
    <div class="btn-row">
        <a class="btn-solid" href="{{ route('dashboard') }}">Check again</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-outline" type="submit">Logout</button></form>
    </div>
</div></main>
@endsection
