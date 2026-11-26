<?php

namespace App\Http\Controllers\Business\Sales;

use App\Http\Controllers\Controller;
use App\Models\{Agent, ComplexResidentialUnit, Lead, PropertySale, PropertySalePayment};
use App\Services\Sales\PropertySaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Selling houses (the Sales module). Who sees a sale follows the property limit of their role (PropertySale
 * scope); what they can do follows sales_management (view, create, payments, complete, cancel).
 */
class PropertySaleController extends Controller
{
    /** Routes sit behind the `module:sales` middleware. */
    public function __construct(private PropertySaleService $sales) {}

    private function can(string $action): bool
    {
        return auth()->user()->hasBusinessPermission('sales_management', $action);
    }

    private function find(string $reference): PropertySale
    {
        return PropertySale::where('business_id', business_owner_id())->where('reference', $reference)->firstOrFail();
    }

    public function index(Request $request)
    {
        $base = PropertySale::where('business_id', business_owner_id());
        $status = in_array($request->query('status'), array_keys(PropertySale::STATUSES), true) ? $request->query('status') : null;
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 120);
        $sales = (clone $base)->with(['complex:id,title,slug', 'unit:id,unit_number,unit_category,house_type,bedrooms,bathrooms', 'agent:id,first_name,last_name', 'payments', 'instalments'])
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->orderByRaw("case when status in ('reserved','agreed') then 0 else 1 end"))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw('LOWER(buyer_name) LIKE ?', ['%' . mb_strtolower($search) . '%'])
                ->orWhereRaw('LOWER(reference) LIKE ?', ['%' . mb_strtolower($search) . '%'])))
            ->when($request->filled('complex_id'), fn ($q) => $q->where('complex_id', (int) $request->query('complex_id')))
            ->latest('agreement_date')->latest('id')->paginate(25)->withQueryString();

        $live = (clone $base)->whereIn('status', PropertySale::LIVE)->with(['payments', 'instalments'])->get();
        $stats = [
            'live' => $live->count(),
            'value' => round($live->sum('price'), 2),
            'collected' => round($live->sum(fn ($s) => $s->collected()), 2),
            'overdue' => round($live->sum(fn ($s) => $s->overdue()->sum(fn ($i) => $i->outstanding())), 2),
            'overdue_count' => $live->filter(fn ($s) => $s->overdue()->isNotEmpty())->count(),
            'completed' => (clone $base)->where('status', 'completed')->whereYear('completed_at', now()->year)->count(),
        ];
        $forSale = ComplexResidentialUnit::where('unit_category', 'house')->whereIn('offer', ['sale', 'both'])->where('status', 'available')
            ->whereIn('complex_id', \App\Models\Complex::forBusiness()->select('id'))->count();
        $canCreate = $this->can('create');
        $complexes = \App\Models\Complex::forBusiness()->whereIn('id', PropertySale::where('business_id', business_owner_id())->select('complex_id'))->orderBy('title')->get(['id', 'title']);
        return view('pages.business.sales.index', compact('sales', 'stats', 'status', 'search', 'forSale', 'canCreate', 'complexes'));
    }

    public function create(Request $request)
    {
        $unit = $request->filled('unit') ? ComplexResidentialUnit::with(['complex', 'complexUnit'])->whereIn('complex_id', \App\Models\Complex::forBusiness()->select('id'))->findOrFail((int) $request->query('unit')) : null;
        $lead = $request->filled('lead') ? Lead::forBusiness()->with('profile')->find((int) $request->query('lead')) : null;
        // Houses that can be sold now, for the picker.
        $houses = ComplexResidentialUnit::with('complex:id,title')->where('unit_category', 'house')->whereIn('offer', ['sale', 'both'])->where('status', 'available')
            ->whereIn('complex_id', \App\Models\Complex::forBusiness()->select('id'))
            ->whereDoesntHave('activeTenancies')->orderBy('complex_id')->orderBy('unit_number')->limit(500)->get();
        $blocker = $unit ? $this->sales->blocker($unit) : null;
        $defaultAgent = $unit ? app(\App\Services\Agents\AgentAssignmentService::class)->defaultAgentFor($unit->complex, $unit) : null;
        return view('pages.business.sales.create', compact('unit', 'lead', 'houses', 'blocker', 'defaultAgent'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id' => 'required|integer',
            'buyer_name' => 'required|string|max:255', 'buyer_phone' => 'nullable|string|max:40', 'buyer_email' => 'nullable|email|max:255',
            'buyer_id_number' => 'nullable|string|max:60', 'buyer_kra_pin' => 'nullable|string|max:30',
            'price' => 'required|numeric|min:1|max:9999999999999', 'agreement_date' => 'required|date|before_or_equal:' . now()->addYear()->toDateString(),
            'deposit_amount' => 'nullable|numeric|min:0', 'deposit_due_date' => 'nullable|date',
            'plan' => 'required|in:equal,custom', 'instalments' => 'required_if:plan,equal|nullable|integer|min:0|max:360',
            'first_due_date' => 'nullable|date', 'frequency' => 'nullable|in:monthly,quarterly,yearly',
            'schedule' => 'required_if:plan,custom|nullable|array|max:120', 'schedule.*.due_date' => 'nullable|date', 'schedule.*.amount' => 'nullable|numeric|min:0',
            'agent_id' => 'nullable|integer', 'lead_id' => 'nullable|integer', 'notes' => 'nullable|string|max:5000',
        ], [], ['unit_id' => 'house', 'price' => 'sale price']);
        // Only houses this person can see.
        $unit = ComplexResidentialUnit::whereIn('complex_id', \App\Models\Complex::forBusiness()->select('id'))->findOrFail($data['unit_id']);
        $sale = $this->sales->start($unit, $data);
        return redirect()->route('sales.show', $sale->reference)->with('success', 'Sale ' . $sale->reference . ' started. The house is now under offer.');
    }

    public function show(string $reference)
    {
        $sale = $this->find($reference)->load(['complex', 'unit.complexUnit', 'agent', 'lead.profile', 'creator', 'instalments.allocations', 'payments.recorder', 'payments.allocations.instalment']);
        $commission = \App\Models\CommissionEntry::where('sale_id', $sale->id)->get();
        $canSeeCommission = $sale->agent && ($this->can('payments') || \App\Services\Agents\AgentProfileService::canSeeCommissionOf($sale->agent));
        return view('pages.business.sales.show', [
            'sale' => $sale, 'commission' => $canSeeCommission ? $commission : collect(), 'canSeeCommission' => $canSeeCommission,
            'canPay' => $this->can('payments'), 'canComplete' => $this->can('complete'), 'canCancel' => $this->can('cancel'),
        ]);
    }

    public function recordPayment(Request $request, string $reference)
    {
        $sale = $this->find($reference);
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01', 'paid_on' => 'required|date|before_or_equal:today',
            'method' => 'required|in:' . implode(',', array_keys(config('property.sale_payment_methods'))), 'reference' => 'nullable|string|max:120',
        ]);
        $payment = $this->sales->recordPayment($sale, $data);
        return redirect()->route('sales.show', $sale->reference)->with('success', 'Payment recorded. Receipt ' . $payment->receipt_number . '.');
    }

    public function reversePayment(Request $request, string $reference, int $payment)
    {
        $sale = $this->find($reference);
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        $this->sales->reverse(PropertySalePayment::where('sale_id', $sale->id)->findOrFail($payment), $data['reason']);
        return redirect()->route('sales.show', $sale->reference)->with('success', 'Payment reversed. Any commission on it was reversed too.');
    }

    public function complete(Request $request, string $reference)
    {
        $sale = $this->find($reference);
        $data = $request->validate(['completion_note' => 'nullable|string|max:1000']);
        $this->sales->complete($sale, $data['completion_note'] ?? null);
        return redirect()->route('sales.show', $sale->reference)->with('success', 'Sale completed. The house is recorded as sold.');
    }

    public function titleTransferred(Request $request, string $reference)
    {
        $sale = $this->find($reference);
        $data = $request->validate(['title_transferred_at' => 'required|date|before_or_equal:today']);
        $this->sales->titleTransferred($sale, $data['title_transferred_at']);
        return redirect()->route('sales.show', $sale->reference)->with('success', 'Title transfer recorded.');
    }

    public function cancel(Request $request, string $reference)
    {
        $sale = $this->find($reference);
        $data = $request->validate(['cancel_reason' => 'required|string|min:5|max:1000', 'refund' => 'nullable|boolean']);
        $this->sales->cancel($sale, $data['cancel_reason'], (bool) ($data['refund'] ?? false));
        return redirect()->route('sales.show', $sale->reference)->with('success', 'Sale cancelled. The house is back on the market.');
    }

    /** A receipt for one buyer payment. */
    public function receipt(string $reference, int $payment)
    {
        $sale = $this->find($reference)->load(['complex', 'unit', 'payments']);
        $payment = $sale->payments->firstWhere('id', $payment) ?? abort(404);
        $payment->load('allocations.instalment');
        return Pdf::loadView('pdf.sale_receipt', ['sale' => $sale, 'payment' => $payment, 'business' => $this->businessName()])
            ->setPaper('a5')->download('receipt-' . $payment->receipt_number . '.pdf');
    }

    /** The buyer's statement: plan, payments and balance. */
    public function statement(string $reference)
    {
        $sale = $this->find($reference)->load(['complex', 'unit', 'instalments', 'payments']);
        return Pdf::loadView('pdf.sale_statement', ['sale' => $sale, 'business' => $this->businessName()])
            ->setPaper('a4')->download('sale-statement-' . $sale->reference . '.pdf');
    }

    private function businessName(): string
    {
        return \Illuminate\Support\Facades\DB::table('businesses')->where('user_id', business_owner_id())->value('business_name') ?: config('app.name');
    }
}
