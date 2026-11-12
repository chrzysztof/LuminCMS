<?php

namespace App\Cms\Fields\Types;

class DateTimePickerField extends DateField
{
    public function name(): string
    {
        return 'date_time_picker';
    }

    public function label(): string
    {
        return 'Date Time Picker';
    }

    protected function storageFormat(): string
    {
        return 'Y-m-d H:i:s';
    }

    protected function defaults(): array
    {
        return ['display_format' => 'd/m/Y g:i a', 'return_format' => 'd/m/Y g:i a', 'first_day' => 1];
    }
}
