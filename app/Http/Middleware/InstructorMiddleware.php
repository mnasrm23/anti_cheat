<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InstructorMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->role !== 'instructor') {
            return response()->json(['message' => 'Forbidden. Instructors only.'], 403);
        }

        return $next($request);
    }
}
