@php
    $logos = array_values(array_filter(is_array($attrs['logos'] ?? null) ? $attrs['logos'] : [], 'is_array'));
@endphp
@if ($logos)
    <section class="lm-logos">
        <div class="lm-container">
            @if (! empty($attrs['title']))
                <h2 class="lm-logos__title">{{ $attrs['title'] }}</h2>
            @endif
            <ul class="lm-logos__list">
                @foreach ($logos as $logo)
                    <li><img src="{{ $logo['url'] }}" alt="{{ $logo['alt'] }}" loading="lazy"></li>
                @endforeach
            </ul>
        </div>
    </section>
@elseif (request()->routeIs('cms.*'))
    <p class="lm-widget-empty">{{ __('Wybierz logo w ustawieniach bloku.') }}</p>
@endif
