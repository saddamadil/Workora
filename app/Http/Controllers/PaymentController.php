<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Money in motion: what is owed, and what has been paid. */
class PaymentController extends Controller
{
    public function index(Request $request, Tenancy $tenancy): View
    {
        abort_unless($tenancy->isFreelancer() || Gate::allows('see-money'), 403);
        $mine = $tenancy->isFreelancer();
        $monthStart = now()->startOfMonth();

        $payments = Payment::query()->with('invoice:id,number', 'payee:id,name')
            ->when($mine, fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('status', 'paid')->latest('paid_at')->paginate(25);

        $owed = Invoice::query()->with('freelancer:id,name')
            ->when($mine, fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereIn('status', ['approved', 'partially_paid'])->orderBy('due_date')->get();

        $paidQuery = fn () => Payment::query()->when($mine, fn ($q) => $q->where('user_id', $request->user()->id))->where('status', 'paid');

        return view('payments.index', [
            'payments' => $payments,
            'owed' => $owed,
            'owedMinor' => (int) $owed->sum(fn (Invoice $i) => $i->outstandingMinor()),
            'overdueMinor' => (int) $owed->filter(fn (Invoice $i) => $i->isOverdue())->sum(fn (Invoice $i) => $i->outstandingMinor()),
            'paidMonthMinor' => (int) $paidQuery()->where('paid_at', '>=', $monthStart)->sum('amount_minor'),
            'paidTotalMinor' => (int) $paidQuery()->sum('amount_minor'),
            'mine' => $mine,
        ]);
    }
}
