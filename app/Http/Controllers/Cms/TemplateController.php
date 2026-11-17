<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\Content;
use App\Cms\Themes\Parts;
use App\Cms\Themes\Templates;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Wygląd → Szablony (Theme Builder): szablony treści, archiwów i terminów budowane w edytorze wizualnym. */
class TemplateController extends Controller
{
    public function __construct(private readonly Templates $templates, private readonly Parts $parts) {}

    public function index(): Response
    {
        $type = $this->parts->type();
        $titles = Content::where('content_type_id', $type->id)->orderBy('title')->pluck('title', 'id');

        return Inertia::render('templates/index', [
            'slots' => $this->templates->slots(),
            'parts' => $titles->map(fn ($title, $id) => ['id' => $id, 'title' => $title])->values(),
            'partType' => $type->key,
        ]);
    }

    /** Tworzy szablon z projektu startowego motywu, przypisuje go i otwiera w edytorze. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string']]);
        abort_unless($this->templates->known($data['key']), 404);

        $part = $this->templates->create($data['key']);
        $kind = explode(':', $data['key'])[0];

        return redirect()->route('cms.content.builder', [
            $this->parts->type()->key, $part, 'preset' => 'template-'.($kind === 'single' ? 'single' : 'archive'),
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'content_id' => ['nullable', 'integer', Rule::exists('contents', 'id')->where('content_type_id', $this->parts->type()->id)->whereNull('deleted_at')],
        ]);
        abort_unless($this->templates->known($data['key']), 404);

        $this->templates->assign($data['key'], $data['content_id'] ?? null);

        return back();
    }
}
