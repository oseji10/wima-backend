<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\SharingScheme;
use App\Models\FinanceService;
use App\Models\RevenueEntry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\FundingSource;
use App\Models\FundingTransaction;
use App\Models\Hubs;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;

class FinanceController extends Controller
{
    /* =======================================================================
     |  Role scoping  (1 = Admin, 3 = National -> all; 4 = State; 5 = Community)
     * ===================================================================== */

    private function scopedHubIds(): ?array
    {
        $user = Auth::user();

        if (in_array((int) $user->role, [1, 3], true)) {
            return null; // null = no restriction
        }

        if ((int) $user->role === 4) {
            $stateId = optional(StateCoordinators::where('userId', $user->id)->first())->stateId;
            return Hubs::where('state', $stateId)->pluck('hubId')->all();
        }

        if ((int) $user->role === 5) {
            $lga = optional(CommunityLead::where('userId', $user->id)->first())->lga;
            return Hubs::where('lga', $lga)->pluck('hubId')->all();
        }

        return []; // unknown role sees nothing
    }

    private function applyHubScope($query, string $column = 'hub')
    {
        $ids = $this->scopedHubIds();
        if (is_array($ids)) {
            $query->whereIn($column, $ids);
        }
        return $query;
    }

    private function isManager(): bool
    {
        return in_array((int) Auth::user()->role, [1, 3], true);
    }

    /* =======================================================================
     |  Treasury dashboard
     * ===================================================================== */

    public function dashboard(Request $request)
    {
        $scheme = SharingScheme::current();

        // ---- Revenue for the requested month (defaults to current month) ----
        $month = $request->query('month')
            ? Carbon::parse($request->query('month'))
            : Carbon::today();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $entries = $this->applyHubScope(RevenueEntry::query())
            ->whereBetween('entry_date', [$start, $end])
            ->get();

        $grossDaily = $entries->sum(fn ($e) => $e->gross_total);

        // Aggregate stakeholder shares across all entries
        $totals = [
            'wima_combined' => 0, 'community_dev' => 0, 'state_coord' => 0,
            'cl' => 0, 'subcl' => 0, 'msp' => 0, 'state_total' => 0,
        ];
        foreach ($entries as $e) {
            $s = $e->shares;
            foreach (array_keys($totals) as $k) {
                $totals[$k] += $s[$k] ?? 0;
            }
        }
        $totals = array_map(fn ($v) => round($v, 2), $totals);

        // ---- Receivables ----
        $invoices = $this->applyHubScope(Invoice::query())->get();
        $outstanding = $invoices->where('status', '!=', 'void')->sum(fn ($i) => $i->balance);
        $overdue = $invoices->filter(fn ($i) => $i->is_overdue)->sum(fn ($i) => $i->balance);
        $collected = $invoices->sum('amount_paid');

        // ---- Funding (not hub-scoped; org-wide) ----
        $funding = $this->isManager() ? FundingSource::all() : collect();
        $fundingSummary = [
            'committed' => round($funding->sum('total_committed'), 2),
            'received'  => round($funding->sum(fn ($f) => $f->total_received), 2),
            'by_type'   => $funding->groupBy('type')->map(fn ($g) => [
                'committed' => round($g->sum('total_committed'), 2),
                'received'  => round($g->sum(fn ($f) => $f->total_received), 2),
                'count'     => $g->count(),
            ]),
        ];

        return response()->json([
            'scheme'    => $scheme,
            'period'    => ['month' => $start->toDateString()],
            'revenue'   => [
                'gross_daily'   => round($grossDaily, 2),
                'gross_weekly'  => round($grossDaily * $scheme->weekly_multiplier, 2),
                'gross_monthly' => round($grossDaily * $scheme->weekly_multiplier * $scheme->monthly_multiplier, 2),
                'stakeholders'  => $totals,
                'entry_count'   => $entries->count(),
            ],
            'receivables' => [
                'outstanding' => round($outstanding, 2),
                'overdue'     => round($overdue, 2),
                'collected'   => round($collected, 2),
                'open_count'  => $invoices->whereNotIn('status', ['paid', 'void'])->count(),
            ],
            'funding' => $fundingSummary,
        ]);
    }

    /* =======================================================================
     |  Sharing scheme  (the variables that drive every figure)
     * ===================================================================== */

    public function schemeShow()
    {
        return response()->json(SharingScheme::current());
    }

    public function schemeUpdate(Request $request)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can change the sharing scheme.');

        $data = $request->validate([
            'name'                 => 'sometimes|string|max:255',
            'wima_pct'             => 'required|numeric|min:0|max:100',
            'state_pct'            => 'required|numeric|min:0|max:100',
            'sb_wima_pct'          => 'required|numeric|min:0|max:100',
            'sb_community_dev_pct' => 'required|numeric|min:0|max:100',
            'sb_state_coord_pct'   => 'required|numeric|min:0|max:100',
            'sb_cl_pct'            => 'required|numeric|min:0|max:100',
            'sb_subcl_pct'         => 'required|numeric|min:0|max:100',
            'sb_msp_pct'           => 'required|numeric|min:0|max:100',
            'msp_groups'           => 'required|integer|min:1',
            'msp_per_group'        => 'required|integer|min:1',
            'weekly_multiplier'    => 'required|numeric|min:0',
            'monthly_multiplier'   => 'required|numeric|min:0',
        ]);

        // Soft guardrails: warn (don't block) if splits don't sum to 100
        $warnings = [];
        if (abs(($data['wima_pct'] + $data['state_pct']) - 100) > 0.01) {
            $warnings[] = 'Top-level WIMA + State does not total 100%.';
        }
        $sb = $data['sb_wima_pct'] + $data['sb_community_dev_pct'] + $data['sb_state_coord_pct']
            + $data['sb_cl_pct'] + $data['sb_subcl_pct'] + $data['sb_msp_pct'];
        if (abs($sb - 100) > 0.01) {
            $warnings[] = 'State breakdown shares do not total 100%.';
        }

        $scheme = SharingScheme::current();
        $scheme->update(array_merge($data, ['is_active' => true]));

        return response()->json(['scheme' => $scheme->fresh(), 'warnings' => $warnings]);
    }

    /** Preview a split for an arbitrary amount without saving anything. */
    public function schemePreview(Request $request)
    {
        $amount = (float) $request->query('amount', 0);
        return response()->json(SharingScheme::current()->split($amount));
    }

    /* =======================================================================
     |  Service catalogue
     * ===================================================================== */

    public function serviceIndex()
    {
        return response()->json(
            FinanceService::orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function serviceStore(Request $request)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can manage services.');
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'default_unit_cost' => 'nullable|numeric|min:0',
            'default_target'    => 'nullable|integer|min:0',
            'active'            => 'nullable|boolean',
            'sort_order'       => 'nullable|integer',
        ]);
        return response()->json(FinanceService::create($data), 201);
    }

    public function serviceUpdate(Request $request, $id)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can manage services.');
        $service = FinanceService::findOrFail($id);
        $service->update($request->validate([
            'name'              => 'sometimes|string|max:255',
            'default_unit_cost' => 'nullable|numeric|min:0',
            'default_target'    => 'nullable|integer|min:0',
            'active'            => 'nullable|boolean',
            'sort_order'       => 'nullable|integer',
        ]));
        return response()->json($service->fresh());
    }

    public function serviceDestroy($id)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can manage services.');
        FinanceService::findOrFail($id)->delete();
        return response()->json(['message' => 'Service removed']);
    }

    /* =======================================================================
     |  Revenue entries + per-hub projection
     * ===================================================================== */

    public function revenueIndex(Request $request)
    {
        $query = $this->applyHubScope(
            RevenueEntry::with('recorder:id,firstName', 'hub.lgas')
        );

        if ($hub = $request->query('hub')) {
            $query->where('hub', $hub);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('entry_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('entry_date', '<=', $to);
        }

        return response()->json(
            $query->orderByDesc('entry_date')->orderByDesc('id')->get()
        );
    }

    public function revenueStore(Request $request)
    {
        $data = $request->validate([
            'hub'          => 'nullable|integer',
            'service_id'   => 'nullable|integer',
            'service_name' => 'required|string|max:255',
            'unit_cost'    => 'required|numeric|min:0',
            'target'       => 'required|integer|min:0',
            'quantity'     => 'required|integer|min:0',
            'entry_date'   => 'required|date',
            'notes'        => 'nullable|string',
        ]);

        // Enforce hub scope on write
        $ids = $this->scopedHubIds();
        if (is_array($ids) && $data['hub'] && !in_array((int) $data['hub'], array_map('intval', $ids), true)) {
            abort(403, 'You cannot record revenue for that hub.');
        }

        $data['scheme_id'] = SharingScheme::current()->id;
        $data['recorded_by'] = Auth::id();

        $entry = RevenueEntry::create($data);
        return response()->json($entry->fresh('recorder:id,firstName'), 201);
    }

    public function revenueUpdate(Request $request, $id)
    {
        $entry = RevenueEntry::findOrFail($id);
        $this->assertHubAccess($entry->hub);

        $entry->update($request->validate([
            'service_name' => 'sometimes|string|max:255',
            'unit_cost'    => 'sometimes|numeric|min:0',
            'target'       => 'sometimes|integer|min:0',
            'quantity'     => 'sometimes|integer|min:0',
            'entry_date'   => 'sometimes|date',
            'notes'        => 'nullable|string',
        ]));

        return response()->json($entry->fresh('recorder:id,firstName'));
    }

    public function revenueDestroy($id)
    {
        $entry = RevenueEntry::findOrFail($id);
        $this->assertHubAccess($entry->hub);
        $entry->delete();
        return response()->json(['message' => 'Revenue entry removed']);
    }

    /**
     * Per-hub projection that mirrors a single Hub sheet: each service line
     * with its split, plus daily/weekly/monthly roll-ups.
     */
    public function revenueProjection(Request $request, $hubId)
    {
        $this->assertHubAccess($hubId);
        $scheme = SharingScheme::current();

        $from = $request->query('from') ?: Carbon::today()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: Carbon::today()->endOfMonth()->toDateString();

        $entries = RevenueEntry::where('hub', $hubId)
            ->whereBetween('entry_date', [$from, $to])
            ->orderBy('entry_date')->get();

        $lines = $entries->map(function ($e) {
            return [
                'id'           => $e->id,
                'service_name' => $e->service_name,
                'unit_cost'    => $e->unit_cost,
                'target'       => $e->target,
                'quantity'     => $e->quantity,
                'entry_date'   => $e->entry_date?->toDateString(),
                'gross_total'  => $e->gross_total,
                'shares'       => $e->shares,
            ];
        });

        $grossDaily = round($entries->sum(fn ($e) => $e->gross_total), 2);
        $daily = $scheme->split($grossDaily);
        $weekly = $scheme->split($grossDaily * $scheme->weekly_multiplier);
        $monthly = $scheme->split($grossDaily * $scheme->weekly_multiplier * $scheme->monthly_multiplier);

        return response()->json([
            'hub'     => Hubs::with('lgas')->find($hubId),
            'scheme'  => $scheme,
            'lines'   => $lines,
            'rollup'  => ['daily' => $daily, 'weekly' => $weekly, 'monthly' => $monthly],
        ]);
    }

    private function assertHubAccess($hub): void
    {
        $ids = $this->scopedHubIds();
        if (is_array($ids) && $hub !== null && !in_array((int) $hub, array_map('intval', $ids), true)) {
            abort(403, 'You do not have access to this hub.');
        }
    }

    /* =======================================================================
     |  Invoicing & receivables
     * ===================================================================== */

    public function invoiceIndex(Request $request)
    {
        $query = $this->applyHubScope(Invoice::with('hub.lgas'));

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                  ->orWhere('invoice_number', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderByDesc('issue_date')->orderByDesc('id')
                ->paginate($request->query('per_page', 15))
        );
    }

    public function invoiceShow($id)
    {
        $invoice = Invoice::with('items', 'payments.recorder:id,firstName', 'hub.lgas')->findOrFail($id);
        $this->assertHubAccess($invoice->hub);
        return response()->json($invoice);
    }

    public function invoiceStore(Request $request)
    {
        $data = $request->validate([
            'hub'          => 'nullable|integer',
            'client_name'  => 'required|string|max:255',
            'client_email' => 'nullable|email',
            'client_phone' => 'nullable|string|max:40',
            'issue_date'   => 'required|date',
            'due_date'     => 'nullable|date',
            'tax_pct'      => 'nullable|numeric|min:0|max:100',
            'status'       => 'nullable|string|max:20',
            'notes'        => 'nullable|string',
            'items'                 => 'required|array|min:1',
            'items.*.description'   => 'required|string|max:255',
            'items.*.quantity'      => 'required|numeric|min:0',
            'items.*.unit_price'    => 'required|numeric|min:0',
        ]);

        $this->assertHubAccess($data['hub'] ?? null);

        $invoice = DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'hub'          => $data['hub'] ?? null,
                'client_name'  => $data['client_name'],
                'client_email' => $data['client_email'] ?? null,
                'client_phone' => $data['client_phone'] ?? null,
                'issue_date'   => $data['issue_date'],
                'due_date'     => $data['due_date'] ?? null,
                'tax_pct'      => $data['tax_pct'] ?? 0,
                'status'       => $data['status'] ?? 'draft',
                'notes'        => $data['notes'] ?? null,
                'created_by'   => Auth::id(),
            ]);

            foreach ($data['items'] as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'amount'      => round($item['quantity'] * $item['unit_price'], 2),
                ]);
            }

            $invoice->recalcTotals();
            return $invoice;
        });

        return response()->json($invoice->load('items'), 201);
    }

    public function invoiceUpdate(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $this->assertHubAccess($invoice->hub);

        $data = $request->validate([
            'client_name'  => 'sometimes|string|max:255',
            'client_email' => 'nullable|email',
            'client_phone' => 'nullable|string|max:40',
            'issue_date'   => 'sometimes|date',
            'due_date'     => 'nullable|date',
            'tax_pct'      => 'nullable|numeric|min:0|max:100',
            'status'       => 'sometimes|string|max:20',
            'notes'        => 'nullable|string',
            'items'                 => 'sometimes|array|min:1',
            'items.*.description'   => 'required_with:items|string|max:255',
            'items.*.quantity'      => 'required_with:items|numeric|min:0',
            'items.*.unit_price'    => 'required_with:items|numeric|min:0',
        ]);

        DB::transaction(function () use ($invoice, $data) {
            $invoice->update(collect($data)->except('items')->toArray());

            if (isset($data['items'])) {
                $invoice->items()->delete();
                foreach ($data['items'] as $item) {
                    $invoice->items()->create([
                        'description' => $item['description'],
                        'quantity'    => $item['quantity'],
                        'unit_price'  => $item['unit_price'],
                        'amount'      => round($item['quantity'] * $item['unit_price'], 2),
                    ]);
                }
            }
            $invoice->recalcTotals();
        });

        return response()->json($invoice->fresh('items'));
    }

    public function invoiceRecordPayment(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $this->assertHubAccess($invoice->hub);

        $data = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'paid_at'   => 'required|date',
            'method'    => 'nullable|string|max:40',
            'reference' => 'nullable|string|max:255',
        ]);
        $data['invoice_id'] = $invoice->id;
        $data['recorded_by'] = Auth::id();

        DB::transaction(function () use ($invoice, $data) {
            Payment::create($data);
            $invoice->amount_paid = round($invoice->payments()->sum('amount'), 2);
            // Promote status as money arrives (leave drafts/sent alone otherwise)
            if ($invoice->amount_paid >= $invoice->total && $invoice->total > 0) {
                $invoice->status = 'paid';
            } elseif ($invoice->amount_paid > 0) {
                $invoice->status = 'partial';
            }
            $invoice->save();
        });

        return response()->json($invoice->fresh('items', 'payments.recorder:id,firstName'));
    }

    public function invoiceVoid($id)
    {
        $invoice = Invoice::findOrFail($id);
        $this->assertHubAccess($invoice->hub);
        $invoice->update(['status' => 'void']);
        return response()->json($invoice);
    }

    private function nextInvoiceNumber(): string
    {
        $seq = Invoice::whereYear('created_at', now()->year)->count() + 1;
        return 'INV-' . now()->format('Y') . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /* =======================================================================
     |  Donor / grant / investment tracking
     * ===================================================================== */

    public function fundingIndex(Request $request)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can view funding.');
        $query = FundingSource::query();
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function fundingShow($id)
    {
        abort_unless($this->isManager(), 403);
        return response()->json(
            FundingSource::with('transactions.recorder:id,firstName')->findOrFail($id)
        );
    }

    public function fundingStore(Request $request)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can manage funding.');
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'type'                => 'required|in:donor,grant,investment',
            'organization'        => 'nullable|string|max:255',
            'contact_email'       => 'nullable|email',
            'currency'            => 'nullable|string|max:8',
            'total_committed'     => 'required|numeric|min:0',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date',
            'status'              => 'nullable|in:pledged,active,closed',
            'purpose'             => 'nullable|string|max:255',
            'equity_pct'          => 'nullable|numeric|min:0|max:100',
            'expected_return_pct' => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
        ]);
        $data['created_by'] = Auth::id();
        return response()->json(FundingSource::create($data), 201);
    }

    public function fundingUpdate(Request $request, $id)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can manage funding.');
        $source = FundingSource::findOrFail($id);
        $source->update($request->validate([
            'name'                => 'sometimes|string|max:255',
            'type'                => 'sometimes|in:donor,grant,investment',
            'organization'        => 'nullable|string|max:255',
            'contact_email'       => 'nullable|email',
            'currency'            => 'nullable|string|max:8',
            'total_committed'     => 'sometimes|numeric|min:0',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date',
            'status'              => 'nullable|in:pledged,active,closed',
            'purpose'             => 'nullable|string|max:255',
            'equity_pct'          => 'nullable|numeric|min:0|max:100',
            'expected_return_pct' => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
        ]));
        return response()->json($source->fresh());
    }

    public function fundingTransactionStore(Request $request, $id)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can manage funding.');
        $source = FundingSource::findOrFail($id);
        $data = $request->validate([
            'type'             => 'required|in:pledge,disbursement,expense,return_payout',
            'amount'           => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'reference'        => 'nullable|string|max:255',
            'notes'            => 'nullable|string',
        ]);
        $data['funding_source_id'] = $source->id;
        $data['recorded_by'] = Auth::id();

        $txn = FundingTransaction::create($data);
        return response()->json([
            'transaction' => $txn,
            'source'      => $source->fresh(),
        ], 201);
    }
}