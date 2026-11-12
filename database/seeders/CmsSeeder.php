<?php

namespace Database\Seeders;

use App\Cms\Content\ContentType;
use App\Cms\Media\MediaSize;
use App\Cms\Taxonomy\Taxonomy;
use Illuminate\Database\Seeder;

/** Domyślne typy treści (strony, wpisy) i taksonomie (kategorie, tagi). Można uruchamiać wielokrotnie. */
class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $page = ContentType::updateOrCreate(['key' => 'page'], [
            'label' => 'Strona',
            'label_plural' => 'Strony',
            'icon' => 'file-text',
            'hierarchical' => true,
            'supports' => ['title', 'editor', 'builder', 'thumbnail', 'seo', 'page_attributes'],
            'url_prefix' => null,
            'capability_type' => 'page',
            'is_system' => true,
        ]);

        $post = ContentType::updateOrCreate(['key' => 'post'], [
            'label' => 'Wpis',
            'label_plural' => 'Wpisy',
            'icon' => 'newspaper',
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'builder', 'excerpt', 'thumbnail', 'seo', 'author'],
            'url_prefix' => 'blog',
            'capability_type' => 'post',
            'is_system' => true,
        ]);

        $category = Taxonomy::updateOrCreate(['key' => 'category'], [
            'label' => 'Kategoria',
            'label_plural' => 'Kategorie',
            'hierarchical' => true,
            'is_system' => true,
        ]);

        $tag = Taxonomy::updateOrCreate(['key' => 'tag'], [
            'label' => 'Tag',
            'label_plural' => 'Tagi',
            'hierarchical' => false,
            'is_system' => true,
        ]);

        $category->contentTypes()->syncWithoutDetaching([$post->id]);
        $tag->contentTypes()->syncWithoutDetaching([$post->id]);

        // Domyślne rozmiary obrazów (wariant "full" powstaje zawsze).
        foreach ([
            ['thumbnail', 'Miniatura', 150, 150, true],
            ['medium', 'Średni', 768, 0, false],
            ['large', 'Duży', 1536, 0, false],
        ] as [$name, $label, $width, $height, $crop]) {
            MediaSize::updateOrCreate(['name' => $name], [
                'label' => $label, 'width' => $width, 'height' => $height,
                'crop' => $crop, 'active' => true, 'is_system' => true,
            ]);
        }
    }
}
