<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('EnsureApiAuthenticated middleware check', [
                'has_token' => Session::has('api_token'),
                'session_id' => Session::getId(),
                'url' => $request->fullUrl(),
            ]);
        }

        if (!Session::has('api_token')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
