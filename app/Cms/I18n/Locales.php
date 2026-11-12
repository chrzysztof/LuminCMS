<?php

namespace App\Cms\I18n;

use Illuminate\Http\Request;

/**
 * Języki panelu administracyjnego. Teksty źródłowe w kodzie są po polsku (panel CMS)
 * lub po angielsku (ekrany logowania i konta); słowniki lang/{język}.json mapują je na dany język.
 */
class Locales
{
    public const DEFAULT = 'pl';

    public const COOKIE = 'lumin_locale';

    /** @var array<string, string> kod => nazwa własna języka */
    public const SUPPORTED = [
        'pl' => 'Polski',
        'en' => 'English',
        'de' => 'Deutsch',
        'es' => 'Español',
        'uk' => 'Українська',
        'cs' => 'Čeština',
        'pt' => 'Português',
    ];

    public static function supported(?string $locale): bool
    {
        return $locale !== null && isset(self::SUPPORTED[$locale]);
    }

    /** Kolejność: język zapisany na koncie, cookie, nagłówek Accept-Language, domyślny. */
    public static function detect(Request $request): string
    {
        $user = $request->user()?->locale;

        if (self::supported($user)) {
            return $user;
        }

        $cookie = $request->cookie(self::COOKIE);

        if (self::supported($cookie)) {
            return $cookie;
        }

        foreach ($request->getLanguages() as $language) {
            $code = strtolower(substr($language, 0, 2));

            if (self::supported($code)) {
                return $code;
            }
        }

        return self::DEFAULT;
    }

    /** Słownik tłumaczeń dla języka (klucz: tekst źródłowy). @return array<string, string> */
    public static function dictionary(string $locale): array
    {
        $file = lang_path("{$locale}.json");

        return is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    }
}
