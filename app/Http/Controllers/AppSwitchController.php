<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AppSwitch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppSwitchController extends Controller
{
    /** "Switch to …" button: hand the logged-in user to the sister app. */
    public function go(Request $request)
    {
        $user = $request->user();

        if (!AppSwitch::allowedFor($user)) {
            return $this->noAccess(config('app.switch.label'));
        }

        return redirect()->away(AppSwitch::targetBase() . '/switch/receive?token=' . urlencode(AppSwitch::makeToken($user)));
    }

    /** Arriving from the sister app with a signed token. */
    public function receive(Request $request)
    {
        $email = AppSwitch::verify((string) $request->query('token', ''));
        $user = $email ? User::whereRaw('LOWER(email) = ?', [$email])->first() : null;

        if (!$user || !$user->is_active || !$user->role) {
            return $this->noAccess(config('app.switch.this_label'));
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route($user->landingRouteName());
    }

    private function noAccess(?string $siteLabel)
    {
        return response()->view('switch-no-access', ['site' => $siteLabel ?: 'this site'], 403);
    }
}
