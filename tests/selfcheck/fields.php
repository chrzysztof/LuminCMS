<?php

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\ConditionalLogic;
use App\Cms\Fields\FieldGroup;
use App\Cms\Fields\FieldRegistry;
use App\Cms\Fields\FieldValidator;
use App\Cms\Fields\LocationMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fail = 0;
$check = function (string $name, bool $ok, mixed $detail = null) use (&$fail) {
    echo ($ok ? 'OK    ' : 'BŁĄD  ').$name.($ok ? '' : '  -> '.json_encode($detail, JSON_UNESCAPED_UNICODE)).PHP_EOL;
    if (! $ok) {
        $fail++;
    }
};
$throws = function (callable $fn): ?array {
    try {
        $fn();

        return null;
    } catch (ValidationException $e) {
        return $e->errors();
    }
};

$validator = app(FieldValidator::class);
$registry = app(FieldRegistry::class);

// --- Rejestr
$check('19 typów zarejestrowanych', count($registry->all()) === 19, count($registry->all()));
$check('nieznany typ rzuca wyjątek', (function () use ($registry) {
    try {
        $registry->get('nie_ma');

        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
})());

// --- Text / Number
$fields = [
    ['type' => 'text', 'name' => 'tytul', 'label' => 'Tytuł', 'required' => true, 'maxlength' => 5],
    ['type' => 'number', 'name' => 'cena', 'min' => 10, 'max' => 100],
];
$check('text wymagany: pusty odrzucony', isset($throws(fn () => $validator->validate($fields, ['tytul' => '', 'cena' => 20]))['tytul']));
$check('text maxlength odrzuca za długi', isset($throws(fn () => $validator->validate($fields, ['tytul' => 'dłuższy', 'cena' => 20]))['tytul']));
$check('number min odrzuca', isset($throws(fn () => $validator->validate($fields, ['tytul' => 'ok', 'cena' => 5]))['cena']));
$out = $validator->validate($fields, ['tytul' => 'ok', 'cena' => '42']);
$check('number zapisany jako int', $out['cena'] === 42, $out);
$out = $validator->validate($fields, ['tytul' => 'ok', 'cena' => '42.5']);
$check('number zapisany jako float', $out['cena'] === 42.5, $out);

// --- Select / Checkbox / Radio / ButtonGroup / TrueFalse
$choices = ['a' => 'Alfa', 'b' => 'Beta'];
$fields = [
    ['type' => 'select', 'name' => 's', 'choices' => $choices, 'return_format' => 'label'],
    ['type' => 'select', 'name' => 'sm', 'choices' => $choices, 'multiple' => true],
    ['type' => 'checkbox', 'name' => 'c', 'choices' => $choices],
    ['type' => 'checkbox', 'name' => 'cc', 'choices' => $choices, 'allow_custom' => true],
    ['type' => 'radio', 'name' => 'r', 'choices' => $choices],
    ['type' => 'radio', 'name' => 'ro', 'choices' => $choices, 'other_choice' => true],
    ['type' => 'button_group', 'name' => 'bg', 'choices' => $choices],
    ['type' => 'true_false', 'name' => 'tf'],
];
$check('select odrzuca wartość spoza opcji', isset($throws(fn () => $validator->validate($fields, ['s' => 'x']))['s']));
$check('select multiple odrzuca element spoza opcji', isset($throws(fn () => $validator->validate($fields, ['sm' => ['a', 'x']]))['sm.1']));
$check('checkbox odrzuca spoza opcji', isset($throws(fn () => $validator->validate($fields, ['c' => ['x']]))['c.0']));
$check('checkbox allow_custom przyjmuje własną wartość', $throws(fn () => $validator->validate($fields, ['cc' => ['własna']])) === null);
$check('radio odrzuca spoza opcji', isset($throws(fn () => $validator->validate($fields, ['r' => 'x']))['r']));
$check('radio other_choice przyjmuje własną wartość', $throws(fn () => $validator->validate($fields, ['ro' => 'inne'])) === null);
$check('button_group odrzuca spoza opcji', isset($throws(fn () => $validator->validate($fields, ['bg' => 'x']))['bg']));
$out = $validator->validate($fields, ['s' => 'a', 'sm' => ['a', 'b'], 'c' => ['b'], 'tf' => '1']);
$check('normalizacja: select, multiple, checkbox, bool', $out['s'] === 'a' && $out['sm'] === ['a', 'b'] && $out['c'] === ['b'] && $out['tf'] === true, $out);
$sel = $registry->resolveField($fields[0]);
$check('select return_format=label', $registry->get('select')->format('a', $sel) === 'Alfa');
$sel['return_format'] = 'array';
$check('select return_format=array', $registry->get('select')->format('a', $sel) === ['value' => 'a', 'label' => 'Alfa']);
$check('true_false wymagane: false odrzucone', isset($throws(fn () => $validator->validate([['type' => 'true_false', 'name' => 'z', 'required' => true]], ['z' => '0']))['z']));

// --- Logika warunkowa: ukryte pole wymagane nie blokuje zapisu i nie jest zwracane
$fields = [
    ['type' => 'true_false', 'name' => 'ma_rabat'],
    ['type' => 'number', 'name' => 'rabat', 'required' => true,
        'conditional_logic' => [[['field' => 'ma_rabat', 'operator' => '==', 'value' => '1']]]],
];
$check('ukryte pole wymagane nie blokuje', $throws(fn () => $validator->validate($fields, ['ma_rabat' => false])) === null);
$out = $validator->validate($fields, ['ma_rabat' => false, 'rabat' => 10]);
$check('ukryte pole nie jest zwracane', ! array_key_exists('rabat', $out), $out);
$check('widoczne pole wymagane blokuje', isset($throws(fn () => $validator->validate($fields, ['ma_rabat' => true]))['rabat']));

$logic = new ConditionalLogic;
$f = fn ($op, $val = '') => ['conditional_logic' => [[['field' => 'x', 'operator' => $op, 'value' => $val]]]];
$check('logika: == na tablicy', $logic->passes($f('==', 'a'), ['x' => ['a', 'b']]));
$check('logika: != ', $logic->passes($f('!=', 'a'), ['x' => 'b']));
$check('logika: ==empty', $logic->passes($f('==empty'), ['x' => '']));
$check('logika: !=empty', ! $logic->passes($f('!=empty'), ['x' => null]));
$check('logika: ==pattern', $logic->passes($f('==pattern', '^ab'), ['x' => 'abc']));
$check('logika: ==contains', $logic->passes($f('==contains', 'bc'), ['x' => 'abcd']));
$or = ['conditional_logic' => [[['field' => 'x', 'operator' => '==', 'value' => '1']], [['field' => 'y', 'operator' => '==', 'value' => '1']]]];
$check('logika: OR między grupami', $logic->passes($or, ['x' => '0', 'y' => '1']));
$and = ['conditional_logic' => [[['field' => 'x', 'operator' => '==', 'value' => '1'], ['field' => 'y', 'operator' => '==', 'value' => '1']]]];
$check('logika: AND w grupie', ! $logic->passes($and, ['x' => '1', 'y' => '0']));

// --- Pozostałe typy: password, textarea, kolor, daty, e-mail, url
$pw = $registry->resolveField(['type' => 'password', 'name' => 'p']);
$enc = $registry->get('password')->normalize('tajne', $pw);
$check('password szyfrowany w bazie', $enc !== 'tajne' && $registry->get('password')->format($enc, $pw) === 'tajne');

$ta = $registry->resolveField(['type' => 'textarea', 'name' => 't']);
$html = (string) $registry->get('textarea')->format("a <b>\nb\n\nc", $ta);
$check('textarea wpautop escapuje i tworzy akapity', $html === "<p>a &lt;b&gt;<br />\nb</p>\n<p>c</p>", $html);

$col = $registry->resolveField(['type' => 'color_picker', 'name' => 'k', 'return_format' => 'array']);
$check('color: hex -> tablica', $registry->get('color_picker')->format('#ff8000', $col) === ['red' => 255, 'green' => 128, 'blue' => 0, 'alpha' => 1]);
$check('color: odrzuca rgba bez enable_opacity', isset($throws(fn () => $validator->validate([['type' => 'color_picker', 'name' => 'k']], ['k' => 'rgba(1,2,3,0.5)']))['k']));
$check('color: przyjmuje rgba z enable_opacity', $throws(fn () => $validator->validate([['type' => 'color_picker', 'name' => 'k', 'enable_opacity' => true]], ['k' => 'rgba(1,2,3,0.5)'])) === null);

$d = $registry->resolveField(['type' => 'date_picker', 'name' => 'd', 'return_format' => 'd.m.Y']);
$check('date: format zwrotny', $registry->get('date_picker')->format('2026-11-12', $d) === '12.11.2026');
$check('date: odrzuca zły format zapisu', isset($throws(fn () => $validator->validate([['type' => 'date_picker', 'name' => 'd']], ['d' => '12/11/2026']))['d']));
$check('email odrzuca zły', isset($throws(fn () => $validator->validate([['type' => 'email', 'name' => 'e']], ['e' => 'nie-email']))['e']));
$check('url przyjmuje https', $throws(fn () => $validator->validate([['type' => 'url', 'name' => 'u']], ['u' => 'https://example.pl/a'])) === null);
$check('url odrzuca javascript:', isset($throws(fn () => $validator->validate([['type' => 'url', 'name' => 'u']], ['u' => 'javascript:alert(1)']))['u']));
$check('elementy układu nie są zwracane', $validator->validate([['type' => 'tab', 'name' => 'zakladka'], ['type' => 'message', 'name' => 'm']], []) === []);

// --- Reguły lokalizacji
$m = new LocationMatcher;
$rules = [[['param' => 'content_type', 'operator' => '==', 'value' => 'page'], ['param' => 'template', 'operator' => '!=', 'value' => 'pelna']], [['param' => 'term', 'operator' => '==', 'value' => '7']]];
$check('lokalizacja: AND w grupie', $m->matches($rules, ['content_type' => 'page', 'template' => 'default']));
$check('lokalizacja: != blokuje', ! $m->matches($rules, ['content_type' => 'page', 'template' => 'pelna']));
$check('lokalizacja: OR między grupami (term)', $m->matches($rules, ['content_type' => 'post', 'term' => [3, 7]]));
$check('lokalizacja: brak reguł nie pasuje', ! $m->matches([], ['content_type' => 'page']));

// --- Odczyt przez Content::field() na prawdziwej bazie
DB::beginTransaction();
try {
    FieldGroup::create([
        'key' => 'group_selfcheck', 'title' => 'Oferta',
        'fields' => [
            ['type' => 'number', 'name' => 'cena'],
            ['type' => 'select', 'name' => 'czas', 'choices' => ['60' => '60 minut', '90' => '90 minut'], 'return_format' => 'label'],
            ['type' => 'date_picker', 'name' => 'od', 'return_format' => 'd.m.Y'],
        ],
        'location_rules' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'page']]],
    ]);
    $page = Content::create(['content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Oferta', 'slug' => 'oferta',
        'fields' => ['cena' => 80, 'czas' => '90', 'od' => '2026-11-12', 'obce' => 'x']]);
    $post = Content::create(['content_type_id' => ContentType::where('key', 'post')->value('id'), 'title' => 'Wpis', 'slug' => 'wpis', 'fields' => ['cena' => 99]]);

    $check('Content::field: liczba', $page->field('cena') === 80, $page->field('cena'));
    $check('Content::field: select jako etykieta', $page->field('czas') === '90 minut', $page->field('czas'));
    $check('Content::field: data sformatowana', $page->field('od') === '12.11.2026', $page->field('od'));
    $check('Content::field: pole spoza grupy zwraca domyślną', $page->field('obce', 'brak') === 'brak');
    $check('Content::field: grupa nie dotyczy innego typu treści', $post->field('cena', 'brak') === 'brak');
} finally {
    DB::rollBack();
}

echo PHP_EOL.($fail === 0 ? 'WSZYSTKIE TESTY OK' : "BŁĘDÓW: {$fail}").PHP_EOL;
exit($fail === 0 ? 0 : 1);
