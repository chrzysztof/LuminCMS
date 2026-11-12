<?php

namespace Tests\Feature\Cms;

use App\Cms\Fields\FieldRegistry;
use App\Cms\Fields\FieldValidator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class FieldValidationTest extends TestCase
{
    private FieldValidator $validator;

    private FieldRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(FieldValidator::class);
        $this->registry = app(FieldRegistry::class);
    }

    /** Zwraca błędy walidacji albo null, gdy dane są poprawne. */
    private function errors(array $fields, array $data): ?array
    {
        try {
            $this->validator->validate($fields, $data);

            return null;
        } catch (ValidationException $e) {
            return $e->errors();
        }
    }

    public function test_registry_has_all_built_in_types(): void
    {
        $this->assertCount(22, $this->registry->all());
    }

    public function test_unknown_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->registry->get('nie_ma');
    }

    public function test_text_and_number_rules(): void
    {
        $fields = [
            ['type' => 'text', 'name' => 'tytul', 'label' => 'Tytuł', 'required' => true, 'maxlength' => 5],
            ['type' => 'number', 'name' => 'cena', 'min' => 10, 'max' => 100],
        ];

        $this->assertArrayHasKey('tytul', $this->errors($fields, ['tytul' => '', 'cena' => 20]));
        $this->assertArrayHasKey('tytul', $this->errors($fields, ['tytul' => 'dłuższy', 'cena' => 20]));
        $this->assertArrayHasKey('cena', $this->errors($fields, ['tytul' => 'ok', 'cena' => 5]));
        $this->assertSame(42, $this->validator->validate($fields, ['tytul' => 'ok', 'cena' => '42'])['cena']);
        $this->assertSame(42.5, $this->validator->validate($fields, ['tytul' => 'ok', 'cena' => '42.5'])['cena']);
    }

    public function test_choice_fields_reject_values_outside_choices(): void
    {
        $choices = ['a' => 'Alfa', 'b' => 'Beta'];
        $fields = [
            ['type' => 'select', 'name' => 's', 'choices' => $choices],
            ['type' => 'select', 'name' => 'sm', 'choices' => $choices, 'multiple' => true],
            ['type' => 'checkbox', 'name' => 'c', 'choices' => $choices],
            ['type' => 'radio', 'name' => 'r', 'choices' => $choices],
            ['type' => 'button_group', 'name' => 'bg', 'choices' => $choices],
        ];

        $this->assertArrayHasKey('s', $this->errors($fields, ['s' => 'x']));
        $this->assertArrayHasKey('sm.1', $this->errors($fields, ['sm' => ['a', 'x']]));
        $this->assertArrayHasKey('c.0', $this->errors($fields, ['c' => ['x']]));
        $this->assertArrayHasKey('r', $this->errors($fields, ['r' => 'x']));
        $this->assertArrayHasKey('bg', $this->errors($fields, ['bg' => 'x']));
    }

    public function test_custom_values_when_allowed(): void
    {
        $choices = ['a' => 'Alfa'];

        $this->assertNull($this->errors([['type' => 'checkbox', 'name' => 'cc', 'choices' => $choices, 'allow_custom' => true]], ['cc' => ['własna']]));
        $this->assertNull($this->errors([['type' => 'radio', 'name' => 'ro', 'choices' => $choices, 'other_choice' => true]], ['ro' => 'inne']));
    }

    public function test_choice_normalization_and_return_formats(): void
    {
        $choices = ['a' => 'Alfa', 'b' => 'Beta'];
        $fields = [
            ['type' => 'select', 'name' => 's', 'choices' => $choices, 'return_format' => 'label'],
            ['type' => 'select', 'name' => 'sm', 'choices' => $choices, 'multiple' => true],
            ['type' => 'checkbox', 'name' => 'c', 'choices' => $choices],
            ['type' => 'true_false', 'name' => 'tf'],
        ];

        $out = $this->validator->validate($fields, ['s' => 'a', 'sm' => ['a', 'b'], 'c' => ['b'], 'tf' => '1']);
        $this->assertSame('a', $out['s']);
        $this->assertSame(['a', 'b'], $out['sm']);
        $this->assertSame(['b'], $out['c']);
        $this->assertTrue($out['tf']);

        $select = $this->registry->resolveField($fields[0]);
        $this->assertSame('Alfa', $this->registry->get('select')->format('a', $select));
        $select['return_format'] = 'array';
        $this->assertSame(['value' => 'a', 'label' => 'Alfa'], $this->registry->get('select')->format('a', $select));
    }

    public function test_required_true_false_rejects_false(): void
    {
        $this->assertArrayHasKey('z', $this->errors([['type' => 'true_false', 'name' => 'z', 'required' => true]], ['z' => '0']));
    }

    public function test_hidden_required_field_does_not_block_and_is_not_returned(): void
    {
        $fields = [
            ['type' => 'true_false', 'name' => 'ma_rabat'],
            ['type' => 'number', 'name' => 'rabat', 'required' => true,
                'conditional_logic' => [[['field' => 'ma_rabat', 'operator' => '==', 'value' => '1']]]],
        ];

        $this->assertNull($this->errors($fields, ['ma_rabat' => false]));
        $this->assertArrayNotHasKey('rabat', $this->validator->validate($fields, ['ma_rabat' => false, 'rabat' => 10]));
        $this->assertArrayHasKey('rabat', $this->errors($fields, ['ma_rabat' => true]));
    }

    public function test_password_is_encrypted_at_rest(): void
    {
        $type = $this->registry->get('password');
        $field = $this->registry->resolveField(['type' => 'password', 'name' => 'p']);
        $encrypted = $type->normalize('tajne', $field);

        $this->assertNotSame('tajne', $encrypted);
        $this->assertSame('tajne', $type->format($encrypted, $field));
    }

    public function test_textarea_escapes_and_wraps_paragraphs(): void
    {
        $field = $this->registry->resolveField(['type' => 'textarea', 'name' => 't']);
        $html = (string) $this->registry->get('textarea')->format("a <b>\nb\n\nc", $field);

        $this->assertSame("<p>a &lt;b&gt;<br />\nb</p>\n<p>c</p>", $html);
    }

    public function test_color_picker(): void
    {
        $field = $this->registry->resolveField(['type' => 'color_picker', 'name' => 'k', 'return_format' => 'array']);
        $this->assertSame(
            ['red' => 255, 'green' => 128, 'blue' => 0, 'alpha' => 1],
            $this->registry->get('color_picker')->format('#ff8000', $field),
        );

        $this->assertArrayHasKey('k', $this->errors([['type' => 'color_picker', 'name' => 'k']], ['k' => 'rgba(1,2,3,0.5)']));
        $this->assertNull($this->errors([['type' => 'color_picker', 'name' => 'k', 'enable_opacity' => true]], ['k' => 'rgba(1,2,3,0.5)']));
    }

    public function test_date_email_and_url(): void
    {
        $date = $this->registry->resolveField(['type' => 'date_picker', 'name' => 'd', 'return_format' => 'd.m.Y']);
        $this->assertSame('12.11.2026', $this->registry->get('date_picker')->format('2026-11-12', $date));
        $this->assertArrayHasKey('d', $this->errors([['type' => 'date_picker', 'name' => 'd']], ['d' => '12/11/2026']));

        $this->assertArrayHasKey('e', $this->errors([['type' => 'email', 'name' => 'e']], ['e' => 'nie-email']));
        $this->assertNull($this->errors([['type' => 'url', 'name' => 'u']], ['u' => 'https://example.pl/a']));
        $this->assertArrayHasKey('u', $this->errors([['type' => 'url', 'name' => 'u']], ['u' => 'javascript:alert(1)']));
    }

    public function test_layout_elements_store_no_data(): void
    {
        $this->assertSame([], $this->validator->validate([['type' => 'tab', 'name' => 'zakladka'], ['type' => 'message', 'name' => 'm']], []));
    }
}
