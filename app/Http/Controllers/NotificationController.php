<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        return view('notifications.index', [
            'items' => AppNotification::query()->where('user_id', $request->user()->id)
                ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))->latest('created_at')->limit(100)->get(),
            'filter' => $filter,
        ]);
    }

    /** Opens a notification: marks it read and goes where it points. */
    public function open(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        // Only ever send people to a page inside this site.
        $url = (string) $notification->url;
        $host = parse_url($url, PHP_URL_HOST);
        $local = (str_starts_with($url, '/') && ! str_starts_with($url, '//')) || ($host !== null && strcasecmp($host, $request->getHost()) === 0 && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true));

        return redirect($local && $url !== '' ? $url : route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        AppNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'All caught up.');
    }

    public function preferences(Request $request): View
    {
        return view('notifications.preferences', ['types' => Notifier::TYPES, 'prefs' => $request->user()->notification_prefs ?? []]);
    }

    public function savePreferences(Request $request): RedirectResponse
    {
        $prefs = [];
        foreach (array_keys(Notifier::TYPES) as $type) {
            $prefs[$type] = ['app' => $request->boolean("prefs.$type.app"), 'email' => $request->boolean("prefs.$type.email")];
        }
        $request->user()->update(['notification_prefs' => $prefs]);

        return back()->with('status', 'Notification settings saved.');
    }
}
