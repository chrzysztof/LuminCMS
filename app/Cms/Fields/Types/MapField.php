<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use Illuminate\Support\HtmlString;

/**
 * Mapa OpenStreetMap: wskazanie miejsca (szerokość, długość, powiększenie, adres).
 * Nie wymaga klucza API; osadzenie to iframe budowany z zapisanych współrzędnych.
 */
class MapField extends AbstractFieldType
{
    public function name(): string
    {
        return 'map';
    }

    public function label(): string
    {
        return 'Mapa';
    }

    public function category(): string
    {
        return 'advanced';
    }

    protected function defaults(): array
    {
        return [
            'center_lat' => 52.2297, 'center_lng' => 21.0122, // domyślnie Warszawa
            'zoom' => 14, 'height' => 320,
            'return_format' => 'array', // array | embed
        ];
    }

    public function rules(array $field): array
    {
        return [
            ...$this->baseRules($field), 'array',
            fn (string $a, mixed $v, \Closure $fail) => $this->valid($v) ?: $fail('Wskaż poprawne miejsce na mapie.'),
        ];
    }

    private function valid(mixed $v): bool
    {
        if ($v === null || $v === [] || ! is_array($v)) {
            return $v === null || $v === [];
        }

        $address = $v['address'] ?? '';

        return is_numeric($v['lat'] ?? null) && $v['lat'] >= -90 && $v['lat'] <= 90
            && is_numeric($v['lng'] ?? null) && $v['lng'] >= -180 && $v['lng'] <= 180
            && (! isset($v['zoom']) || (is_numeric($v['zoom']) && $v['zoom'] >= 1 && $v['zoom'] <= 20))
            && (is_string($address) || $address === null) && mb_strlen((string) $address) <= 255;
    }

    public function normalize(mixed $value, array $field): mixed
    {
        if (! is_array($value) || ! isset($value['lat'], $value['lng'])) {
            return null;
        }

        return [
            'lat' => round((float) $value['lat'], 6),
            'lng' => round((float) $value['lng'], 6),
            'zoom' => (int) ($value['zoom'] ?? $field['zoom']),
            'address' => trim((string) ($value['address'] ?? '')),
        ];
    }

    public function isEmpty(mixed $value): bool
    {
        return ! is_array($value) || ! isset($value['lat'], $value['lng']);
    }

    public function format(mixed $value, array $field): mixed
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        $embed = $this->embedUrl($value);

        if ($field['return_format'] === 'embed') {
            $height = (int) $this->number($field, 'height') ?: 320;

            return new HtmlString('<iframe src="'.e($embed).'" width="100%" height="'.$height.'" style="border:0" loading="lazy" referrerpolicy="no-referrer" title="'.e($value['address'] ?: 'Mapa').'"></iframe>');
        }

        return $value + ['embed_url' => $embed, 'link' => "https://www.openstreetmap.org/?mlat={$value['lat']}&mlon={$value['lng']}#map={$value['zoom']}/{$value['lat']}/{$value['lng']}"];
    }

    /** Adres osadzenia OSM: obszar wokół punktu zależny od powiększenia. */
    private function embedUrl(array $v): string
    {
        $span = 360 / (2 ** max(1, min(20, (int) $v['zoom']))) * 1.2;
        $bbox = implode(',', [round($v['lng'] - $span, 6), round($v['lat'] - $span / 2, 6), round($v['lng'] + $span, 6), round($v['lat'] + $span / 2, 6)]);

        return "https://www.openstreetmap.org/export/embed.html?bbox={$bbox}&layer=mapnik&marker={$v['lat']},{$v['lng']}";
    }
}
