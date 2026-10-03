<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\File;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $projects = Project::query()->visibleTo($user)
            ->with('client:id,name', 'projectManager:id,name')
            ->withCount([
                'tasks as tasks_total' => fn ($q) => $q->visibleTo($user)->where('status', '!=', 'cancelled'),
                'tasks as tasks_done' => fn ($q) => $q->visibleTo($user)->where('status', 'approved'),
                'members',
            ])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when(in_array($status, Project::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("case status when 'active' then 0 when 'planning' then 1 when 'on_hold' then 2 else 3 end")
            ->latest()
            ->paginate(24)->withQueryString();

        return view('projects.index', ['projects' => $projects, 'search' => $search, 'status' => $status]);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.form', $this->formData(new Project(['status' => 'planning', 'currency' => 'INR'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $data = $this->validated($request);
        $project = Project::create($data + [
            'slug' => $this->uniqueSlug($data['name']),
            'created_by' => $request->user()->id,
        ]);

        // The creator and the manager must be able to see what they just made.
        foreach (array_unique(array_filter([$request->user()->id, $project->project_manager_id])) as $userId) {
            ProjectMember::firstOrCreate(['project_id' => $project->id, 'user_id' => $userId], ['role_in_project' => 'manager', 'can_view_budget' => true]);
        }

        return redirect()->route('projects.show', $project)->with('status', 'Project created. Add your team and tasks next.');
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $user = $request->user();

        $tasks = $project->tasks()->visibleTo($user)->with('assignees:id,name')->orderBy('position')->orderBy('created_at')->get();
        $columns = [
            'To do' => ['backlog', 'assigned'],
            'In progress' => ['in_progress'],
            'In review' => ['submitted', 'under_review'],
            'Needs changes' => ['revision_required'],
            'Done' => ['approved'],
        ];

        $minutes = (int) TimeEntry::query()->where('project_id', $project->id)
            ->when($this->isFreelancer(), fn ($q) => $q->where('user_id', $user->id))->sum('minutes');
        $financials = $user->can('viewFinancials', $project);

        $memberUserIds = $project->members()->pluck('user_id');

        return view('projects.show', [
            'project' => $project->load('client', 'projectManager'),
            'tasks' => $tasks,
            'columns' => $columns,
            'members' => $project->members()->with('user:id,name,email')->get(),
            'candidates' => $user->can('manageMembers', $project)
                ? OrganizationMember::query()->with('user:id,name,email')->where('status', 'active')->whereNotIn('user_id', $memberUserIds)->get()
                : collect(),
            'minutes' => $minutes,
            'costMinor' => $financials ? $project->trackedCostMinor() : null,
            'files' => File::query()->visibleTo($user)->where('project_id', $project->id)->latest()->limit(8)->get(),
            'canEdit' => $user->can('update', $project),
            'canCreateTask' => $user->can('create', Task::class),
            'financials' => $financials,
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.form', $this->formData($project));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $project->update($this->validated($request));

        if ($project->project_manager_id) {
            ProjectMember::firstOrCreate(['project_id' => $project->id, 'user_id' => $project->project_manager_id], ['role_in_project' => 'manager', 'can_view_budget' => true]);
        }

        return redirect()->route('projects.show', $project)->with('status', 'Project saved.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project archived.');
    }

    public function addMember(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $data = $request->validate([
            'user_id' => ['required', 'uuid'],
            'role_in_project' => ['nullable', 'string', 'max:60'],
            'can_view_budget' => ['nullable', 'boolean'],
        ]);

        // Only people who belong to this company can be added.
        abort_unless(OrganizationMember::where('user_id', $data['user_id'])->where('status', 'active')->exists(), 422, 'That person is not in this company.');

        ProjectMember::updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $data['user_id']],
            ['role_in_project' => $data['role_in_project'] ?? null, 'can_view_budget' => $request->boolean('can_view_budget')],
        );

        return back()->with('status', 'Added to the project.');
    }

    public function removeMember(Project $project, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        ProjectMember::where('project_id', $project->id)->where('user_id', $user->id)->delete();
        // Taking someone off the project also takes them off its tasks.
        foreach ($project->tasks()->get() as $task) {
            $task->assignees()->detach($user->id);
        }

        return back()->with('status', 'Removed from the project.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'client_id' => ['nullable', 'uuid', Rule::exists('clients', 'id')->where('organization_id', app(Tenancy::class)->id())],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', Rule::in(array_keys(Money::CURRENCIES))],
            'project_manager_id' => ['nullable', 'uuid'],
        ]);

        if (! empty($data['project_manager_id'])) {
            abort_unless(OrganizationMember::where('user_id', $data['project_manager_id'])->where('member_type', 'employee')->where('status', 'active')->exists(), 422, 'The manager must be a member of your team.');
        }

        $data['budget_minor'] = Money::toMinor($data['budget'] ?? null);
        unset($data['budget']);

        return $data;
    }

    private function formData(Project $project): array
    {
        return [
            'project' => $project,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'managers' => OrganizationMember::query()->with('user:id,name')->where('member_type', 'employee')->where('status', 'active')->get(),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;

        while (Project::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    private function isFreelancer(): bool
    {
        return app(Tenancy::class)->isFreelancer();
    }
}
