<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials + ['active' => true])) {
            $audit->write('anonim', 'login.failed', 'User', null, 'email='.$credentials['email']);

            return back()->withErrors(['email' => 'Surel atau kata sandi tidak cocok.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $audit->write($request->user()->auditActor(), 'login', 'User', $request->user()->id);

        return redirect()->intended('/');
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        if ($user = $request->user()) {
            $audit->write($user->auditActor(), 'logout', 'User', $user->id);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Halaman awal mengikuti kewenangan peran.
     */
    public function home(Request $request): RedirectResponse
    {
        $user = $request->user();

        return match (true) {
            $user->can('case.view') => redirect()->route('board'),
            $user->can('report.view') => redirect()->route('reports.stage-durations'),
            $user->can('indicators.manage') => redirect()->route('indicators.index'),
            $user->can('audit.view') => redirect()->route('audit.index'),
            default => abort(403),
        };
    }
}
