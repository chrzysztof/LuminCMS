<?php

namespace App\Cms\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Zapis plików, generowanie wariantów WebP (rozmiary, kadrowanie, punkt ogniskowy) i sprzątanie.
 */
class MediaManager
{
    public function __construct(private readonly ImageProcessor $images) {}

    /** @param  array{alt?:string,title?:string,caption?:string}  $attributes */
    public function store(UploadedFile $file, array $attributes = [], ?int $userId = null): Media
    {
        $mime = (string) $file->getMimeType();

        if (! in_array($mime, config('media.allowed_mimes'), true)) {
            throw new InvalidArgumentException("Typ pliku {$mime} nie jest dozwolony.");
        }

        $disk = config('media.disk');
        $extension = $this->extensionFor($mime, $file->getClientOriginalExtension());
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $base = (Str::slug($original) ?: 'plik').'-'.Str::lower(Str::random(6));
        $directory = 'media/'.now()->format('Y/m');
        $path = "{$directory}/{$base}.{$extension}";

        Storage::disk($disk)->put($path, $file->getContent(), 'public');

        $media = Media::create([
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => $file->getSize(),
            'alt' => $attributes['alt'] ?? '',
            'title' => $attributes['title'] ?? $original,
            'caption' => $attributes['caption'] ?? null,
            'focal_x' => 0.5,
            'focal_y' => 0.5,
            'uploaded_by' => $userId,
        ]);

        if ($this->isProcessable($mime)) {
            $this->generateVariants($media);
        } elseif (str_starts_with($mime, 'image/')) {
            [$width, $height] = $this->readDimensions($media);
            $media->update(['width' => $width, 'height' => $height]);
        }

        return $media->refresh();
    }

    /** Usuwa stare warianty i tworzy wszystkie od nowa (np. po zmianie rozmiarów). */
    public function regenerate(Media $media): Media
    {
        if ($this->isProcessable($media->mime)) {
            $this->generateVariants($media);
        }

        return $media->refresh();
    }

    /**
     * Zapisuje własne kadrowanie dla rozmiaru i odświeża tylko ten wariant.
     *
     * @param  array{x:int,y:int,width:int,height:int}  $rect  obszar w pikselach oryginału
     */
    public function setCrop(Media $media, string $sizeName, array $rect): Media
    {
        $size = MediaSize::active()->where('name', $sizeName)->where('crop', true)->first();

        if (! $size) {
            throw new InvalidArgumentException("Rozmiar {$sizeName} nie istnieje lub nie jest kadrowany.");
        }

        $rect = array_map('intval', array_intersect_key($rect, array_flip(['x', 'y', 'width', 'height'])));

        if (count($rect) !== 4 || $rect['width'] < 1 || $rect['height'] < 1 || $rect['x'] < 0 || $rect['y'] < 0
            || $media->width < $rect['x'] + $rect['width'] || $media->height < $rect['y'] + $rect['height']) {
            throw new InvalidArgumentException('Obszar kadrowania wykracza poza obraz.');
        }

        $media->crops = [...($media->crops ?? []), $sizeName => $rect];
        $media->save();

        $this->generateVariants($media, only: [$sizeName]);

        return $media->refresh();
    }

    /** Usuwa własne kadrowanie rozmiaru (wraca do kadrowania według punktu ogniskowego). */
    public function clearCrop(Media $media, string $sizeName): Media
    {
        $crops = $media->crops ?? [];
        unset($crops[$sizeName]);
        $media->crops = $crops ?: null;
        $media->save();

        $this->generateVariants($media, only: [$sizeName]);

        return $media->refresh();
    }

    /** Ustawia punkt ogniskowy (0..1) i odświeża kadrowane warianty bez własnego kadru. */
    public function setFocalPoint(Media $media, float $x, float $y): Media
    {
        $media->focal_x = max(0, min(1, $x));
        $media->focal_y = max(0, min(1, $y));
        $media->save();

        $names = MediaSize::active()->where('crop', true)->pluck('name')
            ->reject(fn ($name) => isset(($media->crops ?? [])[$name]))->all();

        if ($names) {
            $this->generateVariants($media, only: $names);
        }

        return $media->refresh();
    }

    public function delete(Media $media): void
    {
        $disk = Storage::disk($media->disk);

        foreach ($media->variants ?? [] as $variant) {
            $disk->delete($variant['path']);
        }

        $disk->delete($media->path);
        $media->delete();
    }

    /**
     * Tworzy warianty WebP: "full" (do max_dimension) i wszystkie aktywne rozmiary.
     * Rozmiary większe od obrazu są pomijane (bez powiększania).
     *
     * @param  list<string>|null  $only  tylko wskazane rozmiary (bez "full")
     */
    private function generateVariants(Media $media, ?array $only = null): void
    {
        $disk = Storage::disk($media->disk);
        $source = $this->images->open($disk->get($media->path), $media->mime);
        $sw = $this->images->width($source);
        $sh = $this->images->height($source);

        $quality = (int) config('media.webp_quality');
        $lossless = in_array($media->mime, config('media.lossless_sources'), true);

        $variants = $media->variants ?? [];
        $stem = Str::beforeLast($media->path, '.');

        $write = function (string $name, $image) use (&$variants, $disk, $stem, $quality, $lossless) {
            if (isset($variants[$name])) {
                $disk->delete($variants[$name]['path']);
            }

            $path = "{$stem}-{$name}.webp";
            $data = $this->images->toWebp($image, $quality, $lossless);
            $disk->put($path, $data, 'public');

            $variants[$name] = [
                'path' => $path,
                'width' => $this->images->width($image),
                'height' => $this->images->height($image),
                'size' => strlen($data),
            ];
        };

        if ($only === null) {
            $max = (int) config('media.max_dimension');
            $write(MediaSize::FULL, $this->images->fit($source, $max, $max));
        }

        $sizes = MediaSize::active()->get();

        // Usuń warianty rozmiarów, które już nie istnieją lub są dla tego obrazu za duże.
        if ($only === null) {
            foreach (array_keys($variants) as $name) {
                if ($name !== MediaSize::FULL) {
                    $disk->delete($variants[$name]['path']);
                    unset($variants[$name]);
                }
            }
        }

        foreach ($sizes as $size) {
            if ($only !== null && ! in_array($size->name, $only, true)) {
                continue;
            }

            if ($size->name === MediaSize::FULL) {
                continue;
            }

            if ($size->crop) {
                if ($size->width < 1 || $size->height < 1 || ($sw < $size->width && $sh < $size->height)) {
                    $this->dropVariant($variants, $disk, $size->name);

                    continue;
                }

                $rect = ($media->crops ?? [])[$size->name] ?? null;
                $write($size->name, $this->images->cover($source, $size->width, $size->height, $media->focal_x, $media->focal_y, $rect));

                continue;
            }

            $resized = $this->images->fit($source, $size->width, $size->height);

            // fit() zwraca ten sam obraz, gdy rozmiar wymagałby powiększenia: wtedy pomijamy wariant.
            if ($resized === $source) {
                $this->dropVariant($variants, $disk, $size->name);

                continue;
            }

            $write($size->name, $resized);
        }

        $media->forceFill(['width' => $sw, 'height' => $sh, 'variants' => $variants])->save();
    }

    private function dropVariant(array &$variants, $disk, string $name): void
    {
        if (isset($variants[$name])) {
            $disk->delete($variants[$name]['path']);
            unset($variants[$name]);
        }
    }

    /** @return array{0:int,1:int} */
    private function readDimensions(Media $media): array
    {
        $info = @getimagesizefromstring(Storage::disk($media->disk)->get($media->path));

        return [$info[0] ?? 0, $info[1] ?? 0];
    }

    private function isProcessable(string $mime): bool
    {
        return in_array($mime, config('media.processable_mimes'), true);
    }

    private function extensionFor(string $mime, string $clientExtension): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => Str::lower(preg_replace('/[^A-Za-z0-9]/', '', $clientExtension)) ?: 'bin',
        };
    }
}
