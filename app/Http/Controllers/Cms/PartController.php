<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\Content;
use App\Cms\Themes\Parts;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Wygląd → Części: przypisywanie części globalnych do slotów motywu i tworzenie nowych (edycja w edytorze wizualnym). */
class PartController extends Controller
{
    public function __construct(private readonly Parts $parts) {}

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

        $content->delete();

        return back();
    }
}
