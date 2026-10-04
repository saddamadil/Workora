<?php

namespace Tests\Unit;

use App\Support\Phrases;
use PHPUnit\Framework\TestCase;

class PhrasesTest extends TestCase
{
    private array $map = ['Add a rate' => 'Einen Kurs hinzufügen', 'Search files' => 'Dateien suchen', "Don't" => 'Nicht'];

    public function test_whole_phrases_are_replaced_and_user_data_is_not(): void
    {
        $html = '<h1>  Add a rate </h1><p>Add a rate for Acme</p><span>Acme</span><input placeholder="Search files" class="x"><td>Add a rate</td>';
        $out = Phrases::translateHtml($html, $this->map);
        $this->assertStringContainsString('<h1>  Einen Kurs hinzufügen </h1>', $out, 'spacing is kept');
        $this->assertStringContainsString('<p>Add a rate for Acme</p>', $out, 'mixed text is left alone');
        $this->assertStringContainsString('placeholder="Dateien suchen"', $out);
        $this->assertStringContainsString('<td>Einen Kurs hinzufügen</td>', $out);
    }

    public function test_scripts_styles_textareas_and_code_are_never_touched(): void
    {
        $html = '<script>var a = "Add a rate";</script><textarea>Add a rate</textarea><style>.a{content:"Add a rate"}</style><pre>Add a rate</pre><code>Add a rate</code><!-- Add a rate -->';
        $this->assertSame($html, Phrases::translateHtml($html, $this->map));
    }

    public function test_entities_and_attributes(): void
    {
        $out = Phrases::translateHtml('<button title="Don&#039;t">Don&#039;t</button>', $this->map);
        $this->assertSame('<button title="Nicht">Nicht</button>', $out);
        $this->assertSame('<p>x</p>', Phrases::translateHtml('<p>x</p>', []));
    }
}
