<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Shortcodes\Shortcodes;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/** Lista dostępnych shortcodów (wbudowanych i dodanych przez rozszerzenia). */
class ShortcodeController extends Controller
{
    public function __invoke(Shortcodes $shortcodes): Response
    {
        return Inertia::render('shortcodes/index', [
            'shortcodes' => collect($shortcodes->all())->map(fn (array $doc, string $name) => [
                'name' => $name,
                'usage' => $doc['usage'],
                'description' => $doc['description'] !== '' ? __($doc['description']) : '',
            ])->values(),
        ]);
    }
}
