<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Admin accounts have no reason to be inside the traveler-facing shell —
// landing there (e.g. via the Profile link, a bookmark, or a stale link)
// shows the traveler sidebar/layout instead of the admin one. Bounce them
// back to the admin dashboard before any traveler view gets a chance to render.
class RedirectAdminFromTraveler
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->user()?->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return $next($request);
    }
}
