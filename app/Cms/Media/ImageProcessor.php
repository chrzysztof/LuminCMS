<?php

namespace App\Cms\Media;

use GdImage;
use InvalidArgumentException;

/** Operacje na obrazach oparte o GD (bez dodatkowych zależności). */
class ImageProcessor
{
    /** Wczytuje obraz z danych binarnych i poprawia orientację według EXIF. */
    public function open(string $binary, ?string $mime = null): GdImage
    {
        $image = @imagecreatefromstring($binary);

        if (! $image) {
            throw new InvalidArgumentException('Nie można odczytać obrazu.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $image = $this->autoOrient($image, $binary);
        }

        return $image;
    }

    public function width(GdImage $image): int
    {
        return imagesx($image);
    }

    public function height(GdImage $image): int
    {
        return imagesy($image);
    }

    /**
     * Skaluje tak, by obraz zmieścił się w $maxWidth x $maxHeight (0 = bez limitu danego boku).
     * Nigdy nie powiększa.
     */
    public function fit(GdImage $image, int $maxWidth, int $maxHeight): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);

        $ratio = min($maxWidth > 0 ? $maxWidth / $w : INF, $maxHeight > 0 ? $maxHeight / $h : INF, 1);

        if ($ratio >= 1) {
            return $image;
        }

        return $this->resample($image, 0, 0, $w, $h, max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio)));
    }

    /**
     * Kadruje do dokładnego rozmiaru $width x $height. Gdy podano $rect (x,y,width,height w px),
     * wycina ten obszar; w przeciwnym razie wycina największy pasujący prostokąt
     * wokół punktu ogniskowego ($focalX, $focalY w zakresie 0..1).
     *
     * @param  array{x:int,y:int,width:int,height:int}|null  $rect
     */
    public function cover(GdImage $image, int $width, int $height, float $focalX = 0.5, float $focalY = 0.5, ?array $rect = null): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);

        if ($rect === null) {
            $rect = $this->focalRect($w, $h, $width, $height, $focalX, $focalY);
        }

        return $this->resample($image, $rect['x'], $rect['y'], $rect['width'], $rect['height'], $width, $height);
    }

    /**
     * Największy prostokąt o proporcjach $targetW:$targetH mieszczący się w obrazie,
     * wyśrodkowany na punkcie ogniskowym i przesunięty tak, by nie wyjść poza obraz.
     *
     * @return array{x:int,y:int,width:int,height:int}
     */
    public function focalRect(int $w, int $h, int $targetW, int $targetH, float $focalX, float $focalY): array
    {
        $targetRatio = $targetW / $targetH;

        if ($w / $h > $targetRatio) {
            $cropH = $h;
            $cropW = (int) round($h * $targetRatio);
        } else {
            $cropW = $w;
            $cropH = (int) round($w / $targetRatio);
        }

        $x = (int) round($focalX * $w - $cropW / 2);
        $y = (int) round($focalY * $h - $cropH / 2);

        return [
            'x' => max(0, min($x, $w - $cropW)),
            'y' => max(0, min($y, $h - $cropH)),
            'width' => $cropW,
            'height' => $cropH,
        ];
    }

    /** Koduje do WebP. Przy $lossless bezstratnie (zachowuje ostre krawędzie i przezroczystość). */
    public function toWebp(GdImage $image, int $quality, bool $lossless = false): string
    {
        ob_start();
        $ok = imagewebp($image, null, $lossless ? IMG_WEBP_LOSSLESS : max(1, min(100, $quality)));
        $data = ob_get_clean();

        if (! $ok || $data === false || $data === '') {
            throw new InvalidArgumentException('Nie udało się zapisać obrazu jako WebP.');
        }

        return $data;
    }

    private function resample(GdImage $src, int $sx, int $sy, int $sw, int $sh, int $dw, int $dh): GdImage
    {
        $dst = imagecreatetruecolor($dw, $dh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh);

        return $dst;
    }

    private function autoOrient(GdImage $image, string $binary): GdImage
    {
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));
        $orientation = $exif['Orientation'] ?? 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }
}
