<?php

namespace App\Providers;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Fields\ConditionalLogic;
use App\Cms\Fields\FieldRegistry;
use App\Cms\Fields\FieldValidator;
use App\Cms\Fields\LocationMatcher;
use App\Cms\Fields\Types;
use App\Cms\Install\EnvFile;
use App\Cms\Menus\MenuRenderer;
use App\Cms\Roles\RoleRegistry;
use App\Cms\Settings\Settings;
use App\Cms\Shortcodes\BuiltInShortcodes;
use App\Cms\Shortcodes\Shortcodes;
use App\Cms\Themes\Customizer;
use App\Cms\Themes\Parts;
use App\Cms\Themes\ThemeInstaller;
use App\Cms\Themes\ThemeManager;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class CmsServiceProvider extends ServiceProvider
{
    /** Typy pól dostępne od razu. Rozszerzenia dodają własne przez FieldRegistry::register(). */
    private const BUILT_IN_FIELDS = [
        // Podstawowe
        Types\TextField::class,
        Types\TextareaField::class,
        Types\NumberField::class,
        Types\RangeField::class,
        Types\EmailField::class,
        Types\UrlField::class,
        Types\PasswordField::class,
        // Wybór
        Types\SelectField::class,
        Types\CheckboxField::class,
        Types\RadioField::class,
        Types\ButtonGroupField::class,
        Types\TrueFalseField::class,
        // Zaawansowane
        Types\ColorPickerField::class,
        Types\DatePickerField::class,
        Types\DateTimePickerField::class,
        Types\TimePickerField::class,
        // Układ
        Types\MessageField::class,
        Types\AccordionField::class,
        Types\TabField::class,
        // Treść (media)
        Types\ImageField::class,
        Types\FileField::class,
        Types\GalleryField::class,

        Types\WysiwygField::class,
        Types\OEmbedField::class,
        Types\PostObjectField::class,
        Types\PageLinkField::class,
        Types\LinkField::class,
        Types\RelationshipField::class,
        Types\TaxonomyField::class,
        Types\UserField::class,
        Types\MapField::class,

        Types\RepeaterField::class,
        Types\GroupField::class,
        Types\FlexibleContentField::class,
        Types\CloneField::class,
    ];

    public function boot(): void
    {
        // Uprawnienia ról działają jak zwykłe zdolności: $user->can('edit_posts'), middleware can:manage_options.
        Gate::before(fn (User $user, string $ability) => RoleRegistry::grants($user->role, $ability) ? true : null);

        $themes = $this->app->make(ThemeManager::class);
        $themes->register();
        View::composer('theme::*', fn ($view) => $view->with('theme', $themes->active()));
    }

    public function register(): void
    {
        $this->app->singleton(Shortcodes::class, function () {
            $shortcodes = new Shortcodes;
            BuiltInShortcodes::register($shortcodes);

            return $shortcodes;
        });
        $this->app->singleton(ThemeManager::class, fn ($app) => new ThemeManager($app->make(Settings::class), base_path('themes')));
        $this->app->singleton(ThemeInstaller::class, fn ($app) => new ThemeInstaller($app->make(ThemeManager::class), base_path('themes')));
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(Parts::class);
        $this->app->singleton(Customizer::class);
        $this->app->singleton(MenuRenderer::class);
        $this->app->singleton(ConditionalLogic::class);
        $this->app->singleton(LocationMatcher::class);
        $this->app->singleton(FieldValidator::class);

        $this->app->bind(EnvFile::class, fn () => new EnvFile(base_path('.env')));
        $this->app->singleton(Settings::class, fn () => new Settings([
            'seo' => [
                'site_name' => config('app.name'),
                'tagline' => '',
                'description' => '',
                'title_template' => '%title% %sep% %site%',
                'separator' => '–',
                'default_og_image_id' => null,
                'twitter_handle' => '',
                'noindex_site' => false,
            ],
            'pages' => ['not_found_content_id' => null],
            'theme' => ['active' => ThemeManager::DEFAULT],
            'theme_parts' => ['slots' => []],
            'theme_customizer' => ['values' => []],
            'users' => ['default_role' => 'subscriber', 'registration_enabled' => false],
            'site' => [
                'ga_id' => '', 'gtm_id' => '', 'pixel_id' => '', 'ga_property_id' => '', 'ga_service_account' => '',
                'consent_required' => true, 'privacy_url' => '', 'track_logged_in' => false,
                'facebook' => '', 'instagram' => '', 'x' => '', 'youtube' => '', 'linkedin' => '', 'tiktok' => '',
                'custom_head' => '', 'custom_body_end' => '',
            ],
        ]));

        $this->app->singleton(FieldRegistry::class, function () {
            $registry = new FieldRegistry;

            foreach (self::BUILT_IN_FIELDS as $class) {
                $registry->register(new $class);
            }

            return $registry;
        });
    }
}
