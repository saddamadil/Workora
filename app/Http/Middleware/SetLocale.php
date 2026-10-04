<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uses the signed-in person's language, when it is one we have text for. */
class SetLocale
{
    public const SUPPORTED = ['en' => 'English', 'de' => 'Deutsch', 'hi' => 'हिन्दी', 'ar' => 'العربية', 'tr' => 'Türkçe'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->getAttributes()['locale'] ?? null;
        $locale = $locale && array_key_exists($locale, self::SUPPORTED) ? $locale : config('app.locale');
        app()->setLocale($locale);
        \Illuminate\Support\Carbon::setLocale($locale);

        return $next($request);
    }
}
