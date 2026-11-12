<?php

namespace App\Cms\Install;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Menus\Menu;
use App\Cms\Menus\MenuItem;
use App\Models\User;

/** Opcjonalna treść startowa: strona główna, strona kontaktowa, przykładowy wpis i menu główne. Teksty w języku administratora. */
class StarterContent
{
    public function create(User $author, string $siteName): void
    {
        $previous = app()->getLocale();
        app()->setLocale($author->locale ?: $previous);

        try {
            $page = ContentType::where('key', 'page')->value('id');
            $post = ContentType::where('key', 'post')->value('id');

            $home = $this->make($page, $author, __('Home'), 'home', '<h1>'.e(__('Welcome to :site', ['site' => $siteName])).'</h1><p>'.e(__('This is your new site. Edit this page in the panel, or build it visually with the page builder.')).'</p>', 1);
            $about = $this->make($page, $author, __('About'), 'about', '<p>'.e(__('Tell visitors who you are and what this site is about.')).'</p>', 2);
            $this->make($post, $author, __('Hello, world!'), 'hello-world', '<p>'.e(__('This is a sample post. Edit or delete it, then publish your own.')).'</p>', 0);

            $menu = Menu::firstOrCreate(['slug' => 'main'], ['name' => __('Main menu'), 'location' => 'primary']);

            if (! $menu->items()->exists()) {
                foreach ([$home, $about] as $i => $content) {
                    MenuItem::create(['menu_id' => $menu->id, 'position' => $i, 'type' => 'content', 'label' => $content->title, 'content_id' => $content->id]);
                }
            }
        } finally {
            app()->setLocale($previous);
        }
    }

    private function make(int $typeId, User $author, string $title, string $slug, string $html, int $order): Content
    {
        return Content::firstOrCreate(
            ['content_type_id' => $typeId, 'slug' => $slug],
            ['title' => $title, 'status' => 'published', 'published_at' => now()->subMinute(), 'author_id' => $author->id, 'html_cache' => $html, 'fields' => [], 'menu_order' => $order],
        );
    }
}
