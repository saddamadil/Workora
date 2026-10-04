<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Project;
use App\Models\ShareLink;
use App\Services\FileLibrary;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function index(Request $request): View
    {
        $folder = $request->string('folder')->toString() ?: null;
        $type = $request->string('type')->toString() ?: null;
        $search = trim($request->string('q')->toString());
        $sort = $request->string('sort')->toString();

        $files = File::query()
            ->current()
            ->visibleTo($request->user())
            ->with('uploadedBy:id,name')
            ->withCount(['shareLinks as active_links_count' => fn ($q) => $q->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))])
            ->when($folder, fn ($q) => $q->where('folder', $folder))
            ->when($search !== '', fn ($q) => $q->where('original_name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($type === 'images', fn ($q) => $q->where('mime_type', 'like', 'image/%'))
            ->when($type === 'documents', fn ($q) => $q->where('mime_type', 'not like', 'image/%'))
            ->when($sort === 'name', fn ($q) => $q->orderBy('original_name'), fn ($q) => $q->latest())
            ->paginate(48)
            ->withQueryString();

        return view('files.index', [
            'files' => $files,
            'folders' => File::query()->whereNotNull('folder')->select('folder')->distinct()->orderBy('folder')->pluck('folder'),
            'folder' => $folder,
            'type' => $type,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    public function store(Request $request, FileLibrary $library): JsonResponse|RedirectResponse
    {
        $this->authorize('create', File::class);

        $maxKb = config('workora.max_upload_mb') * 1024;

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => ['required', 'file', "max:{$maxKb}"],
            'folder' => ['nullable', 'string', 'max:60'],
            'project_id' => ['nullable', 'uuid'],
        ], [
            'files.*.max' => 'Each file must be '.config('workora.max_upload_mb').' MB or smaller.',
            'files.*.uploaded' => 'A file did not finish uploading. It may be larger than the server allows.',
        ]);

        // A file can be filed under a project, but only one the uploader can see.
        $projectId = null;
        if ($request->filled('project_id')) {
            $projectId = Project::query()->visibleTo($request->user())->whereKey($request->input('project_id'))->firstOrFail()->id;
        }

        $saved = [];
        $skipped = [];

        foreach ($request->file('files') as $upload) {
            if ($library->isBlocked($upload->getClientOriginalName())) {
                $skipped[] = $upload->getClientOriginalName();

                continue;
            }

            $saved[] = $library->storeUpload($upload, $request->user(), $request->input('folder'), ['project_id' => $projectId]);
        }

        $message = count($saved).' '.str('file')->plural(count($saved)).' uploaded.';
        if ($skipped) {
            $message .= ' Not allowed for security reasons: '.implode(', ', $skipped).'.';
        }

        if ($request->expectsJson()) {
            return response()->json(['uploaded' => count($saved), 'skipped' => $skipped, 'message' => $message], $saved ? 201 : 422);
        }

        return back()->with($saved ? 'status' : 'error', $message);
    }

    /** Inline view for images and PDFs; anything else is sent as a download. */
    public function show(File $file, FileLibrary $library): StreamedResponse
    {
        $this->authorize('view', $file);

        return $library->response($file, inline: $file->isInlineViewable());
    }

    public function download(File $file, FileLibrary $library): StreamedResponse
    {
        $this->authorize('view', $file);

        return $library->response($file, inline: false);
    }

    public function update(Request $request, File $file): RedirectResponse
    {
        $this->authorize('update', $file);

        $data = $request->validate([
            'original_name' => ['sometimes', 'required', 'string', 'max:200'],
            'folder' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);

        if (isset($data['original_name'])) {
            $data['original_name'] = app(FileLibrary::class)->cleanName($data['original_name']);
        }

        $file->update($data);

        return back()->with('status', 'Saved.');
    }

    public function destroy(File $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        // Soft delete: the stored bytes stay until a purge, and links stop working at once.
        $file->delete();

        return redirect()->route('files.index')->with('status', '"'.$file->original_name.'" was deleted.');
    }

    /** Create a public link for one file. */
    public function share(Request $request, File $file): JsonResponse|RedirectResponse
    {
        $this->authorize('share', $file);

        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:4', 'max:100'],
            'expires_in_days' => ['nullable', 'integer', Rule::in([1, 7, 30])],
            'max_downloads' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $link = ShareLink::create([
            'organization_id' => app(Tenancy::class)->idOrFail(),
            'file_id' => $file->id,
            'token' => ShareLink::generateToken(),
            'password' => ! empty($data['password']) ? bcrypt($data['password']) : null,
            'expires_at' => ! empty($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null,
            'max_downloads' => $data['max_downloads'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['url' => $link->url(), 'has_password' => $link->hasPassword()], 201);
        }

        return back()->with('status', 'Link ready: '.$link->url());
    }
}
