<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class StudentMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->role !== 'student') {
            return response()->json(['message' => 'Forbidden. Students only.'], 403);
        }

        return $next($request);
    }
}
