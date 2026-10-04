<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Services\CalendarFeed;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public const VIEWS = ['month' => 'Month', 'week' => 'Week', 'day' => 'Day', 'agenda' => 'Agenda'];

    public function __construct(private Tenancy $tenancy) {}

    public function index(Request $request, CalendarFeed $feed): View
    {
        $view = array_key_exists($request->query('view'), self::VIEWS) ? $request->query('view') : 'month';
        try {
            $date = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        } catch (\Throwable) {
            $date = now()->startOfDay();
        }

        [$from, $to, $prev, $next, $label] = match ($view) {
            'week' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek(), $date->copy()->subWeek(), $date->copy()->addWeek(), $date->copy()->startOfWeek()->format('d M').' - '.$date->copy()->endOfWeek()->format('d M Y')],
            'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay(), $date->copy()->subDay(), $date->copy()->addDay(), $date->format('l, d M Y')],
            'agenda' => [$date->copy(), $date->copy()->addDays(30)->endOfDay(), $date->copy()->subDays(30), $date->copy()->addDays(30), 'Next 30 days from '.$date->format('d M')],
            default => [$date->copy()->startOfMonth()->startOfWeek(), $date->copy()->endOfMonth()->endOfWeek(), $date->copy()->subMonth(), $date->copy()->addMonth(), $date->format('F Y')],
        };

        $portal = $this->tenancy->isClient();
        $events = $feed->between($request->user(), $from, $to);

        return view('calendar.index', [
            'view' => $view, 'date' => $date, 'from' => $from, 'to' => $to, 'prev' => $prev, 'next' => $next, 'label' => $label,
            'events' => $events, 'byDay' => $events->groupBy(fn ($e) => $e['date']->toDateString()),
            'portal' => $portal,
            'clients' => $portal ? collect() : Client::query()->orderBy('name')->get(['id', 'name']),
            'projects' => $portal ? collect() : Project::query()->orderBy('name')->get(['id', 'name']),
            'views' => self::VIEWS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->tenancy->isStaff(), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'kind' => ['required', 'in:meeting,reminder'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'client_id' => ['nullable', 'uuid'],
            'project_id' => ['nullable', 'uuid'],
            'visible_to_client' => ['sometimes', 'boolean'],
        ]);
        $client = ! empty($data['client_id']) ? Client::query()->findOrFail($data['client_id']) : null;
        if (! empty($data['project_id'])) {
            $p = Project::query()->findOrFail($data['project_id']);
            abort_unless(! $client || $p->client_id === $client->id, 422, 'That project belongs to a different client.');
        }

        CalendarEvent::create([
            'created_by' => $request->user()->id, 'client_id' => $client?->id, 'project_id' => $data['project_id'] ?? null,
            'title' => $data['title'], 'kind' => $data['kind'],
            'starts_at' => Carbon::parse($data['date'].' '.($data['time'] ?? '09:00')),
            'visible_to_client' => $client !== null && $request->boolean('visible_to_client'),
        ]);

        return back()->with('status', 'Added to your calendar.');
    }

    public function destroy(Request $request, CalendarEvent $event): RedirectResponse
    {
        abort_unless($event->created_by === $request->user()->id, 404);
        $event->delete();

        return back()->with('status', 'Removed.');
    }
}
