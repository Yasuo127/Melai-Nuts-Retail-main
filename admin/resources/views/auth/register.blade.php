@extends('layouts.auth')
@section('title', 'Create Account · Melai Nuts Admin')
@section('heading1', 'Create')
@section('heading2', 'Account')
@section('blurb', 'Sign up in seconds. An admin will grant your access before you can use the dashboard.')
@section('form')
<form method="POST" action="{{ route('register') }}" novalidate x-cloak
      x-data="authForm({ mode: 'register', old: @js(['name' => old('name', ''), 'email' => old('email', '')]), errors: @js($errors->toArray()) })"
      @submit="onSubmit($event)">
    @csrf
    <h2 class="form-title">Create Account</h2>

    <x-field name="name" label="Full name" autocomplete="name" />
    <x-field name="email" label="Email" type="email" autocomplete="email" />

    <x-field name="password" label="Password" password autocomplete="new-password">
        <div class="strength-bar"><span :class="strength.cls" :style="`width: ${strength.pct}%`"></span></div>
        <p class="strength-label" x-show="values.password" x-text="strength.label"></p>
        <ul class="rules">
            <template x-for="r in ruleList" :key="r.label">
                <li :class="{ ok: r.ok }" x-text="r.label"></li>
            </template>
        </ul>
    </x-field>

    <x-field name="password_confirmation" label="Confirm password" password autocomplete="new-password">
        <p class="field-ok" x-show="values.password_confirmation && values.password_confirmation === values.password && !error('password_confirmation')">Passwords match.</p>
    </x-field>

    <button type="submit" class="btn-primary" :disabled="submitting">
        <span x-text="submitting ? 'Creating account…' : 'Create Account'"></span>
    </button>
    <p class="switch">Already have an account? <a href="{{ route('login') }}" class="link">Login</a></p>
</form>
@endsection
