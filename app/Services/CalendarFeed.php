<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Everything with a date on it, in one list: task deadlines, project deadlines, milestones, invoice due dates and meetings. */
class CalendarFeed
{
    public function __construct(private Tenancy $tenancy) {}

    /** @return Collection<int, array{date: Carbon, title: string, kind: string, url: string|null, time: string|null}> */
    public function between(User $user, Carbon $from, Carbon $to): Collection
    {
        $clientId = $this->tenancy->clientId();
        $portal = $clientId !== null;
        $out = collect();

        $tasks = Task::query()->with('project:id,name,slug,client_id')->whereNotIn('status', ['approved', 'cancelled'])->whereBetween('due_at', [$from, $to])
            ->when($portal, fn ($q) => $q->where('is_internal', false)->whereHas('project', fn ($p) => $p->where('client_id', $clientId)), fn ($q) => $q->visibleTo($user))->get();
        foreach ($tasks as $t) {
            $out->push(['date' => $t->due_at, 'title' => $t->title, 'kind' => 'task', 'time' => null,
                'url' => $portal ? route('portal.project', [$t->project->slug, 'tab' => 'tasks']) : route('tasks.show', $t)]);
        }

        $projects = Project::query()->whereNotIn('status', ['completed', 'cancelled'])->whereBetween('deadline', [$from->toDateString(), $to->toDateString()])
            ->when($portal, fn ($q) => $q->where('client_id', $clientId), fn ($q) => $q->visibleTo($user))->get(['id', 'name', 'slug', 'deadline', 'client_id']);
        foreach ($projects as $p) {
            $out->push(['date' => $p->deadline, 'title' => $p->name.' deadline', 'kind' => 'project', 'time' => null, 'url' => $portal ? route('portal.project', $p->slug) : route('projects.show', $p)]);
        }

        $visibleProjectIds = $portal ? Project::query()->where('client_id', $clientId)->pluck('id') : Project::query()->visibleTo($user)->pluck('id');
        $milestones = ProjectMilestone::query()->with('project:id,name,slug')->whereIn('project_id', $visibleProjectIds)->where('status', '!=', 'completed')
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])->get();
        foreach ($milestones as $m) {
            $out->push(['date' => $m->due_date, 'title' => $m->project->name.': '.$m->title, 'kind' => 'milestone', 'time' => null,
                'url' => $portal ? route('portal.project', [$m->project->slug, 'tab' => 'milestones']) : route('projects.show', [$m->project, 'tab' => 'milestones'])]);
        }

        $canSeeInvoices = $portal || $this->tenancy->issuesOwnInvoices() || $this->tenancy->role()?->seesMoney();
        if ($canSeeInvoices) {
            $invoices = Invoice::query()->whereIn('status', ['submitted', 'under_review', 'approved', 'partially_paid'])->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
                ->when($portal, fn ($q) => $q->where('client_id', $clientId))->get();
            foreach ($invoices as $i) {
                $out->push(['date' => $i->due_date, 'title' => 'Invoice '.$i->number.' due', 'kind' => 'invoice', 'time' => null, 'url' => $portal ? route('portal.invoice', $i) : route('invoices.show', $i)]);
            }
        }

        $events = CalendarEvent::query()->whereBetween('starts_at', [$from, $to])
            ->when($portal, fn ($q) => $q->where('client_id', $clientId)->where('visible_to_client', true), fn ($q) => $q->where('created_by', $user->id))->get();
        foreach ($events as $e) {
            $out->push(['date' => $e->starts_at, 'title' => $e->title, 'kind' => $e->kind, 'time' => $e->starts_at->format('H:i'), 'url' => null, 'id' => $e->id]);
        }

        return $out->sortBy(fn ($e) => $e['date']->timestamp)->values();
    }
}
