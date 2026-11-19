<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Fonts\FontException;
use App\Cms\Fonts\FontLibrary;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Wygląd → Czcionki: import czcionek z Google Fonts (pliki trafiają na serwer). */
class FontController extends Controller
{
    public function __construct(private readonly FontLibrary $fonts) {}

    public function index(): Response
    {
        return Inertia::render('fonts/index', [
            'fonts' => array_map(fn ($f) => ['family' => $f['family'], 'slug' => $f['slug'], 'weights' => $f['weights'], 'italic' => $f['italic']], $this->fonts->all()),
            'css' => $this->fonts->css(),
            'weights' => FontLibrary::WEIGHTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'family' => ['required', 'string', 'max:60'],
            'weights' => ['array'],
            'weights.*' => ['integer'],
            'italic' => ['boolean'],
        ]);

        try {
            $this->fonts->import($data['family'], $data['weights'] ?? [], (bool) ($data['italic'] ?? false));
        } catch (FontException $e) {
            return back()->withErrors(['family' => $e->getMessage()]);
        }

        return back();
    }

    public function destroy(string $slug): RedirectResponse
    {
        $this->fonts->remove($slug);

        return back();
    }
}
