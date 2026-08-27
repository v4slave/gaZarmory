<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->headers->has('Accept-Language')
            ? ($request->getPreferredLanguage(['ru', 'en']) ?? 'ru')
            : 'ru';
        app()->setLocale($locale);

        return $next($request);
    }
}
