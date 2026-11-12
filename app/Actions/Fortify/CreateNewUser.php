<?php

namespace App\Actions\Fortify;

use App\Cms\Roles\RoleRegistry;
use App\Cms\Settings\Settings;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        // Pierwsze konto w instalacji to administrator; kolejne dostają domyślną rolę z ustawień.
        $role = User::query()->exists()
            ? app(Settings::class)->get('users', 'default_role', RoleRegistry::SUBSCRIBER)
            : RoleRegistry::ADMINISTRATOR;

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
        $user->role = RoleRegistry::exists($role) ? $role : RoleRegistry::SUBSCRIBER;
        $user->save();

        return $user;
    }
}
