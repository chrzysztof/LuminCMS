@php
    $items = array_values(array_filter(is_array($attrs['items'] ?? null) ? $attrs['items'] : [], fn ($i) => ! empty($i['title'])));
@endphp
@if ($items)
    <div class="lm-tabs" data-lm-tabs>
        <div class="lm-tabs__list" role="tablist">
            @foreach ($items as $i => $item)
                <button type="button" role="tab" class="lm-tabs__tab" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" tabindex="{{ $i === 0 ? '0' : '-1' }}">{{ $item['title'] }}</button>
            @endforeach
        </div>
        @foreach ($items as $i => $item)
            <div class="lm-tabs__panel" role="tabpanel" @if ($i > 0) hidden @endif>
                {{-- Pole textarea jest już oczyszczone i zamienione na akapity przez typ pola. --}}
                {!! $item['text'] ?? '' !!}
            </div>
        @endforeach
    </div>
@elseif (request()->routeIs('cms.*'))
    <p class="lm-widget-empty">{{ __('Dodaj zakładki w ustawieniach bloku.') }}</p>
@endif
