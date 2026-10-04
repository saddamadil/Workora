<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\Project;
use App\Services\ClientContext;
use App\Services\FileLibrary;
use App\Services\Notifier;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A client's files: what the freelancer shared with them, and what they upload back. */
class PortalFileController extends Controller
{
    public function __construct(private Tenancy $tenancy, private ClientContext $ctx) {}

    private function shared(Request $request)
    {
        $client = $this->ctx->client($request);

        return [$client, File::query()->current()->where('client_id', $client->id)->where('visible_to_client', true)];
    }

    public function index(Request $request): View
    {
        [$client, $query] = $this->shared($request);
        $files = $query->with('project:id,name', 'uploadedBy:id,name')->latest()->get();

        return view('portal.files', [
            'files' => $files,
            'projects' => Project::query()->where('client_id', $client->id)->orderBy('name')->get(['id', 'name']),
            'folders' => ['Client Files', 'Documents', 'Other'],
        ]);
    }

    public function store(Request $request, FileLibrary $library, Notifier $notifier): RedirectResponse
    {
        [$client] = $this->shared($request);
        $maxKb = config('workora.max_upload_mb') * 1024;
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', "max:{$maxKb}"],
            'project_id' => ['nullable', 'uuid'],
            'folder' => ['nullable', 'in:Client Files,Documents,Other'],
        ]);
        $project = ! empty($data['project_id']) ? Project::query()->where('client_id', $client->id)->findOrFail($data['project_id']) : null;

        $n = 0;
        foreach ($request->file('files') as $upload) {
            if ($library->isBlocked($upload->getClientOriginalName())) {
                continue;
            }
            $file = $library->storeUpload($upload, $request->user(), $data['folder'] ?? 'Client Files', ['project_id' => $project?->id, 'client_id' => $client->id, 'visible_to_client' => true]);
            AuditLog::record('file.uploaded', $file, ['project_id' => $project?->id, 'client_id' => $client->id]);
            $n++;
        }

        if ($n) {
            $notifier->send($this->ctx->staffToNotify(), 'file', $client->name.' uploaded '.$n.' file(s)', $project?->name, route('files.index'), $request->user());
        }

        return back()->with($n ? 'status' : 'error', $n ? $n.' file(s) uploaded.' : 'No file was uploaded. That type is not allowed.');
    }

    public function show(Request $request, File $file, FileLibrary $library): StreamedResponse
    {
        return $library->response($this->find($request, $file), inline: $file->isInlineViewable());
    }

    public function download(Request $request, File $file, FileLibrary $library): StreamedResponse
    {
        return $library->response($this->find($request, $file), inline: false);
    }

    private function find(Request $request, File $file): File
    {
        [, $query] = $this->shared($request);

        return $query->findOrFail($file->id);
    }
}
