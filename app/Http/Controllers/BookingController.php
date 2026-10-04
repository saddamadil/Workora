<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\TimeOff;
use App\Services\Availability;
use App\Support\BrandedMail;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Your side: your public booking page, the meetings people booked, and your time off. */
class BookingController extends Controller
{
    public function __construct(private Tenancy $tenancy) {}

    private function guard(): void
    {
        abort_if($this->tenancy->role()?->isClient() ?? true, 403);
    }

    public function index(Request $request): View
    {
        $this->guard();
        $me = $request->user();
        $page = BookingPage::query()->where('organization_id', $this->tenancy->id())->where('user_id', $me->id)->first();

        return view('bookings.index', [
            'page' => $page,
            'hours' => $page?->hours ?? Availability::defaultHours(),
            'upcoming' => Booking::query()->where('user_id', $me->id)->where('status', 'confirmed')->where('ends_at', '>=', now())->orderBy('starts_at')->get(),
            'past' => Booking::query()->where('user_id', $me->id)->where(fn ($q) => $q->where('ends_at', '<', now())->orWhere('status', 'cancelled'))->latest('starts_at')->limit(10)->get(),
            'timeOff' => TimeOff::query()->where('user_id', $me->id)->whereDate('ends_on', '>=', now()->subDays(30))->orderBy('starts_on')->get(),
            'zones' => \DateTimeZone::listIdentifiers(), 'days' => BookingPage::DAYS, 'kinds' => TimeOff::KINDS,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $this->guard();
        $me = $request->user();
        $existing = BookingPage::query()->where('organization_id', $this->tenancy->id())->where('user_id', $me->id)->first();
        $data = $request->validate([
            'slug' => ['required', 'alpha_dash:ascii', 'min:3', 'max:60', Rule::unique('booking_pages', 'slug')->ignore($existing?->id)],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:200'],
            'duration_minutes' => ['required', 'integer', Rule::in([15, 20, 30, 45, 60, 90, 120])],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'notice_hours' => ['required', 'integer', 'min:0', 'max:336'],
            'window_days' => ['required', 'integer', 'min:1', 'max:180'],
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'days' => ['nullable', 'array'],
            'days.*.on' => ['nullable'],
            'days.*.from' => ['nullable', 'date_format:H:i'],
            'days.*.to' => ['nullable', 'date_format:H:i'],
        ]);
        $hours = [];
        foreach (BookingPage::DAYS as $n => $name) {
            $d = $data['days'][$n] ?? null;
            if ($d && ! empty($d['on']) && ! empty($d['from']) && ! empty($d['to']) && $d['to'] > $d['from']) {
                $hours[(string) $n] = [$d['from'], $d['to']];
            }
        }
        unset($data['days']);
        $data['slug'] = Str::lower($data['slug']);
        $data['hours'] = $hours;
        $data['active'] = $request->boolean('active');

        $existing ? $existing->update($data) : BookingPage::create($data + ['organization_id' => $this->tenancy->id(), 'user_id' => $me->id]);

        return back()->with('status', 'Booking page saved.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->guard();
        abort_unless($booking->user_id === $request->user()->id, 404);
        $booking->update(['status' => 'cancelled']);
        $page = BookingPage::find($booking->booking_page_id);
        try {
            BrandedMail::send($booking->email, 'Cancelled: '.($page?->title ?? 'meeting'), 'Your meeting was cancelled', $request->user()->name.' cancelled the meeting on '.$booking->starts_at->copy()->setTimezone($page?->timezone ?? 'UTC')->format('D d M Y, H:i').'.', $page ? 'Pick another time' : null, $page?->url());
        } catch (\Throwable) {
        }

        return back()->with('status', 'Meeting cancelled and the guest was emailed.');
    }

    public function storeTimeOff(Request $request): RedirectResponse
    {
        $this->guard();
        $data = $request->validate(['starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'kind' => ['required', Rule::in(array_keys(TimeOff::KINDS))], 'note' => ['nullable', 'string', 'max:200']]);
        TimeOff::create($data + ['user_id' => $request->user()->id]);

        return back()->with('status', 'Time off saved. Nobody can book you on those days.');
    }

    public function destroyTimeOff(Request $request, TimeOff $timeOff): RedirectResponse
    {
        $this->guard();
        abort_unless($timeOff->user_id === $request->user()->id, 404);
        $timeOff->delete();

        return back()->with('status', 'Removed.');
    }
}
