<?php

namespace App\Http\Controllers;

use App\Support\AccountInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['bail', 'required', 'string', 'max:40'],
            'password' => ['bail', 'required', 'string', 'regex:/\A[^\x00]{1,72}\z/'],
        ], [
            'password.regex' => 'Kata sandi tidak sesuai format.',
        ], ['username' => 'nama pengguna', 'password' => 'kata sandi']);
        $username = AccountInput::username($data['username']);
        $key = 'login:'.hash('sha256', $username.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'username' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        if (! Auth::attempt(['username' => $username, 'password' => $data['password'], 'aktif' => true])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['username' => 'Nama pengguna atau kata sandi tidak sesuai.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('account_version', $request->user()->versi_sesi);

        return redirect()->route('master.index', 'produk');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
