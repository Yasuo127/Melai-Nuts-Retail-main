<?php

use App\Exceptions\BackendActionException;
use App\Exceptions\BackendUnavailableException;
use App\Exceptions\StaffNotLinkedException;
use App\Http\Middleware\EnsureHasAccess;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResetRequestScope;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->prepend(ResetRequestScope::class);
        $middleware->alias([
            'role'       => EnsureRole::class,         // role:admin -> 403 if not allowed
            'has.access' => EnsureHasAccess::class,    // no access yet -> "Waiting" page
            'active'     => EnsureUserIsActive::class, // deactivated -> logged out
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));    // guests -> login
        $middleware->redirectUsersTo(fn () => route('dashboard')); // logged-in -> dashboard
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Shared-database (Supabase) problems are shown as clear messages, never as a stack trace.
        $show = function (string $title, string $message, int $status, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], $status);
            }
            if (! $request->isMethod('GET')) {
                return back()->withInput($request->except(['password', 'password_confirmation']))->with('error', $message);
            }

            return response()->view('errors.backend', ['title' => $title, 'message' => $message], $status);
        };
        $exceptions->render(fn (StaffNotLinkedException $e, Request $r) => $show('Account not linked', $e->getMessage(), 409, $r));
        $exceptions->render(fn (BackendActionException $e, Request $r) => $show('Not allowed', $e->getMessage(), 403, $r));
        $exceptions->render(fn (BackendUnavailableException $e, Request $r) => $show('Database unavailable', $e->getMessage(), 503, $r));
    })
    ->create();
