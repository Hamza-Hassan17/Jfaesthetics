<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Deactivated accounts (is_active = false) must not be able to log in
     * at all — folding this into the credentials query means Laravel's
     * normal "these credentials do not match" response covers it too,
     * without leaking whether the account exists but is disabled.
     */
    protected function credentials(Request $request)
    {
        return array_merge($request->only($this->username(), 'password'), ['is_active' => true]);
    }

    /**
     * Not every role has Dashboard access, so send each user to the first
     * module their role can actually view instead of hardcoding
     * /admin/dashboard for everyone. See User::landingRouteName().
     */
    public function redirectTo()
    {
        $user = auth()->user();

        if (!$user || !$user->role) {
            return '/admin/dashboard';
        }

        return route($user->landingRouteName());
    }
}
