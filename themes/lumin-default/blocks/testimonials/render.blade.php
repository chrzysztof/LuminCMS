@php($items = is_array($attrs['items'] ?? null) ? $attrs['items'] : [])
@if ($items)
    <section class="lm-testimonials">
        <div class="lm-container">
            @if (! empty($attrs['title']))
                <h2>{{ $attrs['title'] }}</h2>
            @endif
            <div class="lm-testimonials__grid">
                @foreach ($items as $item)
                    <figure class="lm-testimonials__item">
                        <blockquote>{{ $item['quote'] ?? '' }}</blockquote>
                        @if (! empty($item['author']))
                            <figcaption>{{ $item['author'] }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif
