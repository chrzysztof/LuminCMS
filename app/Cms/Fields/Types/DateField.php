<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Baza pól daty i czasu. W bazie wartość jest zawsze w stałym formacie
 * (storageFormat), a return_format decyduje o postaci zwracanej w szablonach.
 */
abstract class DateField extends AbstractFieldType
{
    abstract protected function storageFormat(): string;

    public function category(): string
    {
        return 'advanced';
    }

    public function rules(array $field): array
    {
        return [...$this->baseRules($field), 'string', 'date_format:'.$this->storageFormat()];
    }

    public function format(mixed $value, array $field): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!'.$this->storageFormat(), (string) $value)
                ->locale(app()->getLocale())
                ->translatedFormat($field['return_format']);
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
