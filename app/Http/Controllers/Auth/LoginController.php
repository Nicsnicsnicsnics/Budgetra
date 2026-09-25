<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function showForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (auth()->attempt($credentials, $request->boolean('remember'))) {
            if (auth()->user()->role === 'banned') {
                auth()->logout();

                return $this->rejected($request,
                    'Your account has been suspended. Contact support if you believe this is a mistake.');
            }

            $request->session()->regenerate();
            $request->session()->put('collapse_sidebar', true);

            $destination = auth()->user()->role === 'admin'
                ? route('admin.dashboard')
                : redirect()->intended(route('trips.index'))->getTargetUrl();

            // The form posts with fetch() so the password survives a rejection
            // and the button can spin for the real duration of the request
            // (see auth-form.js). Nothing navigates on its own in that case,
            // so hand the browser the address to go to.
            if ($request->expectsJson()) {
                return response()->json(['redirect' => $destination]);
            }

            return redirect()->to($destination);
        }

        return $this->rejected($request, 'These credentials do not match our records.');
    }

    /**
     * A sign-in that was refused for a reason validation cannot express.
     *
     * Shaped like a validation failure either way — 422 with an errors bag for
     * the fetch path, a redirect with a flashed bag otherwise — so the page
     * shows it under the email field exactly as it shows a malformed address.
     */
    private function rejected(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'errors'  => ['email' => [$message]],
            ], 422);
        }

        return back()
            ->withErrors(['email' => $message])
            ->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
