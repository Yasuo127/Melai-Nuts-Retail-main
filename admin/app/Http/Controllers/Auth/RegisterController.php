<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request)
    {
        $data = $request->validated();

        try {
            // Role is set here, never from the request: every new account is "staff" with no access.
            $user = (new User)->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => User::ROLE_STAFF,
                'is_active' => true,
                'has_access' => false,
            ]);
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'An account with this email already exists.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        ActivityLogger::log('account.created', "New account created: {$user->email}", $user, ['role' => User::ROLE_STAFF]);

        return redirect()->route('waiting');
    }
}
