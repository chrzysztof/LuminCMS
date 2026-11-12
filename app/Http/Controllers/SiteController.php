<?php

namespace App\Http\Controllers;

use App\Cms\Content\Content;
use App\Cms\Content\ContentResolver;
use App\Cms\Content\ContentType;
use App\Cms\Redirects\RedirectManager;
use App\Cms\Roles\ContentAccess;
use App\Cms\Seo\SeoResolver;
use App\Cms\Settings\Settings;
use App\Cms\Shortcodes\Shortcodes;
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
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if ($match = $this->redirects->match($request)) {
            abort_if($match['code'] === 410, 410);

            return redirect()->away($match['target'], $match['code'])->withHeaders(['Cache-Control' => 'no-store']);
        }

        $content = $this->resolver->find($request->path());

        abort_if($content === null, 404);

        return $this->view($content, preview: false);
    }

    /** Odpowiedź 404: wybrana w ustawieniach strona albo domyślny widok. Zapisuje adres w monitorze 404. */
    public function notFound(Request $request): HttpResponse|View
    {
        rescue(fn () => $this->redirects->logNotFound($request), report: true);

        $id = $this->settings->get('pages', 'not_found_content_id');
        $page = $id ? Content::published()->find($id) : null;

        if ($page) {
            // Podgląd = noindex i brak canonical; kod odpowiedzi zostaje 404.
            return response()->view('site.show', [
                'content' => $page,
                'html' => $this->shortcodes->render($page->html_cache, $page),
                'preview' => true,
                'seo' => $this->seo->forContent($page, preview: true),
            ], 404);
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
        return view('site.show', [
            'content' => $content,
            'html' => $this->shortcodes->render($content->html_cache, $content),
            'preview' => $preview,
            'seo' => $this->seo->forContent($content, $preview),
        ]);
    }
}
