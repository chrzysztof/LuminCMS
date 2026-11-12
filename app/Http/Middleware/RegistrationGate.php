<?php

namespace App\Http\Middleware;

use App\Cms\Settings\Settings;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publiczna rejestracja działa tylko na świeżej instalacji (pierwsze konto zostaje administratorem)
 * albo gdy administrator włączył ją w Użytkownicy → Role i ustawienia.
 */
class RegistrationGate
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('register') && ! $this->open()) {
            abort(404);
        }

        return $next($request);
    }

    private function open(): bool
    {
        return ! User::query()->exists() || (bool) $this->settings->get('users', 'registration_enabled', false);
    }
}
