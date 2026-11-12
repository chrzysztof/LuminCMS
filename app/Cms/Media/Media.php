<?php

namespace App\Cms\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'disk', 'path', 'filename', 'mime', 'size', 'width', 'height',
        'alt', 'title', 'caption', 'variants', 'crops', 'focal_x', 'focal_y', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'variants' => 'array',
            'crops' => 'array',
            'focal_x' => 'float',
            'focal_y' => 'float',
        ];
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function hasVariants(): bool
    {
        return ! empty($this->variants);
    }

    /** URL oryginału. */
    public function originalUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * URL wariantu WebP. Gdy rozmiaru brak (np. obraz mniejszy od rozmiaru),
     * wraca do "full", a na końcu do oryginału.
     */
    public function url(?string $size = null): string
    {
        $variants = $this->variants ?? [];

        foreach ([$size, MediaSize::FULL] as $name) {
            if ($name !== null && isset($variants[$name])) {
                return Storage::disk($this->disk)->url($variants[$name]['path']);
            }
        }

        return $this->originalUrl();
    }

    /** Wymiary wariantu (lub oryginału, gdy wariantu brak). @return array{0:int,1:int} */
    public function dimensions(?string $size = null): array
    {
        $variants = $this->variants ?? [];

        foreach ([$size, MediaSize::FULL] as $name) {
            if ($name !== null && isset($variants[$name])) {
                return [$variants[$name]['width'], $variants[$name]['height']];
            }
        }

        return [(int) $this->width, (int) $this->height];
    }

    /** Wartość atrybutu srcset ze wszystkich wariantów WebP, od najmniejszego. */
    public function srcset(): string
    {
        $items = [];

        foreach ($this->variants ?? [] as $variant) {
            $items[$variant['width']] = Storage::disk($this->disk)->url($variant['path']).' '.$variant['width'].'w';
        }

        ksort($items);

        return implode(', ', $items);
    }

    /**
     * Semantyczny <picture>: źródło WebP z srcset, fallback do oryginału,
     * width/height (brak skoku układu), lazy loading i alt.
     *
     * @param  array<string, string|int|bool>  $attributes  dodatkowe atrybuty <img> (np. class)
     */
    public function picture(string $size = 'large', string $sizes = '100vw', array $attributes = [], bool $eager = false): HtmlString
    {
        [$width, $height] = $this->dimensions($size);

        $img = array_merge([
            'src' => $this->hasVariants() ? $this->originalUrl() : $this->url($size),
            'alt' => $this->alt,
            'width' => $width ?: null,
            'height' => $height ?: null,
            'loading' => $eager ? 'eager' : 'lazy',
            'decoding' => 'async',
        ], $attributes);

        $imgTag = '<img'.$this->attributes($img).'>';

        if (! $this->hasVariants()) {
            return new HtmlString($imgTag);
        }

        // <source> z WebP dla nowoczesnych przeglądarek; <img src> to oryginał jako fallback.
        $source = '<source type="image/webp" srcset="'.e($this->srcset()).'" sizes="'.e($sizes).'">';

        return new HtmlString('<picture>'.$source.$imgTag.'</picture>');
    }

    /** Reprezentacja do odpowiedzi API i do pól Image/File/Gallery. */
    public function asArray(?string $size = null): array
    {
        [$width, $height] = $this->dimensions($size);

        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $width ?: null,
            'height' => $height ?: null,
            'original_width' => $this->width,
            'original_height' => $this->height,
            'alt' => $this->alt,
            'title' => $this->title,
            'caption' => $this->caption,
            'url' => $this->url($size),
            'original_url' => $this->originalUrl(),
            'sizes' => collect($this->variants ?? [])->map(fn ($v) => [
                'url' => Storage::disk($this->disk)->url($v['path']),
                'width' => $v['width'],
                'height' => $v['height'],
            ])->all(),
            'focal_x' => $this->focal_x,
            'focal_y' => $this->focal_y,
            'crops' => $this->crops ?? (object) [],
        ];
    }

    private function attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $html .= $value === true ? ' '.e($name) : ' '.e($name).'="'.e((string) $value).'"';
        }

        return $html;
    }
}
