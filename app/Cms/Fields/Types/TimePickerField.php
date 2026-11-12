<?php

namespace App\Cms\Fields\Types;

class TimePickerField extends DateField
{
    public function name(): string
    {
        return 'time_picker';
    }

    public function label(): string
    {
        return 'Time Picker';
    }

    protected function storageFormat(): string
    {
        return 'H:i:s';
    }

    protected function defaults(): array
    {
        return ['display_format' => 'g:i a', 'return_format' => 'g:i a', 'default_value' => ''];
    }
}
