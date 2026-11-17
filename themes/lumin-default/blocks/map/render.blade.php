@php
    $place = is_array($attrs['location'] ?? null) ? $attrs['location'] : null;
@endphp
@if ($place)
    <figure class="lm-map">
        <iframe src="{{ $place['embed_url'] }}" title="{{ ($attrs['title'] ?? '') ?: ($place['address'] ?: __('Mapa')) }}" loading="lazy" referrerpolicy="no-referrer"></iframe>
        <figcaption><a href="{{ $place['link'] }}" target="_blank" rel="noopener noreferrer">{{ __('Większa mapa') }}</a></figcaption>
    </figure>
@elseif (request()->routeIs('cms.*'))
    <p class="lm-widget-empty">{{ __('Wskaż miejsce na mapie w ustawieniach bloku.') }}</p>
@endif
