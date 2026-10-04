<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Message;
use App\Services\ReplyAddress;

class MentionsAndInboundTest extends PortalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['workora.inbound_domain' => 'in.example.com', 'workora.inbound_secret' => 's3cret']);
    }

    public function test_mentions_notify_only_people_in_the_conversation(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice Wonder');
        $this->actingAs($sam)->post(route('messages.store'), ['client_id' => $abc->id, 'body' => 'Hi @Alice, the draft is ready'])->assertRedirect();
        $this->assertSame(1, AppNotification::withoutGlobalScopes()->where('user_id', $alice->id)->where('type', 'mention')->count());

        // Not mentioned: no mention notification. Mentioning oneself does nothing.
        $this->actingAs($sam)->post(route('messages.store'), ['client_id' => $abc->id, 'body' => 'No names here, email@alice.com'])->assertRedirect();
        $this->assertSame(1, AppNotification::withoutGlobalScopes()->where('user_id', $alice->id)->where('type', 'mention')->count());

        $this->actingAs($alice)->post(route('portal.messages.store'), ['body' => 'Thanks @'.$sam->name])->assertRedirect();
        $this->assertSame(1, AppNotification::withoutGlobalScopes()->where('user_id', $sam->id)->where('type', 'mention')->count());
    }

    public function test_reply_addresses_are_signed(): void
    {
        $addr = ReplyAddress::make('u1', 'c1', null);
        $this->assertStringEndsWith('@in.example.com', $addr);
        $this->assertSame(['u1', 'c1', null], ReplyAddress::parse($addr));
        $this->assertNull(ReplyAddress::parse(str_replace('reply+', 'reply+x', $addr)));
        $this->assertNull(ReplyAddress::parse('reply+AAAA.0000000000000000@in.example.com'));
        $this->assertSame("Thanks\nsee you", ReplyAddress::stripQuoted("Thanks\nsee you\nOn Mon, 5 Jan 2026, Sam wrote:\n> old"));
    }

    public function test_an_emailed_reply_becomes_a_message(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $to = ReplyAddress::make($alice->id, $abc->id, null);

        $this->postJson(route('inbound.email'), ['to' => $to, 'from' => 'Alice <'.$alice->email.'>', 'text' => "Looks good\n> quoted"])->assertForbidden();
        $this->postJson(route('inbound.email', ['secret' => 's3cret']), ['to' => $to, 'from' => 'Alice <'.$alice->email.'>', 'text' => "Looks good, thanks\n\nOn Mon, 5 Jan 2026, Sam wrote:\n> quoted"])->assertOk()->assertJson(['ok' => true]);
        $m = Message::withoutGlobalScopes()->where('client_id', $abc->id)->firstOrFail();
        $this->assertSame('Looks good, thanks', $m->body);
        $this->assertSame($alice->id, $m->user_id);
        $this->assertSame(1, AppNotification::withoutGlobalScopes()->where('user_id', $sam->id)->where('type', 'message')->count());

        // A different sender cannot use the address; unknown addresses are ignored.
        $this->postJson(route('inbound.email', ['secret' => 's3cret']), ['to' => $to, 'from' => 'evil@example.org', 'text' => 'spoof'])->assertOk()->assertJson(['ok' => false]);
        $this->postJson(route('inbound.email', ['secret' => 's3cret']), ['to' => 'reply+nope@in.example.com', 'from' => $alice->email, 'text' => 'x'])->assertOk()->assertJson(['ok' => false]);
        $this->assertSame(1, Message::withoutGlobalScopes()->count());
    }
}
