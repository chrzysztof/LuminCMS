@php
    $lat = is_numeric($attrs['lat'] ?? null) ? (float) $attrs['lat'] : null;
    $lon = is_numeric($attrs['lon'] ?? null) ? (float) $attrs['lon'] : null;
    $span = in_array((string) ($attrs['zoom'] ?? '0.02'), ['0.02', '0.06', '0.2'], true) ? (float) $attrs['zoom'] : 0.02;
@endphp
@if ($lat !== null && $lon !== null && abs($lat) <= 90 && abs($lon) <= 180)
    @php
        $bbox = implode(',', [round($lon - $span, 6), round($lat - $span / 2, 6), round($lon + $span, 6), round($lat + $span / 2, 6)]);
    @endphp
    <figure class="lm-map">
        <iframe src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&amp;layer=mapnik&amp;marker={{ $lat }},{{ $lon }}" title="{{ $attrs['title'] ?? '' }}" loading="lazy" referrerpolicy="no-referrer"></iframe>
        <figcaption><a href="https://www.openstreetmap.org/?mlat={{ $lat }}&amp;mlon={{ $lon }}#map=16/{{ $lat }}/{{ $lon }}" target="_blank" rel="noopener noreferrer">{{ __('Większa mapa') }}</a></figcaption>
    </figure>
@elseif (request()->routeIs('cms.*'))
    <p class="lm-widget-empty">{{ __('Podaj współrzędne miejsca w ustawieniach bloku.') }}</p>
@endif
