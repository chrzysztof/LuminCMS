<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\Content;
use App\Cms\Redirects\NotFoundLog;
use App\Cms\Redirects\Redirect;
use App\Cms\Redirects\RedirectManager;
use App\Cms\Settings\Settings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class RedirectController extends Controller
{
    public function __construct(private readonly RedirectManager $manager, private readonly Settings $settings) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $like = '%'.addcslashes($q, '%_\\').'%';

        $redirects = Redirect::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('source', 'like', $like)->orWhere('target', 'like', $like)->orWhere('note', 'like', $like)))
            ->orderByDesc('id')->paginate(30)->withQueryString()
            ->through(fn (Redirect $r) => [
                'id' => $r->id, 'source' => $r->source, 'target' => $r->target, 'status_code' => $r->status_code,
                'active' => $r->active, 'auto' => $r->auto, 'note' => $r->note, 'hits' => $r->hits,
                'last_hit_at' => $r->last_hit_at?->toIso8601String(),
            ]);

        return Inertia::render('redirects/index', [
            'redirects' => $redirects,
            'q' => $q,
            'logs' => NotFoundLog::orderByDesc('hits')->orderByDesc('last_seen_at')->limit(100)->get()
                ->map(fn (NotFoundLog $l) => ['id' => $l->id, 'path' => $l->path, 'hits' => $l->hits, 'last_referrer' => $l->last_referrer, 'last_seen_at' => $l->last_seen_at?->toIso8601String()]),
            'notFoundPageId' => $this->settings->get('pages', 'not_found_content_id'),
            'pages' => Content::published()->whereHas('type', fn ($t) => $t->where('key', 'page'))->orderBy('title')->get(['id', 'title']),
            'codes' => Redirect::CODES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $this->manager->create($data['source'], $data['target'] ?? null, $data['status_code'], ['note' => $data['note'] ?? null, 'active' => $data['active'] ?? true]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$this->errorField($e) => $e->getMessage()]);
        }

        // Adres przestaje być "brakujący": usuwamy go z monitora 404.
        NotFoundLog::where('path', $this->manager->normalize($data['source']))->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Przekierowanie dodane.']);

        return back();
    }

    public function update(Request $request, Redirect $redirect): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            [$source, $target] = $this->manager->prepare($data['source'], $data['target'] ?? null, $data['status_code'], $redirect->id);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$this->errorField($e) => $e->getMessage()]);
        }

        // Ręczna zmiana oznacza, że przekierowanie przestaje być automatyczne (nie zniknie samo).
        $redirect->update(['source' => $source, 'target' => $target, 'status_code' => $data['status_code'], 'note' => $data['note'] ?? null, 'active' => $data['active'] ?? true, 'auto' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zapisano.']);

        return back();
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        $redirect->delete();

        return back();
    }

    public function toggle(Redirect $redirect): RedirectResponse
    {
        $redirect->update(['active' => ! $redirect->active]);

        return back();
    }

    public function destroyLog(NotFoundLog $log): RedirectResponse
    {
        $log->delete();

        return back();
    }

    public function clearLogs(): RedirectResponse
    {
        NotFoundLog::query()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Wyczyszczono monitor 404.']);

        return back();
    }

    public function updatePages(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'not_found_content_id' => ['nullable', 'integer', Rule::exists('contents', 'id')->whereNull('deleted_at')],
        ]);

        $this->settings->save('pages', ['not_found_content_id' => $data['not_found_content_id'] ?? null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zapisano stronę 404.']);

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'source' => ['required', 'string', 'max:255'],
            'target' => ['nullable', 'string', 'max:2048'],
            'status_code' => ['required', 'integer', Rule::in(Redirect::CODES)],
            'note' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ]);
    }

    /** Komunikaty o celu pokazujemy przy polu celu, resztę przy źródle. */
    private function errorField(InvalidArgumentException $e): string
    {
        return str_contains($e->getMessage(), 'docelow') || str_contains($e->getMessage(), 'pętl') ? 'target' : 'source';
    }
}
