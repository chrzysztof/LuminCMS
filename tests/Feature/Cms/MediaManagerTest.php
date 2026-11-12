<?php

namespace Tests\Feature\Cms;

use App\Cms\Media\ImageProcessor;
use App\Cms\Media\Media;
use App\Cms\Media\MediaManager;
use App\Cms\Media\MediaSize;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class MediaManagerTest extends TestCase
{
    use RefreshDatabase;

    private MediaManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CmsSeeder::class);
        $this->manager = app(MediaManager::class);
    }

    private function upload(string $name = 'Zdjęcie Testowe.jpg', int $w = 2000, int $h = 1000): Media
    {
        return $this->manager->store(UploadedFile::fake()->image($name, $w, $h), ['alt' => 'Opis']);
    }

    public function test_upload_creates_webp_variants_for_every_size(): void
    {
        $media = $this->upload();

        $this->assertSame([2000, 1000], [$media->width, $media->height]);
        $this->assertEqualsCanonicalizing(['full', 'thumbnail', 'medium', 'large'], array_keys($media->variants));

        foreach ($media->variants as $variant) {
            Storage::disk('public')->assertExists($variant['path']);
            $this->assertStringEndsWith('.webp', $variant['path']);
            $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($variant['path']))['mime']);
        }

        $this->assertSame([150, 150], [$media->variants['thumbnail']['width'], $media->variants['thumbnail']['height']]);
        $this->assertSame([768, 384], [$media->variants['medium']['width'], $media->variants['medium']['height']]);
        $this->assertSame([1536, 768], [$media->variants['large']['width'], $media->variants['large']['height']]);
        Storage::disk('public')->assertExists($media->path);
        $this->assertStringContainsString('zdjecie-testowe-', $media->path);
    }

    public function test_sizes_larger_than_image_are_skipped_without_upscaling(): void
    {
        $media = $this->upload('mala.jpg', 600, 400);

        $this->assertEqualsCanonicalizing(['full', 'thumbnail'], array_keys($media->variants));
        $this->assertSame(600, $media->variants['full']['width']);
    }

    public function test_full_variant_is_capped_by_max_dimension(): void
    {
        config(['media.max_dimension' => 1000]);
        $media = $this->upload('duza.jpg', 3000, 1500);

        $this->assertSame([1000, 500], [$media->variants['full']['width'], $media->variants['full']['height']]);
    }

    public function test_png_is_encoded_losslessly_and_keeps_transparency(): void
    {
        $media = $this->manager->store(UploadedFile::fake()->image('logo.png', 800, 800));
        $webp = Storage::disk('public')->get($media->variants['full']['path']);

        // Bezstratny WebP ma w nagłówku fragment "VP8L".
        $this->assertStringContainsString('VP8L', substr($webp, 0, 32));
    }

    public function test_jpeg_is_encoded_lossy_webp(): void
    {
        $media = $this->upload();

        $this->assertStringContainsString('VP8 ', substr(Storage::disk('public')->get($media->variants['full']['path']), 0, 32));
    }

    public function test_non_images_are_stored_without_variants(): void
    {
        $media = $this->manager->store(UploadedFile::fake()->create('oferta.pdf', 100, 'application/pdf'));

        $this->assertNull($media->variants);
        $this->assertFalse($media->isImage());
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_disallowed_types_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->store(UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'));
    }

    public function test_custom_crop_changes_only_that_variant(): void
    {
        $media = $this->upload();
        $before = $media->variants['medium']['path'];

        $media = $this->manager->setCrop($media, 'thumbnail', ['x' => 100, 'y' => 100, 'width' => 400, 'height' => 400]);

        $this->assertSame(['x' => 100, 'y' => 100, 'width' => 400, 'height' => 400], $media->crops['thumbnail']);
        $this->assertSame([150, 150], [$media->variants['thumbnail']['width'], $media->variants['thumbnail']['height']]);
        $this->assertSame($before, $media->variants['medium']['path']);
    }

    public function test_crop_outside_image_or_for_non_cropped_size_is_rejected(): void
    {
        $media = $this->upload();

        $this->expectException(InvalidArgumentException::class);
        $this->manager->setCrop($media, 'thumbnail', ['x' => 1900, 'y' => 0, 'width' => 400, 'height' => 400]);
    }

    public function test_crop_rejected_for_size_without_crop(): void
    {
        $media = $this->upload();

        $this->expectException(InvalidArgumentException::class);
        $this->manager->setCrop($media, 'medium', ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100]);
    }

    public function test_focal_point_rect_stays_inside_image_and_follows_focus(): void
    {
        $processor = app(ImageProcessor::class);

        $left = $processor->focalRect(2000, 1000, 1, 1, 0.0, 0.5);
        $right = $processor->focalRect(2000, 1000, 1, 1, 1.0, 0.5);
        $middle = $processor->focalRect(2000, 1000, 1, 1, 0.5, 0.5);

        $this->assertSame(['x' => 0, 'y' => 0, 'width' => 1000, 'height' => 1000], $left);
        $this->assertSame(1000, $right['x']);
        $this->assertSame(500, $middle['x']);
    }

    public function test_focal_point_is_saved_and_clamped(): void
    {
        $media = $this->manager->setFocalPoint($this->upload(), 1.7, -3);

        $this->assertSame([1.0, 0.0], [$media->focal_x, $media->focal_y]);
    }

    public function test_regenerate_adds_new_size_and_drops_removed_one(): void
    {
        $media = $this->upload();
        MediaSize::create(['name' => 'baner', 'label' => 'Baner', 'width' => 1200, 'height' => 400, 'crop' => true, 'active' => true]);
        MediaSize::where('name', 'large')->update(['active' => false]);

        $media = $this->manager->regenerate($media);

        $this->assertArrayHasKey('baner', $media->variants);
        $this->assertSame([1200, 400], [$media->variants['baner']['width'], $media->variants['baner']['height']]);
        $this->assertArrayNotHasKey('large', $media->variants);
    }

    public function test_delete_removes_all_files(): void
    {
        $media = $this->upload();
        $paths = [$media->path, ...array_column($media->variants, 'path')];

        $this->manager->delete($media);

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        $this->assertDatabaseCount('media', 0);
    }

    public function test_picture_renders_semantic_markup(): void
    {
        $media = $this->upload();
        $html = (string) $media->picture('large', '(min-width: 768px) 50vw, 100vw');

        $this->assertStringStartsWith('<picture><source type="image/webp"', $html);
        $this->assertStringContainsString('alt="Opis"', $html);
        $this->assertStringContainsString('width="1536" height="768"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('150w', $html);
        $this->assertStringContainsString('sizes="(min-width: 768px) 50vw, 100vw"', $html);
    }

    public function test_picture_escapes_alt_text(): void
    {
        $media = $this->upload();
        $media->alt = '"><script>x</script>';

        $this->assertStringNotContainsString('<script>', (string) $media->picture());
    }

    public function test_url_falls_back_to_full_for_missing_size(): void
    {
        $media = $this->upload('mala.jpg', 600, 400);

        $this->assertSame($media->url('full'), $media->url('large'));
    }
}
