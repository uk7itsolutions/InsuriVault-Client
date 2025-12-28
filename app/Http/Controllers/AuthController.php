<?php

namespace App\Http\Controllers;

use App\Services\InsuriVaultApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    protected $apiService;

    public function __construct(InsuriVaultApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function showLogin()
    {
        if (Session::has('api_token')) {
            return redirect()->route('documents.index');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $token = $this->apiService->getToken($credentials['email'], $credentials['password']);

        if ($token) {
            Session::put('api_token', $token);
            Session::put('user_email', $credentials['email']);
            return redirect()->route('documents.index');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout()
    {
        Session::forget(['api_token', 'user_email']);
        return redirect()->route('login');
    }
}
