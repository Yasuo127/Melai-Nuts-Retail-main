<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request)
    {
        $request->authenticate();
        $user = Auth::user();

        if (! $user->is_active) {
            ActivityLogger::log('auth.login_blocked', 'Deactivated account tried to log in', $user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['auth' => 'This account has been deactivated. Contact an administrator.']);
        }

        $request->session()->regenerate(); // prevents session fixation
        $user->forceFill(['last_login_at' => now()])->save();
        ActivityLogger::log('auth.login', 'Logged in', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        ActivityLogger::log('auth.logout', 'Logged out');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }
}
