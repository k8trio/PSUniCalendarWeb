<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ThrottlesStaffLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class StaffAuthController extends Controller
{
    use ThrottlesStaffLogin;

    public function showLogin()
    {
        if (Auth::check()) {
            return match (Auth::user()->role) {
                'office' => redirect()->route('office.dashboard'),
                'planning_office' => redirect()->route('planning_office.dashboard'),
                default => view('auth.staff-login'),
            };
        }

        return view('auth.staff-login');
    }

    public function redirectToLogin()
    {
        return redirect()->route('login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $this->ensureNotRateLimited($request);
        $key = $this->throttleKey($request);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return redirect()->route('login')->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->withInput($request->only('email', 'remember'));
        }

        $destination = match (Auth::user()->role) {
            'office' => route('office.dashboard'),
            'planning_office' => route('planning_office.dashboard'),
            default => null,
        };

        if ($destination === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($key, 60);

            return redirect()->route('login')->withErrors([
                'email' => 'This account does not have staff portal access.',
            ])->withInput($request->only('email'));
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended($destination);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
