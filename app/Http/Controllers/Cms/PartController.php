<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\Content;
use App\Cms\Themes\Customizer;
use App\Cms\Themes\Parts;
use App\Cms\Themes\Templates;
use App\Cms\Themes\ThemeManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Wygląd → Części: przypisywanie części globalnych do slotów motywu i tworzenie nowych (edycja w edytorze wizualnym). */
class PartController extends Controller
{
    public function __construct(private readonly Parts $parts, private readonly ThemeManager $themes, private readonly Customizer $customizer) {}

    public function index(): Response
    {
        $type = $this->parts->type();
        $all = Content::where('content_type_id', $type->id)->orderBy('title')->get(['id', 'title', 'updated_at']);
        $slots = collect($this->parts->slots());

        $usage = $slots->mapWithKeys(fn ($s) => [$s['slot'] => $this->parts->assignedId($s['slot'])]);

        return Inertia::render('parts/index', [
            'slots' => $slots->map(fn ($s) => $s + ['content_id' => $usage[$s['slot']]])->values(),
            'parts' => $all->map(fn (Content $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'updated_at' => $c->updated_at->toIso8601String(),
                'slots' => $usage->filter(fn ($id) => $id === $c->id)->keys()->values(),
            ])->values(),
            'partType' => $type->key,
            'presets' => $this->parts->presets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $slots = array_column($this->parts->slots(), 'slot');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slot' => ['nullable', 'string', Rule::in($slots)],
        ]);

        $part = $this->parts->create($data['title'], $data['slot'] ?? null);

        return redirect()->route('cms.content.builder', [$this->parts->type()->key, $part]);
    }

    /** Tworzy część z gotowego projektu motywu, przypisuje ją do slotu i otwiera w edytorze. */
    public function storeFromPreset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slot' => ['required', 'string'],
            'key' => ['required', 'string'],
        ]);

        $part = $this->parts->createFromPreset($data['slot'], $data['key']);
        abort_unless($part, 404);

        return redirect()->route('cms.content.builder', [$this->parts->type()->key, $part, 'preset' => $data['slot'].'-'.$data['key']]);
    }

    /** Podgląd projektu części: strona z arkuszami motywu, osadzana w ramce na liście projektów. */
    public function presetPreview(string $slot, string $key): HttpResponse
    {
        $html = $this->parts->presetPreview($slot, $key);
        abort_unless($html !== null, 404);

        $theme = $this->themes->active();
        $styles = collect([asset('css/lumin-blocks.css'), url('theme-blocks.css'), $theme->asset('theme.css'), $theme->asset('menu.css'), $theme->asset('parts.css')])
            ->map(fn ($href) => '<link rel="stylesheet" href="'.e($href).'">')->implode('');
        $vars = '<style>'.str_replace('</', '<\/', $this->customizer->css()).'body{min-height:0!important;pointer-events:none;overflow:hidden}</style>';

        return response('<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=1200">'.$styles.$vars.'</head><body>'.$html.'</body></html>')
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Security-Policy', "default-src 'self' data:; style-src 'self' 'unsafe-inline'; img-src * data:; script-src 'none'");
    }

    public function assign(Request $request, string $slot): RedirectResponse
    {
        abort_unless(in_array($slot, array_column($this->parts->slots(), 'slot'), true), 404);

        $data = $request->validate([
            'content_id' => ['nullable', 'integer', Rule::exists('contents', 'id')->where('content_type_id', $this->parts->type()->id)->whereNull('deleted_at')],
        ]);

        $this->parts->assign($slot, $data['content_id'] ?? null);

        return back();
    }

    public function destroy(Content $content): RedirectResponse
    {
        abort_unless($content->content_type_id === $this->parts->type()->id, 404);

        foreach ($this->parts->slots() as $s) {
            if ($this->parts->assignedId($s['slot']) === $content->id) {
                $this->parts->assign($s['slot'], null);
            }
        }

        app(Templates::class)->unassignPart($content->id);
        $content->delete();

        return back();
    }
}
