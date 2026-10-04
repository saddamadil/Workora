<?php

namespace App\Http\Middleware;

use App\Support\Phrases;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** For non-English languages, swaps the fixed English phrases in the finished page for their translation. */
class TranslatePhrases
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $locale = app()->getLocale();

        if ($locale === 'en' || $request->expectsJson() || $response->isRedirection() || $response->getStatusCode() === 204) {
            return $response;
        }
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html') || $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse || $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return $response;
        }

        $content = $response->getContent();
        if (is_string($content) && $content !== '') {
            $response->setContent(Phrases::translateHtml($content, Phrases::load($locale)));
        }

        return $response;
    }
}
