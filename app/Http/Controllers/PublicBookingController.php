<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Organization;
use App\Services\Availability;
use App\Services\Notifier;
use App\Support\BrandedMail;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** The public side: anyone with the link can pick a free time. No sign-in. */
class PublicBookingController extends Controller
{
    public function __construct(private Availability $availability, private Tenancy $tenancy) {}

    private function page(string $slug): BookingPage
    {
        return BookingPage::query()->with('host:id,name')->where('slug', $slug)->where('active', true)->firstOrFail();
    }

    public function show(Request $request, string $slug): View
    {
        $page = $this->page($slug);
        $today = now($page->timezone)->startOfDay();
        $days = collect(range(0, min($page->window_days, 60)))->map(fn ($i) => $today->copy()->addDays($i))
            ->filter(fn ($d) => $this->availability->slots($page, $d->format('Y-m-d'))->isNotEmpty())->take(21)->values();

        $date = $request->query('date');
        $date = $date && $days->contains(fn ($d) => $d->format('Y-m-d') === $date) ? $date : $days->first()?->format('Y-m-d');

        return view('bookings.public', ['page' => $page, 'days' => $days, 'date' => $date, 'slots' => $date ? $this->availability->slots($page, $date) : collect()]);
    }

    public function store(Request $request, string $slug, Notifier $notifier): RedirectResponse
    {
        $page = $this->page($slug);
        $data = $request->validate(['start' => ['required', 'date'], 'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $start = Carbon::parse($data['start'])->utc();

        $org = Organization::query()->findOrFail($page->organization_id);
        $booking = $this->tenancy->forOrganization($org, function () use ($page, $start, $data) {
            // Re-check inside the request: the slot may have been taken since the page was drawn.
            if (! $this->availability->isFree($page, $start)) {
                return null;
            }

            return Booking::create([
                'booking_page_id' => $page->id, 'user_id' => $page->user_id, 'name' => $data['name'], 'email' => $data['email'], 'notes' => $data['notes'] ?? null,
                'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes($page->duration_minutes), 'status' => 'confirmed', 'token' => Str::random(32),
            ]);
        });
        if (! $booking) {
            return back()->withInput()->withErrors(['start' => 'Sorry, that time was just taken. Please pick another.']);
        }

        $when = $start->copy()->setTimezone($page->timezone)->format('l d F Y, H:i').' ('.$page->timezone.')';
        try {
            BrandedMail::send($data['email'], 'Confirmed: '.$page->title, 'Your meeting is booked', "Hi {$data['name']},\n\nYou are booked with {$page->host->name}.", 'Manage this booking', route('book.confirmed', $booking->token),
                ['When' => $when, 'Length' => $page->duration_minutes.' minutes'] + ($page->location ? ['Where' => $page->location] : []));
        } catch (\Throwable) {
        }
        $this->tenancy->forOrganization($org, fn () => $notifier->send($page->host, 'booking', 'New booking: '.$data['name'], $when, route('bookings.index')));

        return redirect()->route('book.confirmed', $booking->token);
    }

    public function confirmed(string $token): View
    {
        $booking = Booking::withoutGlobalScopes()->where('token', $token)->firstOrFail();

        return view('bookings.confirmed', ['booking' => $booking, 'page' => BookingPage::with('host:id,name')->findOrFail($booking->booking_page_id)]);
    }

    public function cancel(string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScopes()->where('token', $token)->firstOrFail();
        $org = Organization::query()->findOrFail($booking->organization_id);
        $page = BookingPage::query()->with('host')->findOrFail($booking->booking_page_id);
        if ($booking->status === 'confirmed' && $booking->starts_at->isFuture()) {
            $this->tenancy->forOrganization($org, function () use ($booking, $page) {
                Booking::query()->whereKey($booking->id)->update(['status' => 'cancelled']);
                app(Notifier::class)->send($page->host, 'booking', 'Booking cancelled: '.$booking->name, $booking->starts_at->copy()->setTimezone($page->timezone)->format('l d F Y, H:i'), route('bookings.index'));
            });
        }

        return redirect()->route('book.confirmed', $token)->with('status', 'Your booking was cancelled.');
    }
}
