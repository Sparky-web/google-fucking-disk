<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class MyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('Authorization') ?? $request->cookie('Authorization');

        $token = explode(' ', $token);
      
        if(!$token || !isset($token[1])) return response()->json([
            'message' => 'Login failed'
        ], 403);

        $token = $token[1];
        
        $user = User::where('token', $token)->first();
        if(!$user) return response()->json([
            'message' => 'Login failed'
        ], 403);

        $request->attributes->set('user', $user);

        return $next($request);
    }
}
