<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'This area is restricted to '.implode(' or ', $roles).'.',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is suspended. Contact an administrator.',
            ], 403);
        }

        $user->markAsSeen();

        return $next($request);
    }
}
