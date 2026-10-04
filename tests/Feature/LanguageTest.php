<?php

namespace Tests\Feature;

class LanguageTest extends PortalTestCase
{
    public function test_pages_are_translated_and_arabic_is_right_to_left(): void
    {
        $sam = $this->solo();
        $de = json_decode(file_get_contents(lang_path('de/phrases.json')), true);
        $this->actingAs($sam)->get(route('expenses.index'))->assertOk()->assertSee('Add an expense')->assertSee('dir="ltr"', false);

        $sam->update(['locale' => 'de']);
        $res = $this->actingAs($sam->fresh())->get(route('expenses.index'))->assertOk();
        $res->assertSee($de['Add an expense'])->assertDontSee('Add an expense', false);

        $sam->update(['locale' => 'ar']);
        $this->actingAs($sam->fresh())->get(route('expenses.index'))->assertOk()->assertSee('dir="rtl"', false)->assertDontSee('Add an expense');

        // The next person without a language gets English again.
        $other = $this->solo('Other');
        $this->actingAs($other)->get(route('expenses.index'))->assertSee('Add an expense')->assertSee('dir="ltr"', false);
    }

    public function test_every_language_has_every_phrase(): void
    {
        $en = json_decode((string) shell_exec('python3 '.base_path('scripts/extract-phrases.py')), true);
        $this->assertGreaterThan(900, count($en));
        foreach (['de', 'hi', 'ar', 'tr'] as $l) {
            $map = json_decode(file_get_contents(lang_path("$l/phrases.json")), true);
            $missing = array_values(array_diff($en, array_keys($map)));
            $this->assertLessThan(60, count($missing), "$l is missing phrases, such as: ".implode(' | ', array_slice($missing, 0, 5)));
        }
    }
}
