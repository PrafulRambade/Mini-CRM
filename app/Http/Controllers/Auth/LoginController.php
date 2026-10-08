<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticateUser();

        Auth::login($user, $request->boolean('remember'));
        app(ActivityLogger::class)->log('auth.login', 'Signed in', $user, ['channel' => 'web'], $user);

        // Prevent session fixation.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        app(ActivityLogger::class)->log('auth.logout', 'Signed out', $request->user(), ['channel' => 'web']);
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been logged out.');
    }
}
