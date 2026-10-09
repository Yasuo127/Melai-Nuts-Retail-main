@extends('layouts.auth')
@section('title', 'Login · Melai Nuts Admin')
@section('heading1', 'Welcome')
@section('heading2', 'Back')
@section('blurb', 'Sign in to manage branches, stock, deliveries, payments, refunds, and customer rewards.')
@section('form')
<form method="POST" action="{{ route('login') }}" novalidate x-cloak
      x-data="authForm({ mode: 'login', old: @js(['email' => old('email', '')]), errors: @js($errors->toArray()) })"
      @submit="onSubmit($event)">
    @csrf
    <h2 class="form-title">Login</h2>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    <x-field name="email" label="Email" type="email" autocomplete="email" placeholder="admin@melainuts.com" />
    <x-field name="password" label="Password" password autocomplete="current-password" />

    <div class="row">
        <label><input type="checkbox" name="remember"> Remember me</label>
        <a href="{{ route('password.request') }}" class="link">Forgot password?</a>
    </div>

    <button type="submit" class="btn-primary" :disabled="submitting">
        <span x-text="submitting ? 'Logging in…' : 'Login'"></span>
    </button>
    <p class="switch">No account yet? <a href="{{ route('register') }}" class="link">Create Account</a></p>
</form>
@endsection
