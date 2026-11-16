<?php

namespace Tests\Unit;

use App\Cms\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerEditorTest extends TestCase
{
    public function test_keeps_formatting_produced_by_the_editor(): void
    {
        $html = '<h2 class="lm-center">Title</h2><p class="lm-right">Text <mark>hi</mark> H<sub>2</sub>O x<sup>2</sup></p>'
            .'<table><tbody><tr><th colspan="1" rowspan="1"><p>A</p></th></tr><tr><td><p>1</p></td></tr></tbody></table>'
            .'<img src="/storage/a.webp" alt="A" width="800" height="600"><pre><code>echo 1;</code></pre>';

        $out = (new HtmlSanitizer)->clean($html);

        foreach (['<h2 class="lm-center">', '<p class="lm-right">', '<mark>hi</mark>', '<sub>2</sub>', '<sup>2</sup>', '<table>', '<img src="/storage/a.webp"', '<pre><code>'] as $needle) {
            $this->assertStringContainsString($needle, $out);
        }
    }

    public function test_strips_unknown_classes_styles_and_unsafe_links(): void
    {
        $out = (new HtmlSanitizer)->clean('<p class="evil lm-center" style="color:red" onclick="x()">a</p><a href="javascript:alert(1)">b</a><div class="lm-center">c</div>');

        $this->assertStringContainsString('<p class="lm-center">a</p>', $out);

        foreach (['style', 'onclick', 'javascript:', 'evil', '<div'] as $needle) {
            $this->assertStringNotContainsString($needle, $out);
        }
    }
}
