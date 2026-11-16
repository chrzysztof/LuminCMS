@php
    $items = array_values(array_filter(is_array($attrs['items'] ?? null) ? $attrs['items'] : [], fn ($i) => is_numeric($i['value'] ?? null)));
@endphp
@if ($items)
    <section class="lm-counters">
        <div class="lm-container">
            <dl class="lm-counters__grid">
                @foreach ($items as $item)
                    <div class="lm-counters__item">
                        <dt class="lm-counters__value">{{ $item['prefix'] ?? '' }}<span data-lm-count="{{ $item['value'] + 0 }}">{{ $item['value'] + 0 }}</span>{{ $item['suffix'] ?? '' }}</dt>
                        @if (! empty($item['label']))
                            <dd class="lm-counters__label">{{ $item['label'] }}</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        </div>
    </section>
@elseif (request()->routeIs('cms.*'))
    <p class="lm-widget-empty">{{ __('Dodaj liczniki w ustawieniach bloku.') }}</p>
@endif
