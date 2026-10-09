@extends('layouts.base')
@section('content')
<main class="center-page"><div class="card">
    <h1>Forgot your password?</h1>
    <p>Password reset by email isn't set up yet. Ask an administrator to reset it for you.</p>
    <div class="btn-row"><a class="btn-solid" href="{{ route('login') }}">Back to login</a></div>
</div></main>
@endsection
