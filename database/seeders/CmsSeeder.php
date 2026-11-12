<?php

namespace Database\Seeders;

use App\Cms\Content\ContentType;
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
            'is_system' => true,
        ]);

        $post = ContentType::updateOrCreate(['key' => 'post'], [
            'label' => 'Wpis',
            'label_plural' => 'Wpisy',
            'icon' => 'newspaper',
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'builder', 'excerpt', 'thumbnail', 'seo', 'author'],
            'url_prefix' => 'blog',
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
    }
}
