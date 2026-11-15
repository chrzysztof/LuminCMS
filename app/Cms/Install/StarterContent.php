<?php

namespace App\Cms\Install;

use App\Cms\Builder\BuilderRenderer;
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

            $home = $this->makeHome($page, $author, $siteName);
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

    /** Strona główna jako układ edytora wizualnego: hero, trzy karty i wezwanie do działania. */
    private function makeHome(int $typeId, User $author, string $siteName): Content
    {
        $project = $this->homeProject($siteName);

        return Content::firstOrCreate(
            ['content_type_id' => $typeId, 'slug' => 'home'],
            ['title' => __('Home'), 'status' => 'published', 'published_at' => now()->subMinute(), 'author_id' => $author->id, 'builder_data' => $project, 'html_cache' => app(BuilderRenderer::class)->render($project), 'fields' => [], 'menu_order' => 1],
        );
    }

    /** @return array<string, mixed> */
    private function homeProject(string $siteName): array
    {
        $text = fn (string $t) => ['type' => 'textnode', 'content' => $t];
        $heading = fn (string $level, string $t, bool $center = false) => ['tagName' => $level, 'type' => 'lm-heading', 'level' => $level, 'align' => $center ? 'center' : null, 'classes' => $center ? ['lm-center'] : [], 'components' => [$text($t)]];
        $para = fn (string $t, bool $center = false) => ['tagName' => 'p', 'type' => 'lm-paragraph', 'align' => $center ? 'center' : null, 'classes' => $center ? ['lm-center'] : [], 'components' => [$text($t)]];
        $button = fn (string $t, string $href, bool $secondary = false) => ['tagName' => 'a', 'type' => 'lm-button', 'classes' => $secondary ? ['lm-btn', 'lm-btn-secondary'] : ['lm-btn'], 'attributes' => ['href' => $href], 'variant' => $secondary ? 'secondary' : null, 'components' => [$text($t)]];
        $container = fn (array $children) => ['classes' => ['lm-container'], 'components' => $children];
        $section = fn (array $classes, array $children, ?string $bg = null) => ['tagName' => 'section', 'type' => 'lm-section', 'classes' => ['lm-section', ...$classes], 'bg' => $bg, 'pad' => null, 'align' => null, 'components' => [$container($children)]];
        $card = fn (string $title, string $body) => ['type' => 'lm-card', 'classes' => ['lm-card'], 'components' => [$heading('h3', $title), $para($body)]];

        $sections = [
            $section(['lm-hero', 'lm-bg-dark', 'lm-center'], [
                $heading('h1', __('Welcome to :site', ['site' => $siteName]), true),
                $para(__('A fast, modern site built with Lumin CMS. Edit this page in the visual builder and make it yours.'), true),
                ['classes' => ['lm-actions'], 'components' => [$button(__('Log in to the panel'), url('/dashboard')), $button(__('About'), url('/about'), true)]],
            ], 'dark'),
            $section([], [
                $heading('h2', __('Everything you need, out of the box'), true),
                $para(__('The essentials ship with the CMS, so there is no hunting for plugins.'), true),
                ['type' => 'lm-grid', 'classes' => ['lm-grid', 'lm-cols-3'], 'cols' => '3', 'valign' => null, 'components' => [
                    $card(__('Visual page builder'), __('Arrange sections and blocks, see the result instantly and publish when you are ready.')),
                    $card(__('Custom fields'), __('Model any content, from offers to team members, with fields you define yourself.')),
                    $card(__('Built-in SEO'), __('Meta tags, a sitemap and robots.txt work from the very first day.')),
                ]],
            ]),
            $section(['lm-bg-muted', 'lm-center'], [
                $heading('h2', __('Ready to make it yours?'), true),
                $para(__('Log in to edit this page, add posts and change the look of your site.'), true),
                $button(__('Log in to the panel'), url('/dashboard')),
            ], 'muted'),
        ];

        return ['dataSources' => [], 'assets' => [], 'styles' => [], 'symbols' => [], 'pages' => [['frames' => [['component' => ['type' => 'wrapper', 'components' => $sections]]]]]];
    }

    private function make(int $typeId, User $author, string $title, string $slug, string $html, int $order): Content
    {
        return Content::firstOrCreate(
            ['content_type_id' => $typeId, 'slug' => $slug],
            ['title' => $title, 'status' => 'published', 'published_at' => now()->subMinute(), 'author_id' => $author->id, 'html_cache' => $html, 'fields' => [], 'menu_order' => $order],
        );
    }
}
