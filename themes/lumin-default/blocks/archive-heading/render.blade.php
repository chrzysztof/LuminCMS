@php
    $listing = app(\App\Cms\Themes\Listing::class);
    $heading = $listing->active() ? $listing->heading : __('Nazwa archiwum');
@endphp
<header class="lm-archive-heading">
    <h1>{{ $heading }}</h1>
    @if (! empty($attrs['show_description']) && $listing->description !== '')
        <p class="lm-archive-lead">{{ $listing->description }}</p>
    @endif
</header>
