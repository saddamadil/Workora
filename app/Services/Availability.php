<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\TimeOff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Works out which start times on a booking page are free: working hours, notice, time off and existing bookings. */
class Availability
{
    /** Default week: Monday to Friday, 09:00 to 17:00. */
    public static function defaultHours(): array
    {
        return array_fill_keys([1, 2, 3, 4, 5], ['09:00', '17:00']);
    }

    /** @return Collection<int, Carbon> free start times (in the page's time zone) on $date ('Y-m-d' in that zone) */
    public function slots(BookingPage $page, string $date, ?Carbon $now = null): Collection
    {
        $now ??= now();
        $tz = $page->timezone;
        $day = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
        $hours = $page->hours[(string) $day->isoWeekday()] ?? null;
        if (! $page->active || ! $hours || count($hours) !== 2) {
            return collect();
        }

        $earliest = $now->copy()->addHours($page->notice_hours);
        $latest = $now->copy()->addDays($page->window_days)->endOfDay();
        if ($day->copy()->endOfDay()->lt($earliest) || $day->gt($latest)) {
            return collect();
        }

        // Time off for the host covers whole local days.
        $off = TimeOff::withoutGlobalScopes()->where('organization_id', $page->organization_id)->where('user_id', $page->user_id)
            ->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->exists();
        if ($off) {
            return collect();
        }

        $busy = Booking::withoutGlobalScopes()->where('organization_id', $page->organization_id)->where('user_id', $page->user_id)->where('status', 'confirmed')
            ->where('starts_at', '<', $day->copy()->endOfDay()->utc()->addDay())->where('ends_at', '>', $day->copy()->utc()->subDay())->get(['starts_at', 'ends_at']);

        [$from, $to] = [Carbon::createFromFormat('Y-m-d H:i', $date.' '.$hours[0], $tz), Carbon::createFromFormat('Y-m-d H:i', $date.' '.$hours[1], $tz)];
        $length = max(5, $page->duration_minutes);
        $step = $length + $page->buffer_minutes;
        $buffer = $page->buffer_minutes;

        $slots = collect();
        for ($start = $from->copy(); $start->copy()->addMinutes($length)->lte($to); $start->addMinutes($step)) {
            $end = $start->copy()->addMinutes($length);
            if ($start->lt($earliest) || $start->gt($latest)) {
                continue;
            }
            $clash = $busy->contains(fn ($b) => $start->copy()->subMinutes($buffer)->lt($b->ends_at) && $end->copy()->addMinutes($buffer)->gt($b->starts_at));
            if (! $clash) {
                $slots->push($start->copy());
            }
        }

        return $slots;
    }

    public function isFree(BookingPage $page, Carbon $start, ?Carbon $now = null): bool
    {
        return $this->slots($page, $start->copy()->setTimezone($page->timezone)->format('Y-m-d'), $now)
            ->contains(fn ($s) => $s->equalTo($start));
    }
}
