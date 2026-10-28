<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ThrottlesStaffLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class PlanningOfficeAuthController extends Controller
{
    use ThrottlesStaffLogin;

    private const ROLE = 'planning_office';

    public function showLogin()
    {
        if (Auth::check() && Auth::user()->role === self::ROLE) {
            return redirect()->route('planning_office.dashboard');
        }

        return view('auth.planning-office-login');
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

            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email', 'remember');
        }

        if (Auth::user()->role !== self::ROLE) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($key, 60);

            return back()->withErrors([
                'email' => 'This account does not have Planning Office access.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('planning_office.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('planning_office.login');
    }
}
