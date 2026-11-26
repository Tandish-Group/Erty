<?php

namespace App\Http\Controllers\Business\Expenses;

use Illuminate\Http\Request;
use App\Models\CommercialUnit;
use App\Models\Complex;
use App\Models\ComplexExpense;
use App\Models\ComplexResidentialUnit;
use App\Models\ExpenseType;
use App\Http\Controllers\Controller;


class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function allow(string $action): void
    {
        abort_unless(app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'expense_management', $action), 403);
    }

    // ── Page ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $this->allow('view');
        $workflowAvailable = app(\App\Services\Properties\PropertyExpenseWorkflow::class)->available();
        $userId       = business_owner_id();
        $complexes    = Complex::forBusiness($userId)->orderBy('title')->get(['id', 'title', 'slug']);
        $expenseTypes = ExpenseType::orderBy('expense_type')->get(['id', 'expense_type']);

        $preselectComplexId    = null;
        $preselectComplexTitle = null;

        if (request()->has('complex_id')) {
            try {
                $decryptedId = decrypt(request('complex_id'));
                $complex = Complex::where('id', $decryptedId)
                    ->where('user_id', $userId)
                    ->first(['id', 'title']);
                if ($complex) {
                    $preselectComplexId    = $complex->id;
                    $preselectComplexTitle = $complex->title;
                }
            } catch (\Exception $e) {
                // Invalid or tampered token — silently ignore
            }
        }

        return view('pages.business.expenses.index', compact(
            'complexes', 'expenseTypes', 'preselectComplexId', 'preselectComplexTitle', 'workflowAvailable'
        ))->with('expenseCapabilities', collect(['create', 'edit', 'delete'])->mapWithKeys(fn ($action) => [$action => app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'expense_management', $action)])->all());
    }

    public function create(Request $request)
    {
        $this->allow('create');

        $userId = business_owner_id();
        $complexes = Complex::forBusiness($userId)->orderBy('title')->get(['id', 'title']);
        $expenseTypes = ExpenseType::orderBy('expense_type')->get(['id', 'expense_type']);
        $selectedComplexId = null;

        if ($request->filled('complex_id')) {
            $selectedComplexId = Complex::forBusiness($userId)
                ->where('id', $request->input('complex_id'))
                ->value('id');
        }

        return view('pages.business.expenses.create', compact('complexes', 'expenseTypes', 'selectedComplexId'));
    }

    // ── AJAX: paginated expense list ─────────────────────────────────────────

    public function exportLedger(Request $request)
    {
        $this->allow('export');
        $filters = $request->validate(['date_from' => 'nullable|date', 'date_to' => 'nullable|date', 'complex_id' => 'nullable|integer', 'scope' => 'nullable|in:complex,residential_unit,commercial_unit', 'category' => 'nullable|integer', 'q' => 'nullable|string|max:255', 'unit_q' => 'nullable|string|max:255', 'workflow_status' => 'nullable|in:unreviewed,draft,pending,approved,returned,void']);
        $query = ComplexExpense::join('complexes', 'complexes.id', '=', 'complex_expenses.complex_id')->where('complexes.user_id', business_owner_id())->where('complex_expenses.transaction_type', 'expense')->select('complex_expenses.*', 'complexes.title as complex_title');
        $this->applyExpenseFilters($query, $filters);
        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Property', 'Scope', 'Unit', 'Category', 'Description', 'Currency', 'Amount', 'Date', 'Workflow status'], ',', '"', '');
            foreach ($query->with(['expenseType', 'residentialUnit', 'commercialUnit'])->orderBy('complex_expenses.id')->lazy(250) as $record) {
                $data = $this->formatExpense($record);
                $row = [$data['id'], $data['complex_title'], $data['scope'], $data['unit_ref'], $data['category'], $data['description'], $data['currency'], $record->amount, $data['expense_date'], $data['workflow_status'] ?? 'unreviewed'];
                $row = array_map(fn ($value) => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".$value : $value, $row);
                fputcsv($output, $row, ',', '"', '');
            }
            fclose($output);
        }, 'property-expenses.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function getData(Request $request)
    {
        $this->allow('view');
        $userId = business_owner_id();

        $validated = $request->validate([
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date'],
            'complex_id' => ['nullable', 'integer'],
            'scope'      => ['nullable', 'in:complex,residential_unit,commercial_unit'],
            'page'       => ['nullable', 'integer', 'min:1'],
            'category' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:255'],
            'unit_q' => ['nullable', 'string', 'max:255'],
            'workflow_status' => ['nullable', 'in:unreviewed,draft,pending,approved,returned,void'],
        ]);

        $perPage = 25;
        $page    = max(1, (int) ($validated['page'] ?? 1));

        $query = ComplexExpense::query()
            ->join('complexes', 'complexes.id', '=', 'complex_expenses.complex_id')
            ->where('complexes.user_id', $userId)
            ->where('complex_expenses.transaction_type', 'expense')
            ->select('complex_expenses.*', 'complexes.title as complex_title');

        $this->applyExpenseFilters($query, $validated);

        $total = $query->count();
        if (app(\App\Services\Properties\PropertyExpenseWorkflow::class)->available()) {
            $query->selectSub(\Illuminate\Support\Facades\DB::table('property_expense_payments')->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('expense_id', 'complex_expenses.id'), 'paid_total');
        }

        $expenses = $query
            ->with(['expenseType', 'residentialUnit', 'commercialUnit'])
            ->orderBy('complex_expenses.expense_date', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $summaryBase = ComplexExpense::query()
            ->join('complexes', 'complexes.id', '=', 'complex_expenses.complex_id')
            ->where('complexes.user_id', $userId);

        $this->applyExpenseFilters($summaryBase, $validated);

        $currencyTotals = (clone $summaryBase)->selectRaw('complex_expenses.currency, SUM(complex_expenses.amount) as total')->groupBy('complex_expenses.currency')->get();
        $summary = $summaryBase->selectRaw("
            COUNT(*) as total_count,
            SUM(complex_expenses.amount) as total_amount,
            SUM(CASE WHEN complex_expenses.scope = 'complex'          THEN 1 ELSE 0 END) as complex_count,
            SUM(CASE WHEN complex_expenses.scope = 'residential_unit' THEN 1 ELSE 0 END) as residential_count,
            SUM(CASE WHEN complex_expenses.scope = 'commercial_unit'  THEN 1 ELSE 0 END) as commercial_count
        ")->first();

        return response()->json([
            'status' => 200,
            'data'   => [
                'expenses'   => $expenses->map(fn ($e) => $this->formatExpense($e)),
                'summary'    => [
                    'total_count'       => (int)   ($summary->total_count       ?? 0),
                    'currency_totals'   => $currencyTotals,
                    'complex_count'     => (int)   ($summary->complex_count     ?? 0),
                    'residential_count' => (int)   ($summary->residential_count ?? 0),
                    'commercial_count'  => (int)   ($summary->commercial_count  ?? 0),
                ],
                'pagination' => [
                    'total'    => $total,
                    'per_page' => $perPage,
                    'page'     => $page,
                    'pages'    => (int) ceil($total / $perPage),
                ],
            ],
        ]);
    }

    // ── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->allow('create');
        $validated = $request->validate([
            'confirmed' => ['accepted'],
            'allocation' => ['required', 'in:per_unit,split_total'],
            'complex_id'            => ['required', 'integer'],
            'scope'                 => ['required', 'in:complex,residential_unit,commercial_unit'],
            'residential_unit_ids'  => ['nullable', 'array', 'min:1', 'max:500', 'required_if:scope,residential_unit'],
            'residential_unit_ids.*'=> ['integer', 'distinct'],
            'commercial_unit_ids'   => ['nullable', 'array', 'min:1', 'max:500', 'required_if:scope,commercial_unit'],
            'commercial_unit_ids.*' => ['integer', 'distinct'],
            'type'                  => ['nullable', 'integer'],
            'description'           => ['required', 'string', 'max:1000'],
            'amount'                => ['required', 'numeric', 'min:0.01', 'max:999999999', 'decimal:0,2'],
            'currency'              => ['required', 'in:KES,USD,EUR,GBP,ZAR,UGX,TZS'],
            'expense_date'          => ['required', 'date'],
        ]);

        $userId  = business_owner_id();
        $complex = Complex::where('id', $validated['complex_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $this->verifyUnitsForStore($validated, $complex);

        $scope    = $validated['scope'];
        $baseData = [
            'complex_id'       => $complex->id,
            'scope'            => $scope,
            'type'             => $validated['type'] ?? null,
            'description'      => $validated['description'],
            'amount'           => $validated['amount'],
            'currency'         => $validated['currency'],
            'expense_date'     => $validated['expense_date'],
            'listing_type'     => 'complex',
            'transaction_type' => 'expense',
            'user_id'          => $userId,
        ];

        $unitIds = $scope === 'residential_unit' ? $validated['residential_unit_ids'] : ($scope === 'commercial_unit' ? $validated['commercial_unit_ids'] : [null]);
        $count = count($unitIds);
        $minor = (int) round((float) $validated['amount'] * 100);
        $split = $validated['allocation'] === 'split_total';
        if ($split && $minor < $count) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'The total must allocate at least 0.01 to each selected unit.']);
        }
        $workflow = app(\App\Services\Properties\PropertyExpenseWorkflow::class);
        $workflowEnabled = $workflow->available();
        $group = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::transaction(function () use ($unitIds, $baseData, $scope, $minor, $split, $count, $workflow, $workflowEnabled, $group) {
            foreach (array_values($unitIds) as $index => $unitId) {
                $share = $split ? intdiv($minor, $count) + ($index < $minor % $count ? 1 : 0) : $minor;
                $created = ComplexExpense::create(array_merge($baseData, [
                    'amount' => number_format($share / 100, 2, '.', ''),
                    'residential_unit_id' => $scope === 'residential_unit' ? $unitId : null,
                    'commercial_unit_id' => $scope === 'commercial_unit' ? $unitId : null,
                ]));
                if ($workflowEnabled) {
                    $created->forceFill(['workflow_status' => 'draft', 'allocation_group' => $group])->save();
                    $workflow->event($created, 'created', ['allocation' => $split ? 'split_total' : 'per_unit', 'group' => $group, 'records' => $count, 'entered_minor' => $minor, 'allocated_minor' => $share]);
                }
            }
        });

        $message = $count === 1
            ? 'Expense recorded successfully.'
            : "{$count} expenses recorded successfully.";

        return response()->json(['status' => 200, 'message' => $message]);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function updateExpense(Request $request, int $id)
    {
        $this->allow('edit');
        $validated = $request->validate([
            'confirmed' => ['accepted'],
            'complex_id'          => ['required', 'integer'],
            'scope'               => ['required', 'in:complex,residential_unit,commercial_unit'],
            'residential_unit_id' => ['nullable', 'integer', 'required_if:scope,residential_unit'],
            'commercial_unit_id'  => ['nullable', 'integer', 'required_if:scope,commercial_unit'],
            'type'                => ['nullable', 'integer'],
            'description'         => ['required', 'string', 'max:1000'],
            'amount'              => ['required', 'numeric', 'min:0.01', 'max:999999999', 'decimal:0,2'],
            'currency'            => ['required', 'in:KES,USD,EUR,GBP,ZAR,UGX,TZS'],
            'expense_date'        => ['required', 'date'],
        ]);

        $userId  = business_owner_id();
        return \Illuminate\Support\Facades\DB::transaction(function () use ($id, $validated, $userId) {
        $expense = ComplexExpense::lockForUpdate()->findOrFail($id);
        abort_unless($expense->transaction_type === 'expense', 404);
        // Verify current ownership
        Complex::where('id', $expense->complex_id)->where('user_id', $userId)->firstOrFail();

        $complex = Complex::where('id', $validated['complex_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $this->verifyUnitBelongsToComplex($validated, $complex);
        $workflow = app(\App\Services\Properties\PropertyExpenseWorkflow::class);
        if ($workflow->available()) $workflow->assertEditable($expense);
        $before = $expense->getAttributes();

        $expense->update([
            'complex_id'          => $complex->id,
            'scope'               => $validated['scope'],
            'residential_unit_id' => $validated['scope'] === 'residential_unit' ? ($validated['residential_unit_id'] ?? null) : null,
            'commercial_unit_id'  => $validated['scope'] === 'commercial_unit'  ? ($validated['commercial_unit_id']  ?? null) : null,
            'type'                => $validated['type'] ?? null,
            'description'         => $validated['description'],
            'amount'              => $validated['amount'],
            'currency'            => $validated['currency'],
            'expense_date'        => $validated['expense_date'],
        ]);

        if ($workflow->available()) $workflow->event($expense, 'corrected', ['before' => $before, 'after' => $expense->getAttributes()]);
        return response()->json(['status' => 200, 'message' => 'Expense updated successfully.']);
        });
    }

    // ── Destroy ──────────────────────────────────────────────────────────────

    public function destroyExpense(int $id)
    {
        $this->allow('delete');
        abort_if(app(\App\Services\Properties\PropertyExpenseWorkflow::class)->available(), 422, 'Use the workflow to void an unpaid expense with a reason. Financial history is retained.');
        $userId  = business_owner_id();
        $expense = ComplexExpense::findOrFail($id);
        abort_unless($expense->transaction_type === 'expense', 404);
        Complex::where('id', $expense->complex_id)->where('user_id', $userId)->firstOrFail();

        $expense->delete();

        return response()->json(['status' => 200, 'message' => 'Expense deleted.']);
    }

    // ── AJAX: units for a complex ─────────────────────────────────────────────

    public function getComplexUnits(int $complexId)
    {
        $this->allow('view');
        $userId  = business_owner_id();
        $complex = Complex::where('id', $complexId)->where('user_id', $userId)->firstOrFail();

        $residential = ComplexResidentialUnit::where('complex_id', $complex->id)
            ->get(['id', 'unit_number', 'floor', 'status'])
            ->map(fn ($u) => [
                'id'    => $u->id,
                'type'  => 'residential',
                'label' => 'Unit ' . ($u->unit_number ?? '#' . $u->id)
                         . ($u->floor !== null ? ' (Floor ' . $u->floor . ')' : ''),
            ]);

        $commercial = CommercialUnit::where('complex_id', $complex->id)
            ->get(['id', 'unit_number', 'status'])
            ->map(fn ($u) => [
                'id'    => $u->id,
                'type'  => 'commercial',
                'label' => 'Unit ' . ($u->unit_number ?? '#' . $u->id),
            ]);

        return response()->json([
            'status'      => 200,
            'residential' => $residential,
            'commercial'  => $commercial,
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function applyExpenseFilters($query, array $filters): void
    {
        if (!empty($filters['unit_q'])) {
            $term = '%'.mb_strtolower($filters['unit_q']).'%';
            $query->where(fn ($q) => $q->whereHas('residentialUnit', fn ($u) => $u->whereRaw('LOWER(unit_number) LIKE ?', [$term]))->orWhereHas('commercialUnit', fn ($u) => $u->whereRaw('LOWER(unit_number) LIKE ?', [$term])));
        }
        if (!empty($filters['category'])) $query->where('complex_expenses.type', $filters['category']);
        if (!empty($filters['q'])) $query->whereRaw('LOWER(complex_expenses.description) LIKE ?', ['%'.mb_strtolower($filters['q']).'%']);
        if (app(\App\Services\Properties\PropertyExpenseWorkflow::class)->available()) {
            if (!empty($filters['workflow_status'])) $query->where('complex_expenses.workflow_status', $filters['workflow_status']);
            else $query->where('complex_expenses.workflow_status', '!=', 'void');
        }
        if (!empty($filters['complex_id'])) {
            $query->where('complex_expenses.complex_id', $filters['complex_id']);
        }
        if (!empty($filters['scope'])) {
            $query->where('complex_expenses.scope', $filters['scope']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('complex_expenses.expense_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('complex_expenses.expense_date', '<=', $filters['date_to']);
        }
    }

    // Used by updateExpense() — single unit
    private function verifyUnitBelongsToComplex(array $validated, Complex $complex): void
    {
        if ($validated['scope'] === 'residential_unit' && !empty($validated['residential_unit_id'])) {
            ComplexResidentialUnit::where('id', $validated['residential_unit_id'])
                ->where('complex_id', $complex->id)
                ->firstOrFail();
        }
        if ($validated['scope'] === 'commercial_unit' && !empty($validated['commercial_unit_id'])) {
            CommercialUnit::where('id', $validated['commercial_unit_id'])
                ->where('complex_id', $complex->id)
                ->firstOrFail();
        }
    }

    // Used by store() — array of unit IDs
    private function verifyUnitsForStore(array $validated, Complex $complex): void
    {
        if ($validated['scope'] === 'residential_unit' && !empty($validated['residential_unit_ids'])) {
            foreach ($validated['residential_unit_ids'] as $unitId) {
                ComplexResidentialUnit::where('id', $unitId)
                    ->where('complex_id', $complex->id)
                    ->firstOrFail();
            }
        }
        if ($validated['scope'] === 'commercial_unit' && !empty($validated['commercial_unit_ids'])) {
            foreach ($validated['commercial_unit_ids'] as $unitId) {
                CommercialUnit::where('id', $unitId)
                    ->where('complex_id', $complex->id)
                    ->firstOrFail();
            }
        }
    }

    private function formatExpense(ComplexExpense $e): array
    {
        $scope   = $e->scope ?? 'complex';
        $unitRef = '—';

        if ($scope === 'residential_unit' && $e->residentialUnit) {
            $u       = $e->residentialUnit;
            $unitRef = 'Unit ' . ($u->unit_number ?? '#' . $u->id)
                     . ($u->floor !== null ? ' (Floor ' . $u->floor . ')' : '');
        } elseif ($scope === 'commercial_unit' && $e->commercialUnit) {
            $u       = $e->commercialUnit;
            $unitRef = 'Unit ' . ($u->unit_number ?? '#' . $u->id);
        }

        return [
            'id'                  => $e->id,
            'workflow_status' => $e->workflow_status,
            'payment_status' => !$e->workflow_status ? null : (($e->paid_total ?? 0) >= $e->amount ? 'Paid' : (($e->paid_total ?? 0) > 0 ? 'Part-paid' : 'Unpaid')),
            'paid_total' => $e->paid_total ?? 0,
            'complex_id'          => $e->complex_id,
            'complex_title'       => $e->complex_title ?? '',
            'scope'               => $scope,
            'residential_unit_id' => $e->residential_unit_id,
            'commercial_unit_id'  => $e->commercial_unit_id,
            'unit_ref'            => $unitRef,
            'expense_type_id'     => $e->type,
            'category'            => $e->expenseType?->expense_type ?? '—',
            'description'         => $e->description,
            'amount'              => (float) $e->amount,
            'currency'            => $e->currency,
            'expense_date'        => $e->expense_date,
        ];
    }
    
}
