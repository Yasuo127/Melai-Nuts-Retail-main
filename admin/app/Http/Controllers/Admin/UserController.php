<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminCreateUserRequest;
use App\Models\User;
use App\Exceptions\BackendActionException;
use App\Exceptions\BackendUnavailableException;
use App\Services\ActivityLogger;
use App\Services\Platform;
use App\Services\Supabase\StaffLinker;
use App\Services\Supabase\SupabaseRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Admin-only user management: create staff/driver accounts, grant/restrict access, activate/deactivate. */
class UserController extends Controller
{
    public function index(Platform $platform)
    {
        $directory = [];
        $directoryError = null;
        // The list of app staff/owner accounts to link to (owner-only in Supabase).
        if ($platform->isSupabase() && auth()->user()->staff_uid) {
            try {
                $directory = app(SupabaseRepository::class)->staffDirectory();
            } catch (BackendActionException|BackendUnavailableException $e) {
                $directoryError = $e->getMessage();
            }
        }

        return view('admin.users.index', [
            'users' => User::orderBy('name')->get(),
            'liveData' => $platform->isSupabase(),
            'directory' => collect($directory)->keyBy('firebase_uid')->all(),
            'directoryError' => $directoryError,
        ]);
    }

    /** Link a portal login to its staff/owner account in the app (verified by Supabase, see StaffLinker). */
    public function link(Request $request, User $user, StaffLinker $linker, Platform $platform)
    {
        abort_unless($platform->isSupabase(), 404);
        $data = $request->validate(['staff_uid' => ['required', 'string', 'max:128']]);
        $profile = $linker->link($user, $data['staff_uid'], $request->user());

        return back()->with('status', "{$user->email} is now linked to the app {$profile['role']} account \"{$profile['full_name']}\".");
    }

    public function unlink(Request $request, User $user, StaffLinker $linker, Platform $platform)
    {
        abort_unless($platform->isSupabase(), 404);
        abort_if($user->is($request->user()), 403, 'You cannot unlink your own account.');
        $linker->unlink($user, $request->user());

        return back()->with('status', "{$user->email} is no longer linked to an app account.");
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(AdminCreateUserRequest $request)
    {
        $data = $request->validated();

        try {
            $user = (new User)->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
                'is_active' => true,
                'has_access' => true, // admin created this account on purpose, so it's usable right away
            ]);
            $user->save();
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'An account with this email already exists.']);
        }

        ActivityLogger::log('account.created_by_admin', "Admin created account: {$user->email}", $user, [
            'role' => $user->role, 'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.users.index')->with('status', "Account created for {$user->email}.");
    }

    /** Give or restrict dashboard access (has_access). Admins can't be restricted this way. */
    public function toggleAccess(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 403);

        $user->forceFill(['has_access' => ! $user->has_access])->save();
        ActivityLogger::log($user->has_access ? 'access.granted' : 'access.restricted', "{$request->user()->name} changed access for {$user->email}", $user);

        return back()->with('status', $user->has_access ? "Access granted to {$user->email}." : "Access restricted for {$user->email}.");
    }

    /** Deactivate/reactivate a login. Admins can't deactivate themselves or other admins here. */
    public function toggleActive(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 403);

        $user->forceFill(['is_active' => ! $user->is_active])->save();
        ActivityLogger::log($user->is_active ? 'account.activated' : 'account.deactivated', "{$request->user()->name} toggled status for {$user->email}", $user);

        return back()->with('status', $user->is_active ? "{$user->email} reactivated." : "{$user->email} deactivated.");
    }
}
