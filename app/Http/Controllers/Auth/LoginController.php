<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            DB::table('users')
                ->where('id', $request->user()?->id)
                ->update(['last_seen_at' => now(), 'updated_at' => now()]);

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'username' => 'Невалидни данни за вход. Опитайте отново.',
        ])->onlyInput('username');
    }

    public function logout(Request $request): RedirectResponse
    {
        $userId = $request->user()?->id;
        if ($userId !== null) {
            DB::table('users')->where('id', $userId)->update(['last_seen_at' => null, 'updated_at' => now()]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
