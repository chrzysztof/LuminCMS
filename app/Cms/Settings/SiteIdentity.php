<?php

namespace App\Cms\Settings;

use App\Cms\Media\Media;

/** Logo i favicon witryny z ustawień SEO ("Tożsamość witryny"). */
class SiteIdentity
{
    /** @var array<string, Media|null> */
    private array $cache = [];

    public function __construct(private readonly Settings $settings) {}

    /** @return array{url: string, width: int, height: int, alt: string}|null */
    public function logo(): ?array
    {
        $media = $this->media('logo_id');

        if (! $media) {
            return null;
        }

        // Wariant "medium" wystarcza (logo w nagłówku ma kilkadziesiąt pikseli wysokości), a zachowuje przezroczystość.
        [$width, $height] = $media->dimensions('medium');

        return [
            'url' => $media->url('medium'),
            'width' => $width,
            'height' => $height,
            'alt' => (string) $this->settings->get('seo', 'site_name', config('app.name')),
        ];
    }

    /** Oryginalny plik (PNG/JPEG/WebP/GIF), żeby przeglądarka dostała dokładnie to, co wgrano. @return array{url: string, type: string}|null */
    public function favicon(): ?array
    {
        $media = $this->media('favicon_id');

        return $media ? ['url' => $media->originalUrl(), 'type' => $media->mime] : null;
    }

    /** Gotowy znacznik <img> logo albo null, gdy logo nie ustawiono. */
    public function logoHtml(): ?string
    {
        $logo = $this->logo();

        if (! $logo) {
            return null;
        }

        return '<img class="lm-site-logo" src="'.e($logo['url']).'" alt="'.e($logo['alt']).'" width="'.(int) $logo['width'].'" height="'.(int) $logo['height'].'">';
    }

    private function media(string $key): ?Media
    {
        $id = $this->settings->get('seo', $key);

        if (! $id) {
            return null;
        }

        $media = $this->cache[$key.$id] ??= Media::find($id);

        return $media && $media->isImage() ? $media : null;
    }
}
