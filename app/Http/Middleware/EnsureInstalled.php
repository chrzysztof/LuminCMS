<?php

namespace App\Http\Middleware;

use App\Cms\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Przed instalacją całą witrynę zastępuje kreator /install (baza jeszcze nie istnieje, więc sesje i cache idą do plików);
 * po instalacji kreator przestaje istnieć (404).
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installer = $request->is('install', 'install/*');

        if (InstallState::installed()) {
            abort_if($installer, 404);

            return $next($request);
        }

        config(['session.driver' => 'file', 'cache.default' => 'file']);

        if ($installer || $request->is('up')) {
            return $next($request);
        }

        return redirect('/install');
    }
}
