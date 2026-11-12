<?php

namespace App\Http\Controllers;

use App\Cms\Content\ArchiveResolver;
use App\Cms\Content\Content;
use App\Cms\Content\ContentResolver;
use App\Cms\Content\ContentType;
use App\Cms\Redirects\RedirectManager;
use App\Cms\Roles\ContentAccess;
use App\Cms\Seo\SeoResolver;
use App\Cms\Settings\Settings;
use App\Cms\Shortcodes\Shortcodes;
use App\Cms\Themes\ThemeManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Publiczna strona: renderuje zapisany HTML treści. */
class SiteController extends Controller
{
    public function __construct(
        private readonly ContentResolver $resolver,
        private readonly SeoResolver $seo,
        private readonly RedirectManager $redirects,
        private readonly Settings $settings,
        private readonly Shortcodes $shortcodes,
        private readonly ThemeManager $themes,
        private readonly ArchiveResolver $archives,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if ($match = $this->redirects->match($request)) {
            abort_if($match['code'] === 410, 410);

            return redirect()->away($match['target'], $match['code'])->withHeaders(['Cache-Control' => 'no-store']);
        }

        $content = $this->resolver->find($request->path());

        if ($content !== null) {
            return $this->view($content, preview: false);
        }

        $listing = $this->archives->find($request->path());

        abort_if($listing === null, 404);

        return $this->listing($listing, max(1, $request->integer('page', 1)));
    }

    /** Odpowiedź 404: wybrana w ustawieniach strona albo domyślny widok. Zapisuje adres w monitorze 404. */
    public function notFound(Request $request): HttpResponse|View
    {
        rescue(fn () => $this->redirects->logNotFound($request), report: true);

        $id = $this->settings->get('pages', 'not_found_content_id');
        $page = $id ? Content::published()->find($id) : null;

        if ($page) {
            // Podgląd = noindex i brak canonical; kod odpowiedzi zostaje 404.
            return response($this->render($page, preview: true), 404);
        }

        return response()->view('errors.404', [], 404);
    }

    /** Podgląd dla zalogowanych, także szkiców. */
    public function preview(Request $request, ContentType $type, Content $content): View
    {
        abort_unless($content->content_type_id === $type->id, 404);
        abort_unless(app(ContentAccess::class)->canEdit($request->user(), $content->setRelation('type', $type)), 403);

        return $this->view($content, preview: true);
    }

    /** Strona główna: opublikowana strona o adresie "home", a bez niej ekran powitalny. */
    public function home(): View|Response
    {
        $home = Content::published()->whereHas('type', fn ($q) => $q->where('key', 'page'))->where('slug', 'home')->first();

        return $home ? $this->view($home, preview: false) : Inertia::render('welcome');
    }

    private function view(Content $content, bool $preview): View
    {
        return $this->render($content, $preview);
    }

    /**
     * Wybiera szablon: wskazany w treści, potem single-{typ}, single, templates.default.
     * Nazwę z bazy filtrujemy, bo trafia do nazwy widoku.
     */
    private function render(Content $content, bool $preview): View
    {
        $content->loadMissing('type');
        $chosen = preg_match('/^[a-z0-9_-]+$/', (string) $content->template) ? (string) $content->template : 'default';

        $candidates = [
            ...($chosen !== 'default' ? ["templates.{$chosen}"] : []),
            "single-{$content->type->key}",
            'single',
            'templates.default',
        ];

        return $this->themes->view($candidates, [
            'content' => $content,
            'html' => $this->shortcodes->render($content->html_cache, $content),
            'preview' => $preview,
            'seo' => $this->seo->forContent($content, $preview),
        ]);
    }

    /** @param  array<string, mixed>  $listing */
    private function listing(array $listing, int $page): View
    {
        if ($listing['kind'] === 'archive') {
            $type = $listing['type'];
            $heading = $type->label_plural;
            $description = '';
            $url = $this->archives->urlForArchive($type);
            $items = $this->archives->itemsOfType($type);
            $candidates = ["archive-{$type->key}", 'archive'];
            $extra = ['type' => $type];
        } else {
            $term = $listing['term'];
            $heading = $term->name;
            $description = (string) $term->description;
            $url = $this->archives->urlForTerm($term);
            $items = $this->archives->itemsOfTerm($term);
            $candidates = ["taxonomy-{$listing['taxonomy']->key}", 'taxonomy', 'archive'];
            $extra = ['term' => $term, 'taxonomy' => $listing['taxonomy']];
        }

        abort_if($page > 1 && $items->isEmpty(), 404);

        return $this->themes->view($candidates, [
            'heading' => $heading,
            'description' => $description,
            'items' => $items,
            'paths' => app(ContentResolver::class),
            'preview' => false,
            'seo' => $this->seo->forListing($heading, $description, $url, $page),
            ...$extra,
        ]);
    }
}
