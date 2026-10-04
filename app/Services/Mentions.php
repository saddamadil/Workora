<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/** "@Name" in a message or comment tells that person. Only people who can already see the thread are considered. */
class Mentions
{
    public function __construct(private Notifier $notifier) {}

    /** People from $candidates named in $body, by full name or by an unambiguous first name. */
    public function find(string $body, iterable $candidates): Collection
    {
        $candidates = collect($candidates)->unique('id')->values();
        $first = $candidates->groupBy(fn ($u) => mb_strtolower(strtok($u->name, ' ')));

        return $candidates->filter(function (User $u) use ($body, $first) {
            $names = [preg_quote($u->name, '/')];
            $key = mb_strtolower(strtok($u->name, ' '));
            if (($first[$key] ?? collect())->count() === 1) {
                $names[] = preg_quote(strtok($u->name, ' '), '/');
            }

            return (bool) preg_match('/(?<![\w@])@('.implode('|', $names).')(?![\w])/iu', $body);
        })->values();
    }

    /** @param iterable<User> $candidates */
    public function notify(string $body, iterable $candidates, User $author, string $where, string $url): int
    {
        $people = $this->find($body, $candidates)->reject(fn ($u) => $u->id === $author->id);
        $this->notifier->send($people, 'mention', $author->name.' mentioned you', $where.': '.str($body)->limit(140), $url, $author);

        return $people->count();
    }
}
