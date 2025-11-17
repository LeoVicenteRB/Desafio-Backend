<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Para API, sempre retornar null (vai retornar JSON 401)
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // Para rotas web, retornar null também (vai usar o Handler)
        return null;
    }
}

