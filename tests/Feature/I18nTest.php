<?php

namespace Tests\Feature;

use App\Cms\I18n\Locales;
use App\Cms\Roles\RoleRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class I18nTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_languages(): void
    {
        $this->assertSame(['pl', 'en', 'de', 'es', 'uk', 'cs'], array_keys(Locales::SUPPORTED));
    }

    public function test_dictionaries_are_complete_and_keep_placeholders(): void
    {
        $dict = fn (string $l) => Locales::dictionary($l);
        $foreign = ['de', 'es', 'uk', 'cs'];

        // Teksty źródłowe są po polsku (en ma tłumaczenia) albo po angielsku (pl ma tłumaczenia).
        $all = array_keys($dict('de'));
        sort($all);

        $union = array_unique([...array_keys($dict('pl')), ...array_keys($dict('en'))]);
        sort($union);
        $this->assertSame($union, $all, 'pl + en powinny razem pokrywać wszystkie klucze');

        foreach ($foreign as $l) {
            $keys = array_keys($dict($l));
            sort($keys);
            $this->assertSame($all, $keys, "Słownik {$l} ma inny zestaw kluczy");
        }

        foreach (['pl', 'en', ...$foreign] as $l) {
            foreach ($dict($l) as $key => $value) {
                $this->assertNotSame('', trim($value), "Pusty przekład {$l}: {$key}");
                preg_match_all('/\{\w+\}|:\w+/', $key, $m);

                foreach ($m[0] as $placeholder) {
                    $this->assertStringContainsString($placeholder, $value, "Brak {$placeholder} w {$l}: {$key}");
                }
            }
        }
    }

    public function test_detection_order(): void
    {
        $this->get('/login')->assertSee('<html lang="pl"', false);

        $this->get('/login', ['Accept-Language' => 'de-DE,de;q=0.9'])->assertSee('<html lang="de"', false);
        $this->get('/login', ['Accept-Language' => 'fr-FR,ja;q=0.8'])->assertSee('<html lang="pl"', false);
        $this->withCookie(Locales::COOKIE, 'uk')->get('/login', ['Accept-Language' => 'de'])->assertSee('<html lang="uk"', false);
    }

    public function test_dictionary_is_embedded_for_the_frontend(): void
    {
        $this->get('/login', ['Accept-Language' => 'cs'])
            ->assertSee('window.__luminI18n', false)
            ->assertSee('Zrušit', false);
    }

    public function test_user_can_change_language_and_it_is_remembered(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/locale', ['locale' => 'es'])->assertRedirect()->assertCookie(Locales::COOKIE);
        $this->assertSame('es', $user->refresh()->locale);

        $this->get('/dashboard', ['Accept-Language' => 'de'])->assertSee('<html lang="es"', false);
    }

    public function test_guest_language_choice_goes_to_cookie(): void
    {
        $this->post('/locale', ['locale' => 'en'])->assertCookie(Locales::COOKIE);
        $this->post('/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_server_messages_are_translated(): void
    {
        app()->setLocale('de');
        $this->assertSame('Gespeichert.', __('Zapisano.'));
        $this->assertSame('Redakteur', RoleRegistry::label('editor'));

        app()->setLocale('en');
        $this->assertSame('Saved.', __('Zapisano.'));

        app()->setLocale('pl');
        $this->assertSame('Zapisano.', __('Zapisano.'));
        $this->assertSame('Redaktor', RoleRegistry::label('editor'));
    }

    public function test_framework_validation_messages_exist_for_all_languages(): void
    {
        foreach (array_keys(Locales::SUPPORTED) as $l) {
            app()->setLocale($l);
            $this->assertNotSame('validation.required', __('validation.required'), $l);
            $this->assertNotSame('auth.failed', __('auth.failed'), $l);
        }
    }
}
