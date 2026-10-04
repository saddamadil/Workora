<?php

namespace App\Support;

/**
 * Translates the fixed English phrases of a rendered page.
 *
 * Only a text node (or a placeholder, title, aria-label or alt attribute) that is exactly one known phrase
 * is replaced. Anything with a name, number or other user data mixed in is left alone, so nobody's own
 * words are ever rewritten by accident. Scripts, styles, text areas and code blocks are never touched.
 * Phrase files live in lang/{locale}/phrases.json as {"English phrase": "translation"}.
 */
class Phrases
{
    /** @var array<string, array<string,string>> */
    private static array $cache = [];

    public static function load(string $locale): array
    {
        if (! isset(self::$cache[$locale])) {
            $file = lang_path($locale.'/phrases.json');
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            self::$cache[$locale] = is_array($data) ? $data : [];
        }

        return self::$cache[$locale];
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function translateHtml(string $html, array $map): string
    {
        if ($map === []) {
            return $html;
        }

        $parts = preg_split('/(<!--.*?-->|<script\b.*?<\/script>|<style\b.*?<\/style>|<textarea\b.*?<\/textarea>|<pre\b.*?<\/pre>|<code\b.*?<\/code>|<[^>]+>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                if (preg_match('/^<(?:!--|script|style|textarea|pre|code)/i', $part)) {
                    continue;
                }
                $parts[$i] = preg_replace_callback('/(\s(?:placeholder|title|aria-label|alt)=)(["\'])(.*?)\2/is', function ($m) use ($map) {
                    $key = self::normalise(html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5));

                    return isset($map[$key]) ? $m[1].$m[2].htmlspecialchars($map[$key], ENT_QUOTES).$m[2] : $m[0];
                }, $part);

                continue;
            }
            $key = self::normalise(html_entity_decode($part, ENT_QUOTES | ENT_HTML5));
            if ($key !== '' && isset($map[$key])) {
                preg_match('/^\s*/', $part, $lead);
                preg_match('/\s*$/', $part, $trail);
                $parts[$i] = $lead[0].htmlspecialchars($map[$key], ENT_NOQUOTES).$trail[0];
            }
        }

        return implode('', $parts);
    }

    private static function normalise(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
