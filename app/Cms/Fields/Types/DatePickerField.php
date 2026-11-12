<?php

namespace App\Cms\Fields\Types;

class DatePickerField extends DateField
{
    public function name(): string
    {
        return 'date_picker';
    }

    public function label(): string
    {
        return 'Date Picker';
    }

    protected function storageFormat(): string
    {
        return 'Y-m-d';
    }

    protected function defaults(): array
    {
        return ['display_format' => 'd/m/Y', 'return_format' => 'd/m/Y', 'first_day' => 1];
    }
}
