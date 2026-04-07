<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfNotInstalled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->alreadyInstalled() && !$request->is('install*') && !$request->is('_debugbar*')) {
            return redirect()->route('LaravelInstaller::welcome');
        }

        return $next($request);
    }

    /**
     * Check if the application is already installed.
     *
     * @return bool
     */
    protected function alreadyInstalled()
    {
        return file_exists(storage_path('installed'));
    }
}
