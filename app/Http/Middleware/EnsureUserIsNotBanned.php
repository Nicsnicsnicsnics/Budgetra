<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBanned
{
    // Catches a user who was already logged in at the moment an admin banned
    // them — LoginController rejects a banned login attempt up front, but
    // that alone wouldn't affect an existing session. Runs on every web
    // request so a ban takes effect on the banned user's very next action.
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->role === 'banned') {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been suspended. Contact support if you believe this is a mistake.',
            ]);
        }
        return $next($request);
    }
}
