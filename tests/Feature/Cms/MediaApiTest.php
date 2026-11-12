<?php

namespace Tests\Feature\Cms;

use App\Cms\Media\Media;
use App\Cms\Media\MediaSize;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CmsSeeder::class);
    }

    public function test_guests_cannot_use_media_api(): void
    {
        $this->getJson('/cms/media')->assertUnauthorized();
        $this->postJson('/cms/media')->assertUnauthorized();
    }

    public function test_upload_list_update_and_delete(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson('/cms/media', [
            'file' => UploadedFile::fake()->image('baner.jpg', 1600, 900),
            'alt' => 'Baner',
        ])->assertCreated()->assertJsonPath('alt', 'Baner')->assertJsonPath('width', 1600)->json('id');

        $this->getJson('/cms/media?search=baner&type=image')->assertOk()->assertJsonPath('meta.total', 1);

        $this->patchJson("/cms/media/{$id}", ['alt' => 'Nowy opis', 'focal_x' => 0.2, 'focal_y' => 0.8])
            ->assertOk()->assertJsonPath('alt', 'Nowy opis')->assertJsonPath('focal_x', 0.2);

        $this->deleteJson("/cms/media/{$id}")->assertNoContent();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_upload_rejects_disallowed_file(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/cms/media', [
            'file' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_crop_endpoint_validates_bounds(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson('/cms/media', ['file' => UploadedFile::fake()->image('a.jpg', 1000, 800)])->json('id');

        $this->putJson("/cms/media/{$id}/crops/thumbnail", ['x' => 0, 'y' => 0, 'width' => 500, 'height' => 500])
            ->assertOk()->assertJsonPath('crops.thumbnail.width', 500);

        $this->putJson("/cms/media/{$id}/crops/thumbnail", ['x' => 800, 'y' => 0, 'width' => 500, 'height' => 500])
            ->assertUnprocessable()->assertJsonValidationErrors('crop');

        $this->deleteJson("/cms/media/{$id}/crops/thumbnail")->assertOk();
        $this->assertNull(Media::find($id)->crops);
    }

    public function test_sizes_crud_and_regenerate(): void
    {
        $this->actingAs(User::factory()->create());
        Queue::fake();

        $this->getJson('/cms/media-sizes')->assertOk()->assertJsonCount(3, 'data');

        $this->postJson('/cms/media-sizes', ['name' => 'baner', 'label' => 'Baner', 'width' => 1200, 'height' => 400, 'crop' => true])->assertCreated();
        $this->postJson('/cms/media-sizes', ['name' => 'full', 'label' => 'X', 'width' => 10, 'height' => 10])->assertUnprocessable();
        $this->postJson('/cms/media-sizes', ['name' => 'zly', 'label' => 'Zły', 'width' => 100, 'height' => 0, 'crop' => true])->assertUnprocessable();

        $size = MediaSize::where('name', 'baner')->first();
        $this->patchJson("/cms/media-sizes/{$size->id}", ['width' => 1000])->assertOk()->assertJsonPath('width', 1000);
        $this->deleteJson("/cms/media-sizes/{$size->id}")->assertNoContent();

        $system = MediaSize::where('name', 'medium')->first();
        $this->deleteJson("/cms/media-sizes/{$system->id}")->assertUnprocessable();

        $this->postJson('/cms/media-sizes/regenerate')->assertStatus(202)->assertJsonPath('queued', 0);
    }
}
