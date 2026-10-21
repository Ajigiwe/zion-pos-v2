<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string'],
        ]);

        $username = strtolower(trim($credentials['username']));

        RateLimiter::hit($this->throttleKey($request), decaySeconds: 60);

        if (RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            $seconds = RateLimiter::availableIn($this->throttleKey($request));
            throw ValidationException::withMessages([
                'username' => "Too many attempts. Try again in {$seconds}s.",
            ]);
        }

        if (Auth::attempt(['username' => $username, 'password' => $credentials['password']], remember: true)) {
            RateLimiter::clear($this->throttleKey($request));
            $request->session()->regenerate();

            $user = Auth::user();
            if (! $user->is_active) {
                Auth::logout();
                throw ValidationException::withMessages(['username' => 'This account has been disabled.']);
            }

            AuditLog::record('LOGIN', $user->id, 'user', $user->id, 'Signed in');

            // Never honor a stale intended target pointing at a JSON API probe
            // (e.g. /api/ping), otherwise login dumps the user on raw JSON.
            $intended = session('url.intended');
            if (is_string($intended) && str_starts_with(parse_url($intended, PHP_URL_PATH) ?? '', '/api/')) {
                $request->session()->remove('url.intended');
            }

            return redirect()->intended($user->can('pos.use') ? route('pos') : route('dashboard'));
        }

        throw ValidationException::withMessages(['username' => 'Wrong username or password.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::record('LOGOUT', Auth::id());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('username')).'|'.$request->ip());
    }
}
