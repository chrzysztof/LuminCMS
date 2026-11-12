<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledPublishingTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $slug, ?\DateTimeInterface $at, string $status = 'scheduled'): Content
    {
        return Content::create([
            'content_type_id' => ContentType::where('key', 'post')->value('id'),
            'title' => $slug, 'slug' => $slug, 'status' => $status, 'published_at' => $at, 'fields' => [],
        ]);
    }

    public function test_command_publishes_only_due_content(): void
    {
        $this->seed(CmsSeeder::class);
        $due = $this->make('due', now()->subMinute());
        $later = $this->make('later', now()->addHour());
        $draft = $this->make('draft', now()->subDay(), 'draft');

        $this->artisan('cms:publish-scheduled')->assertSuccessful();

        $this->assertSame('published', $due->refresh()->status);
        $this->assertSame('scheduled', $later->refresh()->status);
        $this->assertSame('draft', $draft->refresh()->status);
    }

    public function test_scheduled_content_is_public_only_after_its_date(): void
    {
        $this->seed(CmsSeeder::class);
        $this->make('przyszly', now()->addHour());
        $this->make('minal', now()->subMinute());

        $this->get('/blog/przyszly')->assertNotFound();
        $this->get('/blog/minal')->assertOk();
    }

    public function test_command_is_scheduled_every_minute(): void
    {
        $events = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');
        $this->assertStringContainsString('cms:publish-scheduled', $events);
    }

    public function test_scheduling_requires_future_date(): void
    {
        $this->seed(CmsSeeder::class);
        $this->actingAs(User::factory()->create());

        $payload = ['title' => 'Plan', 'status' => 'scheduled', 'fields' => []];

        $this->post(route('cms.content.store', 'post'), $payload + ['published_at' => now()->subDay()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('published_at');

        $this->post(route('cms.content.store', 'post'), $payload + ['published_at' => now()->addDay()->format('Y-m-d\TH:i')])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('scheduled', Content::where('title', 'Plan')->value('status'));
    }
}
