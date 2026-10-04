<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;

/** Every transactional email goes through here: one branded HTML layout with a plain-text twin. */
class BrandedMail
{
    /**
     * @param  array<string,string>  $facts  label => value rows shown in a small table
     * @param  callable|null  $tap  receives the message to add attachments, reply-to, etc.
     */
    public static function send(string $to, string $subject, string $heading, string $body, ?string $button = null, ?string $url = null, array $facts = [], ?string $footer = null, ?callable $tap = null): void
    {
        $data = ['heading' => $heading, 'body' => $body, 'button' => $button, 'url' => $url ? url($url) : null, 'facts' => $facts, 'footer' => $footer];

        Mail::send(['html' => 'emails.branded', 'text' => 'emails.branded-text'], $data, function ($m) use ($to, $subject, $tap) {
            $m->to($to)->subject($subject);
            if ($tap) {
                $tap($m);
            }
        });
    }
}
