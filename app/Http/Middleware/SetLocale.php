<?php

namespace App\Http\Middleware;

use App\Cms\I18n\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Ustawia język panelu dla bieżącego żądania i udostępnia słownik widokowi głównemu. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locales::detect($request);

        app()->setLocale($locale);
        View::share('uiLocale', $locale);

        return $next($request);
    }
}
