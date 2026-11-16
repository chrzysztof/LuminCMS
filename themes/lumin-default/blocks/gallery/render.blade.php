@php
    $images = array_values(array_filter(is_array($attrs['images'] ?? null) ? $attrs['images'] : [], 'is_array'));
    $columns = (string) ($attrs['columns'] ?? '3');
    $columns = in_array($columns, ['2', '3', '4'], true) ? $columns : '3';
@endphp
@if ($images)
    <ul class="lm-gallery lm-gallery--{{ $columns }}" data-lm-gallery>
        @foreach ($images as $image)
            <li>
                <a href="{{ $image['original_url'] ?? $image['url'] }}" data-caption="{{ $image['caption'] ?: $image['alt'] }}">
                    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy"@if (! empty($image['width'])) width="{{ $image['width'] }}" height="{{ $image['height'] }}"@endif>
                </a>
            </li>
        @endforeach
    </ul>
@elseif (request()->routeIs('cms.*'))
    <p class="lm-widget-empty">{{ __('Wybierz zdjęcia w ustawieniach bloku.') }}</p>
@endif
