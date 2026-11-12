<?php

namespace App\Providers;

use App\Cms\Fields\ConditionalLogic;
use App\Cms\Fields\FieldRegistry;
use App\Cms\Fields\FieldValidator;
use App\Cms\Fields\LocationMatcher;
use App\Cms\Fields\Types;
use App\Cms\Roles\RoleRegistry;
use App\Cms\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        Types\RelationshipField::class,
        Types\TaxonomyField::class,
        Types\UserField::class,
        Types\MapField::class,

        Types\RepeaterField::class,
    ];

    public function boot(): void
    {
        // Uprawnienia ról działają jak zwykłe zdolności: $user->can('edit_posts'), middleware can:manage_options.
        Gate::before(fn (User $user, string $ability) => RoleRegistry::grants($user->role, $ability) ? true : null);
    }

    public function register(): void
    {
        $this->app->singleton(ConditionalLogic::class);
        $this->app->singleton(LocationMatcher::class);
        $this->app->singleton(FieldValidator::class);

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
            'users' => ['default_role' => 'subscriber', 'registration_enabled' => false],
            'site' => [
                'ga_id' => '', 'gtm_id' => '', 'pixel_id' => '',
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
