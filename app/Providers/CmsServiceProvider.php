<?php

namespace App\Providers;

use App\Cms\Fields\ConditionalLogic;
use App\Cms\Fields\FieldRegistry;
use App\Cms\Fields\FieldValidator;
use App\Cms\Fields\LocationMatcher;
use App\Cms\Fields\Types;
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
    ];

    public function register(): void
    {
        $this->app->singleton(ConditionalLogic::class);
        $this->app->singleton(LocationMatcher::class);
        $this->app->singleton(FieldValidator::class);

        $this->app->singleton(FieldRegistry::class, function () {
            $registry = new FieldRegistry;

            foreach (self::BUILT_IN_FIELDS as $class) {
                $registry->register(new $class);
            }

            return $registry;
        });
    }
}
