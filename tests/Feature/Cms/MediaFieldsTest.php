<?php

namespace Tests\Feature\Cms;

use App\Cms\Fields\FieldRegistry;
use App\Cms\Fields\FieldValidator;
use App\Cms\Media\MediaManager;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MediaFieldsTest extends TestCase
{
    use RefreshDatabase;

    private FieldValidator $validator;

    private FieldRegistry $registry;

    private MediaManager $media;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CmsSeeder::class);
        $this->validator = app(FieldValidator::class);
        $this->registry = app(FieldRegistry::class);
        $this->media = app(MediaManager::class);
    }

    private function errors(array $fields, array $data): ?array
    {
        try {
            $this->validator->validate($fields, $data);

            return null;
        } catch (ValidationException $e) {
            return $e->errors();
        }
    }

    public function test_image_field_validates_existence_and_type(): void
    {
        $image = $this->media->store(UploadedFile::fake()->image('a.jpg', 800, 600));
        $pdf = $this->media->store(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));
        $fields = [['type' => 'image', 'name' => 'okladka']];

        $this->assertNull($this->errors($fields, ['okladka' => $image->id]));
        $this->assertArrayHasKey('okladka', $this->errors($fields, ['okladka' => 9999]));
        $this->assertArrayHasKey('okladka', $this->errors($fields, ['okladka' => $pdf->id]));
    }

    public function test_image_dimension_limits(): void
    {
        $image = $this->media->store(UploadedFile::fake()->image('a.jpg', 800, 600));

        $this->assertArrayHasKey('o', $this->errors([['type' => 'image', 'name' => 'o', 'min_width' => 1000]], ['o' => $image->id]));
        $this->assertArrayHasKey('o', $this->errors([['type' => 'image', 'name' => 'o', 'max_height' => 500]], ['o' => $image->id]));
        $this->assertNull($this->errors([['type' => 'image', 'name' => 'o', 'min_width' => 800, 'max_height' => 600]], ['o' => $image->id]));
    }

    public function test_image_return_formats(): void
    {
        $image = $this->media->store(UploadedFile::fake()->image('a.jpg', 2000, 1000));
        $type = $this->registry->get('image');
        $base = ['type' => 'image', 'name' => 'o'];

        $this->assertSame($image->id, $type->format($image->id, $this->registry->resolveField($base + ['return_format' => 'id'])));
        $this->assertSame($image->url('medium'), $type->format($image->id, $this->registry->resolveField($base + ['return_format' => 'url', 'size' => 'medium'])));

        $array = $type->format($image->id, $this->registry->resolveField($base));
        $this->assertSame($image->id, $array['id']);
        $this->assertSame(2000, $array['width']);
        $this->assertNull($type->format(null, $this->registry->resolveField($base)));
    }

    public function test_file_field_extension_and_size_limits(): void
    {
        $pdf = $this->media->store(UploadedFile::fake()->create('a.pdf', 3000, 'application/pdf'));
        $field = ['type' => 'file', 'name' => 'f'];

        $this->assertNull($this->errors([$field + ['mime_types' => 'pdf, docx']], ['f' => $pdf->id]));
        $this->assertArrayHasKey('f', $this->errors([$field + ['mime_types' => 'docx']], ['f' => $pdf->id]));
        $this->assertArrayHasKey('f', $this->errors([$field + ['max_size' => 1]], ['f' => $pdf->id]));
    }

    public function test_gallery_keeps_order_removes_duplicates_and_enforces_limits(): void
    {
        $a = $this->media->store(UploadedFile::fake()->image('a.jpg', 800, 600));
        $b = $this->media->store(UploadedFile::fake()->image('b.jpg', 800, 600));
        $field = ['type' => 'gallery', 'name' => 'g', 'max' => 2, 'min' => 1];

        $out = $this->validator->validate([$field], ['g' => [(string) $b->id, $a->id]]);
        $this->assertSame([$b->id, $a->id], $out['g']);

        $resolved = $this->registry->resolveField($field);
        $this->assertSame([$b->id, $a->id], $this->registry->get('gallery')->normalize([$b->id, $a->id, $b->id], $resolved));

        $c = $this->media->store(UploadedFile::fake()->image('c.jpg', 800, 600));
        $this->assertArrayHasKey('g', $this->errors([$field], ['g' => [$a->id, $b->id, $c->id]]));
        $this->assertArrayHasKey('g.1', $this->errors([$field], ['g' => [$a->id, 9999]]));
    }

    public function test_gallery_format_skips_deleted_media_and_keeps_order(): void
    {
        $a = $this->media->store(UploadedFile::fake()->image('a.jpg', 800, 600));
        $b = $this->media->store(UploadedFile::fake()->image('b.jpg', 800, 600));
        $type = $this->registry->get('gallery');
        $field = $this->registry->resolveField(['type' => 'gallery', 'name' => 'g', 'return_format' => 'id']);

        $this->assertSame([$b->id, $a->id], $type->format([$b->id, $a->id], $field));

        $this->media->delete($b);
        $this->assertSame([$a->id], $type->format([$b->id, $a->id], $field));
        $this->assertSame([], $type->format(null, $field));
    }
}
