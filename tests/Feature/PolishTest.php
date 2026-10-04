<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\TimeEntry;

class PolishTest extends PortalTestCase
{
    public function test_hours_report_only_shows_shared_projects_to_the_client(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');
        $shared = $this->makeProject($abc, $sam, 'Shared Hours Project');
        $hidden = $this->makeProject($abc, $sam, 'Hidden Hours Project');
        $other = $this->makeProject($xyz, $sam, 'Other Client Project');
        Project::withoutGlobalScopes()->whereKey($shared->id)->update(['share_hours' => true]);
        Project::withoutGlobalScopes()->whereKey($other->id)->update(['share_hours' => true]);
        foreach ([[$shared, '2'], [$hidden, '3'], [$other, '4']] as [$p, $d]) {
            $this->actingAs($sam)->post(route('time.store'), ['project_id' => $p->id, 'entry_date' => now()->toDateString(), 'duration' => $d, 'description' => 'Work on '.$p->name, 'is_billable' => 1])->assertRedirect();
        }
        $this->assertSame(3, TimeEntry::withoutGlobalScopes()->count());
        $alice = $this->portalUser($sam, $abc, 'Alice');

        $res = $this->actingAs($alice)->get(route('portal.hours'))->assertOk();
        $res->assertSee('Work on Shared Hours Project')->assertDontSee('Work on Hidden Hours Project')->assertDontSee('Work on Other Client Project');
        $this->actingAs($alice)->get(route('portal.hours', ['project' => $hidden->id]))->assertOk()->assertDontSee('Work on Hidden Hours Project');
        $this->actingAs($alice)->get(route('portal.hours', ['month' => now()->format('Y-m')]))->assertOk()->assertSee('Work on Shared Hours Project');
    }

    public function test_emails_are_branded_html_with_a_text_part(): void
    {
        config(['mail.default' => 'array']);
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $this->actingAs($sam)->post(route('clients.invite', $abc))->assertRedirect();

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages[0]->getOriginalMessage();
        $this->assertStringContainsString('Open my portal', $email->getHtmlBody());
        $this->assertStringContainsString('background:#c2410c', $email->getHtmlBody());
        $this->assertStringContainsString('Open my portal: ', $email->getTextBody());
    }

    public function test_invoice_project_picker_is_filtered_by_client(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $this->makeProject($abc, $sam, 'Picker Project');
        $this->actingAs($sam)->get(route('invoices.create'))->assertOk()->assertSee('Choose a client to see their projects')->assertSee('Picker Project');
    }
}
