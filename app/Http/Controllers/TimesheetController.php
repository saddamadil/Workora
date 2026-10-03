<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Timesheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The company side of time: reviewing what freelancers and staff submitted. */
class TimesheetController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('review-time');
        $status = $request->query('status', 'submitted');

        $timesheets = Timesheet::query()->with('freelancer:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('submitted_at')->orderByDesc('period_start')->paginate(30)->withQueryString();

        return view('timesheets.index', ['timesheets' => $timesheets, 'status' => $status]);
    }

    public function show(Timesheet $timesheet): View
    {
        $this->authorize('review-time');

        return view('timesheets.show', [
            'timesheet' => $timesheet->load('freelancer:id,name,email', 'reviewedBy:id,name',
                'entries.project:id,name', 'entries.task:id,title'),
        ]);
    }

    public function approve(Request $request, Timesheet $timesheet): RedirectResponse
    {
        $this->authorize('review-time');
        abort_unless($timesheet->status === 'submitted', 422, 'This timesheet is not waiting for review.');

        $timesheet->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $request->input('note')]);
        AuditLog::record('timesheet.approved', $timesheet);

        return redirect()->route('timesheets.index')->with('status', 'Timesheet approved.');
    }

    public function reject(Request $request, Timesheet $timesheet): RedirectResponse
    {
        $this->authorize('review-time');
        abort_unless($timesheet->status === 'submitted', 422, 'This timesheet is not waiting for review.');

        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        // Reopening it lets the person fix entries and send the week again.
        $timesheet->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['note']]);
        AuditLog::record('timesheet.rejected', $timesheet);

        return redirect()->route('timesheets.index')->with('status', 'Sent back for changes.');
    }
}
