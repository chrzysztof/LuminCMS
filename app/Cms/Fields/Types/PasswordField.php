<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Wartość jest szyfrowana w bazie (Crypt) i odszyfrowywana przy odczycie w kodzie,
 * a nie zapisywana jawnym tekstem.
 */
class PasswordField extends AbstractFieldType
{
    public function name(): string
    {
        return 'password';
    }

    public function label(): string
    {
        return 'Password';
    }

    public function category(): string
    {
        return 'basic';
    }

    protected function defaults(): array
    {
        return ['placeholder' => '', 'prepend' => '', 'append' => ''];
    }

    public function rules(array $field): array
    {
        return [...$this->baseRules($field), 'string', 'max:255'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        $value = parent::normalize($value, $field);

        return $value === null ? null : Crypt::encryptString((string) $value);
    }

    public function format(mixed $value, array $field): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            return null;
        }
    }
}
