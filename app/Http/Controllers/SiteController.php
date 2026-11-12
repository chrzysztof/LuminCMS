<?php

namespace App\Http\Controllers;

use App\Cms\Content\Content;
use App\Cms\Content\ContentResolver;
use App\Cms\Content\ContentType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Publiczna strona: renderuje zapisany HTML treści. */
class SiteController extends Controller
{
    public function __construct(private readonly ContentResolver $resolver) {}

    public function show(Request $request): View
    {
        $content = $this->resolver->find($request->path());

        abort_if($content === null, 404);

        return $this->view($content, preview: false);
    }

    /** Podgląd dla zalogowanych, także szkiców. */
    public function preview(ContentType $type, Content $content): View
    {
        abort_unless($content->content_type_id === $type->id, 404);

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
            'html' => (string) $content->html_cache,
            'preview' => $preview,
        ]);
    }
}
