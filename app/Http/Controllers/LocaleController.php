<?php

namespace App\Http\Controllers;

use App\Cms\I18n\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /** Zmiana języka panelu: zapis na koncie (jeśli zalogowany) i w cookie dla ekranów logowania. */
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(Locales::SUPPORTED))]]);

        $request->user()?->forceFill(['locale' => $data['locale']])->save();

        return back()->withCookie(Cookie::make(Locales::COOKIE, $data['locale'], 60 * 24 * 365));
    }
}
