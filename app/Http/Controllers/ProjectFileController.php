<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\File;
use App\Models\Project;
use App\Services\ClientContext;
use App\Services\FileLibrary;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Staff side: put files on a project or a client, and choose which ones the client may see. */
class ProjectFileController extends Controller
{
    public const FOLDERS = ['Reports', 'Research', 'Creative', 'Client Files', 'Deliverables', 'Documents', 'Other'];

    public function storeForProject(Request $request, Project $project, FileLibrary $library, ClientContext $ctx, Notifier $notifier): RedirectResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', File::class);

        $saved = $this->save($request, $library, $project, $project->client_id);

        if ($saved && $request->boolean('visible_to_client') && $project->client_id) {
            $client = Client::query()->find($project->client_id);
            $notifier->send($ctx->clientUsers($client), 'file', 'New file on '.$project->name, $saved.' file(s) shared with you', route('portal.files.index'), $request->user());
        }

        return back()->with($saved ? 'status' : 'error', $saved ? $saved.' file(s) uploaded.' : 'No file was uploaded.');
    }

    public function storeForClient(Request $request, Client $client, FileLibrary $library): RedirectResponse
    {
        $this->authorize('staff');
        $this->authorize('create', File::class);
        $saved = $this->save($request, $library, null, $client->id);

        return back()->with($saved ? 'status' : 'error', $saved ? $saved.' file(s) uploaded.' : 'No file was uploaded.');
    }

    /** Share a file with the client, or take it back. */
    public function toggleClient(File $file): RedirectResponse
    {
        $this->authorize('update', $file);
        abort_unless($file->client_id, 422, 'This file is not linked to a client.');
        $file->update(['visible_to_client' => ! $file->visible_to_client]);
        AuditLog::record($file->visible_to_client ? 'file.shared' : 'file.unshared', $file, ['project_id' => $file->project_id, 'client_id' => $file->client_id]);

        return back()->with('status', $file->visible_to_client ? 'Shared with the client.' : 'No longer shared with the client.');
    }

    private function save(Request $request, FileLibrary $library, ?Project $project, ?string $clientId): int
    {
        $maxKb = config('workora.max_upload_mb') * 1024;
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', "max:{$maxKb}"],
            'folder' => ['nullable', 'string', 'max:60'],
            'visible_to_client' => ['sometimes', 'boolean'],
        ]);

        $n = 0;
        foreach ($request->file('files') as $upload) {
            if ($library->isBlocked($upload->getClientOriginalName())) {
                continue;
            }
            $file = $library->storeUpload($upload, $request->user(), $request->input('folder'), [
                'project_id' => $project?->id, 'client_id' => $clientId,
                'visible_to_client' => $clientId !== null && $request->boolean('visible_to_client'),
            ]);
            AuditLog::record('file.uploaded', $file, ['project_id' => $project?->id, 'client_id' => $clientId]);
            $n++;
        }

        return $n;
    }
}
