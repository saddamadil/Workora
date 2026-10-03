<?php

namespace App\Http\Controllers;

use App\Models\DriveConnection;
use App\Models\File;
use App\Services\FileLibrary;
use App\Services\GoogleDrive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class DriveController extends Controller
{
    public function __construct(private GoogleDrive $drive) {}

    public function index(Request $request): View
    {
        $connection = DriveConnection::where('user_id', $request->user()->id)->first();

        $folder = preg_match('/^[\w-]{1,100}$/', (string) $request->query('folder')) ? $request->query('folder') : 'root';
        $search = trim((string) $request->query('q', ''));

        $view = [
            'configured' => $this->drive->configured(),
            'connection' => $connection,
            'redirectUri' => $this->drive->redirectUri(),
            'items' => [],
            'next' => null,
            'trail' => $this->trail($request->query('trail')),
            'search' => $search,
            'folder' => $folder,
            'error' => null,
        ];

        if (! $view['configured'] || ! $connection) {
            return view('drive.index', $view);
        }

        try {
            $listing = $this->drive->list($connection, $folder, $search ?: null, $request->query('page'));
            $view['items'] = $listing['files'];
            $view['next'] = $listing['next'];
        } catch (RuntimeException $e) {
            $view['error'] = $e->getMessage();
            // A revoked grant deletes the connection; show the connect card again.
            $view['connection'] = DriveConnection::where('user_id', $request->user()->id)->first();
        }

        return view('drive.index', $view);
    }

    public function connect(Request $request): RedirectResponse
    {
        abort_unless($this->drive->configured(), 404);

        $request->session()->put('drive_oauth_state', $state = Str::random(40));

        return redirect()->away($this->drive->authUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('drive_oauth_state');

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('drive.index')->with('error', 'That sign-in link was not valid. Please try again.');
        }

        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->route('drive.index')->with('error', 'Google Drive was not connected.');
        }

        try {
            $connection = $this->drive->connect($request->user(), (string) $request->query('code'));
        } catch (RuntimeException $e) {
            return redirect()->route('drive.index')->with('error', $e->getMessage());
        }

        return redirect()->route('drive.index')->with('status', 'Connected to '.($connection->google_email ?: 'Google Drive').'.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        if ($connection = DriveConnection::where('user_id', $request->user()->id)->first()) {
            $this->drive->disconnect($connection);
        }

        return redirect()->route('drive.index')->with('status', 'Google Drive disconnected. Files you already imported are untouched.');
    }

    /** Copy chosen Drive files into this workspace. */
    public function import(Request $request, FileLibrary $library): RedirectResponse
    {
        $this->authorize('create', File::class);

        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:20'],
            'ids.*' => ['required', 'string', 'regex:/^[\w-]{1,100}$/'],
        ])['ids'];

        $connection = DriveConnection::where('user_id', $request->user()->id)->first();
        abort_unless($connection, 409, 'Connect Google Drive first.');

        $maxBytes = config('workora.max_upload_mb') * 1024 * 1024;
        $imported = 0;
        $problems = [];

        foreach ($ids as $id) {
            try {
                $meta = $this->drive->metadata($connection, $id);

                if ($meta['mimeType'] === GoogleDrive::FOLDER) {
                    $problems[] = "\"{$meta['name']}\" is a folder; open it and pick files.";

                    continue;
                }

                if (! isset(GoogleDrive::EXPORTS[$meta['mimeType']]) && str_starts_with($meta['mimeType'], 'application/vnd.google-apps.')) {
                    $problems[] = "\"{$meta['name']}\" is a Google format that cannot be exported.";

                    continue;
                }

                if (isset($meta['size']) && (int) $meta['size'] > $maxBytes) {
                    $problems[] = "\"{$meta['name']}\" is larger than ".config('workora.max_upload_mb').' MB.';

                    continue;
                }

                $download = $this->drive->download($connection, $meta);

                try {
                    if ($library->isBlocked($download['name'])) {
                        $problems[] = "\"{$download['name']}\" is a file type that is not allowed.";

                        continue;
                    }

                    $library->storeFromPath($download['path'], $download['name'], $download['mime'], $request->user(), [
                        'source' => 'google_drive',
                        'drive_file_id' => $meta['id'],
                    ]);
                    $imported++;
                } finally {
                    @unlink($download['path']);
                }
            } catch (RuntimeException $e) {
                $problems[] = $e->getMessage();
            }
        }

        $redirect = redirect()->route($imported ? 'files.index' : 'drive.index');

        if ($imported) {
            $redirect->with('status', $imported.' '.Str::plural('file', $imported).' imported from Google Drive.'.($problems ? ' Some were skipped.' : ''));
        }

        return $problems ? $redirect->with('error', implode(' ', $problems)) : $redirect;
    }

    /** Save a copy of a workspace file into the user's Google Drive. */
    public function export(Request $request, File $file, FileLibrary $library): RedirectResponse
    {
        $this->authorize('share', $file);

        $connection = DriveConnection::where('user_id', $request->user()->id)->first();

        if (! $connection) {
            return redirect()->route('drive.index')->with('error', 'Connect Google Drive first.');
        }

        $tmp = $library->toTempFile($file);

        try {
            $saved = $this->drive->upload($connection, $tmp, $file->original_name, $file->mime_type ?: 'application/octet-stream');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            @unlink($tmp);
        }

        return back()->with('status', '"'.$file->original_name.'" was saved to your Google Drive.');
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function trail(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)->take(20)
            ->filter(fn ($c) => is_array($c) && isset($c[0], $c[1]) && is_string($c[0]) && is_string($c[1]) && preg_match('/^[\w-]{1,100}$/', $c[0]))
            ->map(fn ($c) => [$c[0], Str::limit($c[1], 80, '')])
            ->values()->all();
    }
}
