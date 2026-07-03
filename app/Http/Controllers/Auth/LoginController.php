<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function index()
    {
        // dd(Auth::guard('admin')->check(), Auth::guard('admin')->user());

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
            'status' => 1,
        ];

        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {

            $request->session()->regenerate();

            $admin = Auth::guard('admin')->user();

            if ($admin->role && $admin->role->slug === 'super_admin') {
                return redirect()->route('super-admin.dashboard');
            }

            if ($admin->role && $admin->role->slug === 'admin') {
                return redirect()->route('admin.dashboard');
            }

            Auth::guard('admin')->logout();

            return back()->withErrors([
                'email' => 'Role not assigned.',
            ]);
        }

        return back()
            ->withErrors([
                'email' => 'Invalid email or password.',
            ])
            ->onlyInput('email');
    }

    // public function submitLogin(Request $request)
    // {

    //     $credentials = $request->only('email','password');

    //     if (Auth::guard('admin')->attempt($credentials)) {

    //         return redirect()->route('admin.dashboard');
    //     }

    //     return back()->with('error','Invalid Credentials');
    // }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
