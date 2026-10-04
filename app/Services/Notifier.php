<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;

/**
 * Tells people about things. Every notification is kept in-app; an email goes out only when the
 * person has not turned that kind of email off, and only for the kinds worth interrupting someone.
 */
class Notifier
{
    /** type => [label, group, email on by default] */
    public const TYPES = [
        'message' => ['New message', 'Conversations', false],
        'request' => ['Work request', 'Conversations', true],
        'task' => ['New or completed task', 'Work', false],
        'project' => ['Project update', 'Work', false],
        'file' => ['File uploaded', 'Work', false],
        'approval' => ['Approval requested or given', 'Work', true],
        'revision' => ['Changes requested', 'Work', true],
        'invoice' => ['Invoice created, due or overdue', 'Money', true],
        'payment' => ['Payment received or reported', 'Money', true],
    ];

    public function __construct(private Tenancy $tenancy) {}

    /** @param  iterable<User>|User  $users */
    public function send(iterable|User $users, string $type, string $title, ?string $body = null, ?string $url = null, ?User $except = null): void
    {
        $users = $users instanceof User ? [$users] : $users;

        foreach ($users as $user) {
            if ($except && $user->id === $except->id) {
                continue;
            }

            $prefs = $user->notification_prefs ?? [];

            if (($prefs[$type]['app'] ?? true) !== false) {
                AppNotification::create(['user_id' => $user->id, 'type' => $type, 'title' => $title, 'body' => $body, 'url' => $url, 'created_at' => now()]);
            }

            if (($prefs[$type]['email'] ?? (self::TYPES[$type][2] ?? false)) === true) {
                try {
                    Mail::raw($title.($body ? "\n\n".$body : '').($url ? "\n\n".url($url) : '')."\n\nYou can change which emails you get in Settings > Notifications.", fn ($m) => $m->to($user->email)->subject($title));
                } catch (\Throwable) {
                    // Mail may not be set up yet. The in-app notification is already saved.
                }
            }
        }
    }

    /** Like send(), but not again if the same person already got this same title within $days. */
    public function once(User $user, string $type, string $title, ?string $body, ?string $url, int $days = 1): void
    {
        $seen = AppNotification::query()->where('user_id', $user->id)->where('type', $type)->where('title', $title)->where('created_at', '>=', now()->subDays($days))->exists();
        if (! $seen) {
            $this->send($user, $type, $title, $body, $url);
        }
    }

    public function unreadCount(User $user): int
    {
        return AppNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count();
    }
}
