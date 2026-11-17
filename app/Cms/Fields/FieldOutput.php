<?php

namespace App\Cms\Fields;

use App\Cms\Content\Content;
use App\Cms\Media\Media;
use Illuminate\Support\HtmlString;

/**
 * Zamienia sformatowaną wartość pola własnego (return_format) na bezpieczny fragment HTML lub adres.
 * Używane przez shortcode [field], czyli przez powiązania elementów edytora z polami własnymi.
 */
class FieldOutput
{
    public const FORMATS = ['text', 'html', 'url', 'image'];

    /** Wbudowane pola treści dostępne w powiązaniach obok pól własnych (nazwy z podkreślnikiem na początku). */
    public const BUILT_IN = [
        '_featured' => ['label' => 'Obraz wyróżniający', 'type' => 'image'],
        '_title' => ['label' => 'Tytuł', 'type' => 'text'],
        '_excerpt' => ['label' => 'Zajawka', 'type' => 'textarea'],
    ];

    public static function builtIn(Content $content, string $name): mixed
    {
        return match ($name) {
            '_featured' => $content->featured_media_id,
            '_title' => $content->title,
            '_excerpt' => $content->excerpt,
            default => null,
        };
    }

    /** Czy pole ma wartość: niepusty tekst, lista, zaznaczony przełącznik, liczba (także 0). */
    public static function filled(mixed $value): bool
    {
        return match (true) {
            $value === null, $value === false, $value === '' => false,
            is_array($value) => array_filter($value, fn ($v) => $v !== null && $v !== '' && $v !== []) !== [],
            is_string($value), $value instanceof \Stringable => trim(strip_tags((string) $value)) !== '' || str_contains((string) $value, '<img'),
            default => true,
        };
    }

    /** Tekst: wartości proste, listy po przecinku, tak/nie; HTML z pól tekstowych jest sprowadzany do tekstu. */
    public static function text(mixed $value): string
    {
        if ($value instanceof HtmlString) {
            return e(trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', (string) $value)), ENT_QUOTES)));
        }
        if (is_bool($value)) {
            return e($value ? __('Tak') : __('Nie'));
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return e((string) $value);
        }
        if (is_array($value)) {
            if (isset($value['title']) && is_scalar($value['title'])) {
                return e((string) $value['title']); // link: tekst odnośnika
            }
            if (isset($value['label']) && is_scalar($value['label'])) {
                return e((string) $value['label']); // wybór: etykieta
            }
            $parts = array_map(fn ($v) => self::text($v), array_filter($value, fn ($v) => is_scalar($v) || $v instanceof \Stringable || is_array($v)));

            return implode(', ', array_filter($parts, fn ($p) => $p !== ''));
        }

        return '';
    }

    /** HTML z pól edytora i tekstowych (już oczyszczony przez typ pola); inne wartości jako tekst. */
    public static function html(mixed $value): string
    {
        return $value instanceof HtmlString ? (string) $value : self::text($value);
    }

    /** Adres: tekst, pole URL, link, odnośnik do treści albo plik z biblioteki. Tylko http(s), mailto, tel i adresy względne. */
    public static function url(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['url'] ?? null;
        }
        if (! is_string($value) && ! $value instanceof \Stringable) {
            return null;
        }

        $url = trim((string) $value);

        if ($url === '' || preg_match('~[\x00-\x1f\s]~', $url)) {
            return null;
        }
        if ((str_starts_with($url, '/') && ! str_starts_with($url, '//')) || str_starts_with($url, '#')) {
            return $url;
        }

        return preg_match('~^(https?://|mailto:|tel:)~i', $url) ? $url : null;
    }

    /** Obraz z biblioteki jako <picture> (srcset, wymiary) albo zwykły <img> dla adresu. */
    public static function image(mixed $value, string $size, string $class, string $alt): string
    {
        $id = is_array($value) ? ($value['id'] ?? null) : (is_numeric($value) ? $value : null);
        $attributes = array_filter(['class' => $class ?: null, 'alt' => $alt !== '' ? $alt : null], fn ($v) => $v !== null);

        if (is_numeric($id) && ($media = Media::find((int) $id))) {
            return (string) $media->picture($size, '100vw', $attributes);
        }

        $url = self::url($value);

        return $url === null ? '' : '<img'.($class !== '' ? ' class="'.e($class).'"' : '').' src="'.e($url).'" alt="'.e($alt).'" loading="lazy" decoding="async">';
    }
}
