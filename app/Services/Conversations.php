<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ConversationRead;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Messaging between a freelancer and one client. A conversation is the client's general thread or
 * one project's thread, so work on different projects never gets mixed together.
 */
class Conversations
{
    public const GENERAL = 'general';

    public static function scopeKey(?string $projectId): string
    {
        return $projectId ?: self::GENERAL;
    }

    /** Threads for a client: general first, then each project, with unread counts for $viewer. */
    public function threads(Client $client, User $viewer, ?Collection $projects = null): Collection
    {
        $projects ??= Project::query()->where('client_id', $client->id)->orderBy('name')->get(['id', 'name', 'slug']);
        $reads = ConversationRead::query()->where('user_id', $viewer->id)->where('client_id', $client->id)->pluck('last_read_at', 'scope');

        $counts = Message::query()->where('client_id', $client->id)->where('user_id', '!=', $viewer->id)->get(['id', 'project_id', 'created_at'])
            ->groupBy(fn ($m) => self::scopeKey($m->project_id));

        $make = function (string $key, string $title, ?Project $project) use ($counts, $reads) {
            $unread = ($counts[$key] ?? collect())->filter(fn ($m) => ! isset($reads[$key]) || $m->created_at->gt($reads[$key]))->count();

            return ['key' => $key, 'title' => $title, 'project' => $project, 'unread' => $unread];
        };

        return collect([$make(self::GENERAL, 'General', null)])
            ->concat($projects->map(fn ($p) => $make($p->id, $p->name, $p)));
    }

    public function messages(Client $client, ?string $projectId, string $search = ''): Collection
    {
        return Message::query()->with('user:id,name,avatar_path,last_seen_at', 'files', 'parent:id,body,user_id')
            ->where('client_id', $client->id)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId), fn ($q) => $q->whereNull('project_id'))
            ->when($search !== '', fn ($q) => $q->where('body', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->oldest()->limit(300)->get();
    }

    public function markRead(User $viewer, Client $client, ?string $projectId): void
    {
        ConversationRead::query()->updateOrCreate(
            ['user_id' => $viewer->id, 'client_id' => $client->id, 'scope' => self::scopeKey($projectId)],
            ['last_read_at' => now()],
        );
    }

    public function totalUnread(User $viewer, Collection $clients): int
    {
        return $clients->sum(fn ($c) => $this->threads($c, $viewer)->sum('unread'));
    }

    // Typing and presence are best-effort and short-lived, kept in the cache rather than the database.
    public function typing(User $user, Client $client, ?string $projectId): void
    {
        Cache::put($this->typingKey($client, $projectId, $user->id), $user->name, now()->addSeconds(6));
    }

    /** Names of others typing right now. */
    public function whoIsTyping(Client $client, ?string $projectId, User $viewer, Collection $others): array
    {
        return $others->filter(fn ($u) => $u->id !== $viewer->id && Cache::has($this->typingKey($client, $projectId, $u->id)))->pluck('name')->all();
    }

    private function typingKey(Client $client, ?string $projectId, string $userId): string
    {
        return "typing.{$client->id}.".self::scopeKey($projectId).".{$userId}";
    }

    public static function isOnline(?User $user): bool
    {
        return $user?->last_seen_at !== null && $user->last_seen_at->gt(now()->subMinutes(3));
    }
}
