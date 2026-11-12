<?php

namespace App\Cms\Content;

/** Znajduje opublikowaną treść po ścieżce URL (strony hierarchiczne, typy z prefiksem adresu). */
class ContentResolver
{
    public function find(string $path): ?Content
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn ($s) => $s !== ''));

        if ($segments === []) {
            return null;
        }

        foreach (ContentType::all() as $type) {
            $prefix = $type->url_prefix;
            $slugs = $segments;

            if ($prefix) {
                if ($slugs[0] !== $prefix || count($slugs) < 2) {
                    continue;
                }
                array_shift($slugs);
            }

            if (($content = $this->findInType($type, $slugs)) !== null) {
                return $content;
            }
        }

        return null;
    }

    /** Ścieżka publiczna treści (np. "oferta/korepetycje" albo "blog/wpis"). */
    public function pathFor(Content $content): string
    {
        $slugs = [$content->slug];
        $parent = $content->parent;

        for ($i = 0; $parent && $i < 20; $i++) {
            array_unshift($slugs, $parent->slug);
            $parent = $parent->parent;
        }

        $prefix = $content->type->url_prefix;

        return ($prefix ? $prefix.'/' : '').implode('/', $slugs);
    }

    /** @param  list<string>  $slugs */
    private function findInType(ContentType $type, array $slugs): ?Content
    {
        if (! $type->hierarchical && count($slugs) !== 1) {
            return null;
        }

        $content = Content::published()->where('content_type_id', $type->id)->where('slug', end($slugs))->first();

        // Zgodność całej ścieżki rodziców ze slugami z adresu.
        if ($content && $this->pathFor($content) !== (($type->url_prefix ? $type->url_prefix.'/' : '').implode('/', $slugs))) {
            return null;
        }

        return $content;
    }
}
