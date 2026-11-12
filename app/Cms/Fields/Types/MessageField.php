<?php

namespace App\Cms\Fields\Types;

class MessageField extends LayoutElementField
{
    public function name(): string
    {
        return 'message';
    }

    public function label(): string
    {
        return 'Message';
    }

    protected function defaults(): array
    {
        return ['message' => '', 'new_lines' => 'wpautop', 'esc_html' => false];
    }
}
