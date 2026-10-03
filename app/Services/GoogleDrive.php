<?php

namespace App\Services;

use App\Models\DriveConnection;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin client over Google's OAuth 2.0 and Drive v3 REST endpoints. Uses
 * Laravel's HTTP client rather than Google's SDK, which pulls in dozens of
 * packages for the four calls this app makes.
 */
class GoogleDrive
{
    public const FOLDER = 'application/vnd.google-apps.folder';

    /** Google-native formats have no bytes to download; they are exported. */
    public const EXPORTS = [
        'application/vnd.google-apps.document' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'],
        'application/vnd.google-apps.spreadsheet' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
        'application/vnd.google-apps.presentation' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx'],
        'application/vnd.google-apps.drawing' => ['image/png', 'png'],
    ];

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';

    public function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.google.redirect') ?: route('drive.callback');
    }

    public function authUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            // readonly to browse and import anything; drive.file to save into
            // Drive without asking for access to everything.
            'scope' => implode(' ', [
                'openid', 'email',
                'https://www.googleapis.com/auth/drive.readonly',
                'https://www.googleapis.com/auth/drive.file',
            ]),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function connect(User $user, string $code): DriveConnection
    {
        $tokens = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if ($tokens->failed() || ! $tokens->json('access_token')) {
            throw new RuntimeException('Google did not accept the sign-in. Please try connecting again.');
        }

        $email = Http::withToken($tokens->json('access_token'))
            ->get('https://openidconnect.googleapis.com/v1/userinfo')
            ->json('email');

        $existing = DriveConnection::where('user_id', $user->id)->first();

        return DriveConnection::updateOrCreate(['user_id' => $user->id], [
            'google_email' => $email,
            'access_token' => $tokens->json('access_token'),
            // Google only returns a refresh token on first consent; keep the old one otherwise.
            'refresh_token' => $tokens->json('refresh_token') ?: $existing?->refresh_token,
            'expires_at' => now()->addSeconds((int) $tokens->json('expires_in', 3600)),
        ]);
    }

    public function disconnect(DriveConnection $connection): void
    {
        $token = $connection->refresh_token ?: $connection->access_token;

        // Best effort: if revoking fails the local connection is still removed.
        try {
            Http::asForm()->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
        } catch (\Throwable) {
        }

        $connection->delete();
    }

    /** @return array{files: array<int, array>, next: ?string} */
    public function list(DriveConnection $connection, string $folderId = 'root', ?string $search = null, ?string $pageToken = null): array
    {
        $q = $search
            ? "name contains '".$this->escape($search)."' and trashed = false"
            : "'".$this->escape($folderId)."' in parents and trashed = false";

        $response = $this->api($connection)->get(self::API.'/files', array_filter([
            'q' => $q,
            'pageSize' => 50,
            'pageToken' => $pageToken,
            'orderBy' => 'folder,name',
            'fields' => 'nextPageToken, files(id,name,mimeType,size,modifiedTime,iconLink,thumbnailLink)',
        ]));

        $this->ensureOk($response, 'Could not read your Google Drive.');

        return ['files' => $response->json('files', []), 'next' => $response->json('nextPageToken')];
    }

    public function metadata(DriveConnection $connection, string $fileId): array
    {
        $response = $this->api($connection)->get(self::API.'/files/'.rawurlencode($fileId), [
            'fields' => 'id,name,mimeType,size',
        ]);

        $this->ensureOk($response, 'That file could not be found in Google Drive.');

        return $response->json();
    }

    /**
     * Download (or export, for Google-native files) into a temp file.
     *
     * @return array{path: string, name: string, mime: string}
     */
    public function download(DriveConnection $connection, array $meta): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'drive_');
        $mime = $meta['mimeType'];
        $name = $meta['name'];
        $id = rawurlencode($meta['id']);

        if (isset(self::EXPORTS[$mime])) {
            [$exportMime, $ext] = self::EXPORTS[$mime];
            $response = $this->api($connection)->sink($tmp)
                ->get(self::API."/files/{$id}/export", ['mimeType' => $exportMime]);
            $mime = $exportMime;
            $name = Str::endsWith(strtolower($name), '.'.$ext) ? $name : $name.'.'.$ext;
        } else {
            $response = $this->api($connection)->sink($tmp)
                ->get(self::API."/files/{$id}", ['alt' => 'media']);
        }

        if ($response->failed()) {
            @unlink($tmp);
            throw new RuntimeException("Google Drive would not let us download \"{$meta['name']}\".");
        }

        return ['path' => $tmp, 'name' => $name, 'mime' => $mime];
    }

    /** Upload a local file into the user's Drive (My Drive root) and return Google's file record. */
    public function upload(DriveConnection $connection, string $localPath, string $name, string $mime): array
    {
        $boundary = 'workora'.Str::random(16);
        $metadata = json_encode(['name' => $name]);

        $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$metadata}\r\n"
            ."--{$boundary}\r\nContent-Type: {$mime}\r\n\r\n".file_get_contents($localPath)."\r\n--{$boundary}--";

        $response = $this->api($connection)
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post(self::UPLOAD.'?uploadType=multipart&fields=id,name,webViewLink');

        $this->ensureOk($response, 'Google Drive rejected the upload.');

        return $response->json();
    }

    private function api(DriveConnection $connection): PendingRequest
    {
        return Http::withToken($this->accessToken($connection))->timeout(120);
    }

    private function accessToken(DriveConnection $connection): string
    {
        if (! $connection->isExpired()) {
            return $connection->access_token;
        }

        if (! $connection->refresh_token) {
            throw new RuntimeException('Your Google connection expired. Please reconnect Google Drive.');
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            // Revoked or expired grant: drop it so the UI offers "Connect" again.
            $connection->delete();
            throw new RuntimeException('Google access was revoked. Please reconnect Google Drive.');
        }

        $connection->update([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);

        return $connection->access_token;
    }

    private function ensureOk($response, string $message): void
    {
        if ($response->failed()) {
            throw new RuntimeException($message);
        }
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
