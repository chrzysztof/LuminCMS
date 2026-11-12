<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use Illuminate\Support\HtmlString;

/**
 * Osadzanie filmów z YouTube i Vimeo. Kod iframe budujemy sami z rozpoznanego adresu,
 * więc nie ma zapytań do zewnętrznych usług ani wstrzykiwania cudzego HTML.
 */
class OEmbedField extends AbstractFieldType
{
    public function name(): string
    {
        return 'oembed';
    }

    public function label(): string
    {
        return 'oEmbed';
    }

    public function category(): string
    {
        return 'content';
    }

    protected function defaults(): array
    {
        return ['width' => '', 'height' => '', 'return_format' => 'html']; // html | url
    }

    public function rules(array $field): array
    {
        return [...$this->baseRules($field), 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
            if ($value !== null && $value !== '' && $this->embedUrl((string) $value) === null) {
                $fail('Obsługiwane adresy: YouTube i Vimeo.');
            }
        }];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function format(mixed $value, array $field): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($field['return_format'] === 'url') {
            return $value;
        }

        $src = $this->embedUrl((string) $value);

        if ($src === null) {
            return null;
        }

        $w = (int) $this->number($field, 'width') ?: 0;
        $h = (int) $this->number($field, 'height') ?: 0;
        $size = $w && $h ? " width=\"{$w}\" height=\"{$h}\"" : '';

        return new HtmlString('<iframe src="'.e($src).'"'.$size.' loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" title="Film"></iframe>');
    }

    /** Adres osadzenia dla rozpoznanego dostawcy albo null. */
    public function embedUrl(string $url): ?string
    {
        $url = trim($url);

        if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }

        if (preg_match('~^https?://(?:www\.)?vimeo\.com/(?:video/)?(\d+)~i', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }
}
