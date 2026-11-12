<?php

namespace App\Http\Controllers;

use App\Cms\Themes\ThemeManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serwuje pliki z themes/{motyw}/assets (css, js, obrazy, fonty), bez wychodzenia poza ten katalog. */
class ThemeAssetController extends Controller
{
    private const TYPES = [
        'css' => 'text/css', 'js' => 'text/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
    ];

    public function __invoke(ThemeManager $themes, string $theme, string $path): BinaryFileResponse
    {
        abort_unless($themes->has($theme), 404);

        $root = realpath($themes->all()[$theme]->assetsPath());
        $file = $root ? realpath($root.'/'.$path) : false;
        $type = self::TYPES[strtolower(pathinfo((string) $file, PATHINFO_EXTENSION))] ?? null;

        abort_unless($file && $type && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        return response()->file($file, ['Content-Type' => $type, 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
