<?php

namespace App\Http\Controllers;

use App\Rules\LockedForDemo;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $emailRules = ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user->id)];

        if ($user->isProtectedDemoAccount()) {
            $emailRules[] = new LockedForDemo($user->email);
        }

        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => $emailRules,
        ]);

        $user->fill($data)->save();

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        if ($request->user()->isProtectedDemoAccount()) {
            throw ValidationException::withMessages([
                'password' => str_replace(':attribute', 'password', LockedForDemo::MESSAGE),
            ])->errorBag('password');
        }

        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        // Signs out every other browser session and revokes all API tokens.
        $this->users->changePassword($request->user(), $data['password'], $request->session()->getId());

        return back()->with('success', 'Password changed. Other sessions and API tokens have been signed out.');
    }
}
