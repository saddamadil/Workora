<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** An invoice that repeats on a schedule: a retainer, a monthly service, a yearly fee. */
class RecurringInvoice extends Model
{
    use BelongsToOrganization, HasUuids;

    public const FREQUENCIES = ['weekly' => 'Every week', 'monthly' => 'Every month', 'quarterly' => 'Every 3 months', 'yearly' => 'Every year'];

    protected $fillable = [
        'organization_id', 'user_id', 'client_id', 'project_id', 'title', 'frequency', 'next_run_on', 'ends_on', 'auto_send',
        'status', 'details', 'items', 'last_run_on', 'runs_count',
    ];

    protected function casts(): array
    {
        return ['next_run_on' => 'date', 'ends_on' => 'date', 'last_run_on' => 'date', 'auto_send' => 'boolean', 'details' => 'array', 'items' => 'array', 'runs_count' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The date after $from, keeping month ends sensible (31 Jan + 1 month is 28 or 29 Feb, then back to the end of March). */
    public function advance(Carbon $from): Carbon
    {
        return match ($this->frequency) {
            'weekly' => $from->copy()->addWeek(),
            'quarterly' => $from->copy()->addMonthsNoOverflow(3),
            'yearly' => $from->copy()->addYearNoOverflow(),
            default => $from->copy()->addMonthNoOverflow(),
        };
    }
}
