<?php

namespace App\Http\Controllers;

use App\Cms\Content\Content;
use App\Cms\Seo\SeoResolver;
use Illuminate\Http\Response;

/** sitemap.xml i robots.txt generowane z aktualnych treści i ustawień SEO. */
class SitemapController extends Controller
{
    private const MAX_URLS = 50000;

    public function __construct(private readonly SeoResolver $seo) {}

    public function sitemap(): Response
    {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        $count = 0;

        if (! $this->seo->site()['noindex_site']) {
            Content::published()->with('type', 'parent')->orderBy('id')->lazy(500)->each(function (Content $content) use (&$xml, &$count) {
                if ($count >= self::MAX_URLS || ! $this->seo->indexable($content) || ! empty($content->seo['canonical'])) {
                    return;
                }

                $count++;
                $xml[] = '<url><loc>'.e($this->seo->urlFor($content)).'</loc><lastmod>'.$content->updated_at->toIso8601String().'</lastmod></url>';
            });
        }

        $xml[] = '</urlset>';

        return response(implode("\n", $xml), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        if ($this->seo->site()['noindex_site']) {
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Disallow: /cms';
            $lines[] = 'Disallow: /login';
            $lines[] = 'Disallow: /dashboard';
            $lines[] = '';
            $lines[] = 'Sitemap: '.url('/sitemap.xml');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
