@php
    $inside = (bool) config('cms.runtime.in_post_content', false);
    $body = '';

    if (! $inside && ! empty($current)) {
        config(['cms.runtime.in_post_content' => true]);
        app(\App\Cms\Builder\LocalCss::class)->add($current->css_cache);

        try {
            $body = app(\App\Cms\Shortcodes\Shortcodes::class)->render((string) $current->html_cache, $current);
        } finally {
            config(['cms.runtime.in_post_content' => false]);
        }
    }
@endphp
@if ($inside)
@elseif (! empty($current))
    <div class="lm-article__body">{!! $body !!}</div>
@else
    <div class="lm-article__body lm-template-placeholder"><p>{{ __('Tutaj pojawi się treść wpisu.') }}</p></div>
@endif
