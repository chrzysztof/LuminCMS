<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentResolver;
use App\Cms\Themes\Customizer;
use App\Cms\Themes\ThemeManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Wygląd → Dostosuj: edycja tokenów motywu (kolory, krój pisma, układ) z podglądem na żywo w ramce. */
class CustomizeController extends Controller
{
    public function __construct(private readonly Customizer $customizer, private readonly ThemeManager $themes, private readonly ContentResolver $contents) {}

    public function edit(): Response
    {
        return Inertia::render('customize/index', [
            'theme' => $this->themes->active()->name(),
            'tokens' => $this->customizer->tokens(),
            'values' => $this->customizer->values(),
            'previewUrl' => $this->previewUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $input = (array) $request->input('values', []);
        $errors = [];

        foreach ($this->customizer->tokens() as $t) {
            if (array_key_exists($t['key'], $input) && $this->customizer->normalize($t, $input[$t['key']]) === null) {
                $errors["values.{$t['key']}"] = __('Niepoprawna wartość.');
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $this->customizer->save($input);

        return back();
    }

    public function reset(): RedirectResponse
    {
        $this->customizer->save([]);

        return back();
    }

    /** Strona do podglądu: strona główna (jeśli jest), inaczej pierwsza opublikowana treść albo archiwum bloga. */
    private function previewUrl(): string
    {
        $home = Content::published()->whereHas('type', fn ($q) => $q->where('key', 'page'))->where('slug', 'home')->exists();

        if ($home) {
            return url('/');
        }

        $first = Content::published()->whereHas('type', fn ($q) => $q->where('key', '!=', 'part'))->with('type', 'parent')->orderBy('id')->first();

        return $first ? $this->contents->urlFor($first) : url('/blog');
    }
}
