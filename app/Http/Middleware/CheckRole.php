<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!auth()->check()) {
            \Log::info('CheckRole: Not logged in');
            abort(403, 'Unauthorized access - Not logged in');
        }
        if (auth()->user()->role !== $role) {
            \Log::info("CheckRole: Role mismatch. User role: " . auth()->user()->role . ", Expected: " . $role);
            abort(403, 'Unauthorized access');
        }
        return $next($request);
    }
   

}
