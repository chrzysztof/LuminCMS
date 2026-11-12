<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_field_reads_formatted_values_per_field_group(): void
    {
        $this->seed(CmsSeeder::class);

        FieldGroup::create([
            'key' => 'group_oferta', 'title' => 'Oferta',
            'fields' => [
                ['type' => 'number', 'name' => 'cena'],
                ['type' => 'select', 'name' => 'czas', 'choices' => ['60' => '60 minut', '90' => '90 minut'], 'return_format' => 'label'],
                ['type' => 'date_picker', 'name' => 'od', 'return_format' => 'd.m.Y'],
            ],
            'location_rules' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'page']]],
        ]);

        $page = Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'),
            'title' => 'Oferta', 'slug' => 'oferta',
            'fields' => ['cena' => 80, 'czas' => '90', 'od' => '2026-11-12', 'obce' => 'x'],
        ]);
        $post = Content::create([
            'content_type_id' => ContentType::where('key', 'post')->value('id'),
            'title' => 'Wpis', 'slug' => 'wpis', 'fields' => ['cena' => 99],
        ]);

        $this->assertSame(80, $page->field('cena'));
        $this->assertSame('90 minut', $page->field('czas'));
        $this->assertSame('12.11.2026', $page->field('od'));
        $this->assertSame('brak', $page->field('obce', 'brak'));
        $this->assertSame('brak', $post->field('cena', 'brak'));
    }
}
