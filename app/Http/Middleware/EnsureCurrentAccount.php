<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->aktif && $user->versi_sesi === $request->session()->get('account_version')) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sesi berakhir. Silakan masuk kembali.');
    }
}
