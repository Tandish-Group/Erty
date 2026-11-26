<?php

namespace App\Http\Controllers\Business\Expenses;

use App\Http\Controllers\Controller;
use App\Models\{Complex, ComplexExpense, User};
use App\Services\Access\BusinessPermissionService;
use App\Services\Properties\PropertyExpenseWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class PropertyExpenseWorkflowController extends Controller
{

    private function allow(string $action): void
    {
        abort_unless(app(BusinessPermissionService::class)->can(auth()->user(), 'expense_management', $action), 403);
        abort_unless(app(PropertyExpenseWorkflow::class)->available(), 503, 'Expense workflow migration must be applied first.');
    }

    private function record(int $id, bool $lock = false): ComplexExpense
    {
        $query = ComplexExpense::where('transaction_type', 'expense')->whereIn('complex_id', Complex::forBusiness()->select('id'));
        return ($lock ? $query->lockForUpdate() : $query)->findOrFail($id);
    }

    public function show(int $id)
    {
        $this->allow('view');
        $expense = $this->record($id);
        $property = Complex::findOrFail($expense->complex_id);
        $service = app(PropertyExpenseWorkflow::class);
        $paid = $service->paid($expense);
        $balance = PropertyExpenseWorkflow::cents((string) $expense->amount) - $paid;
        $events = DB::table('property_expense_events')->where('expense_id', $id)->orderByDesc('id')->get();
        $payments = DB::table('property_expense_payments')->where('expense_id', $id)->orderByDesc('id')->get();
        $evidence = DB::table('property_expense_evidence')->where('expense_id', $id)->get();
        $reviewers = User::where(fn ($q) => $q->where('id', business_owner_id())->orWhere('main_account_user_id', business_owner_id()))->get()->filter(fn ($user) => app(BusinessPermissionService::class)->can($user, 'expense_management', 'approve'));
        $capabilities = collect(['edit', 'approve', 'pay', 'delete', 'create'])->mapWithKeys(fn ($action) => [$action => app(BusinessPermissionService::class)->can(auth()->user(), 'expense_management', $action)])->all();
        $capabilities['approve'] = $capabilities['approve'] && (! auth()->user()->is_sub_account || (int) $expense->reviewer_id === (int) auth()->id());
        $editable = in_array($expense->workflow_status, ['unreviewed', 'draft', 'returned'], true);
        $categories = \App\Models\ExpenseType::orderBy('expense_type')->get();
        return view('pages.business.expenses.workflow', compact('expense', 'property', 'paid', 'balance', 'events', 'payments', 'evidence', 'reviewers', 'capabilities', 'editable', 'categories'));
    }

    public function act(Request $request, int $id)
    {
        $data = $request->validate(['action' => 'required|in:details,submit,approve,return,void,pay,recur', 'confirmed' => 'accepted']);
        $action = $data['action'];
        $this->allow(match ($action) { 'approve', 'return' => 'approve', 'pay' => 'pay', 'void' => 'delete', 'recur' => 'create', default => 'edit' });
        try {
        return DB::transaction(function () use ($request, $id, $action) {
            $expense = $this->record($id, true);
            $service = app(PropertyExpenseWorkflow::class);
            $before = $expense->getAttributes();
            $details = [];
            if ($action === 'details') {
                $service->assertEditable($expense);
                $details = $request->validate(['supplier' => 'required|string|max:255', 'invoice_reference' => 'nullable|string|max:255', 'due_date' => 'nullable|date', 'reviewer_id' => 'required|integer', 'recurrence' => 'required|in:none,monthly,quarterly,yearly', 'next_occurrence' => 'nullable|required_unless:recurrence,none|date|after:today']);
                $details += $request->validate(['type' => 'required|integer|exists:expense_types,id']);
                $reviewer = User::where(fn ($q) => $q->where('id', business_owner_id())->orWhere('main_account_user_id', business_owner_id()))->findOrFail($details['reviewer_id']);
                abort_unless(app(BusinessPermissionService::class)->can($reviewer, 'expense_management', 'approve'), 422, 'Choose an active reviewer with expense approval permission.');
                if ($details['recurrence'] === 'none') $details['next_occurrence'] = null;
                $expense->forceFill($details)->save();
            } elseif ($action === 'submit') {
                $service->assertEditable($expense);
                abort_unless(filled($expense->supplier) && $expense->reviewer_id && $expense->type, 422, 'Record supplier, expense category and reviewer before submission.');
                $details = $request->validate(['note' => 'nullable|string|max:1000']);
                abort_unless(DB::table('property_expense_evidence')->where('expense_id', $id)->exists() || filled($details['note'] ?? null), 422, 'Attach evidence or explain why evidence is unavailable.');
                $expense->forceFill(['workflow_status' => 'pending'])->save();
            } elseif (in_array($action, ['approve', 'return'], true)) {
                abort_unless($expense->workflow_status === 'pending', 422, 'Only a submitted expense can be reviewed.');
                abort_if(auth()->user()->is_sub_account && (int) $expense->reviewer_id !== (int) auth()->id(), 403, 'This review belongs to another employee.');
                $details = $request->validate(['note' => ($action === 'return' ? 'required' : 'nullable').'|string|max:1000']);
                $expense->forceFill(['workflow_status' => $action === 'approve' ? 'approved' : 'returned'])->save();
            } elseif ($action === 'void') {
                abort_if($expense->workflow_status === 'void' || $service->paid($expense) > 0, 422, 'Paid or already voided records cannot be voided.');
                $details = $request->validate(['note' => 'required|string|max:1000']);
                $expense->forceFill(['workflow_status' => 'void'])->save();
            } elseif ($action === 'pay') {
                abort_unless($expense->workflow_status === 'approved', 422, 'Approve the expense before recording payment.');
                $details = $request->validate(['amount' => 'required|numeric|min:0.01|max:999999999|decimal:0,2', 'paid_on' => 'required|date|before_or_equal:today', 'method' => 'required|in:bank,mobile_money,cash,card,other', 'reference' => 'required|string|max:255', 'submission_id' => 'required|uuid']);
                if (DB::table('property_expense_payments')->where('submission_id', $details['submission_id'])->exists()) return back()->with('success', 'This payment submission was already recorded.');
                abort_if(PropertyExpenseWorkflow::cents((string) $details['amount']) > PropertyExpenseWorkflow::cents((string) $expense->amount) - $service->paid($expense), 422, 'Payment exceeds the outstanding balance.');
                DB::table('property_expense_payments')->insert($details + ['expense_id' => $id, 'actor_id' => auth()->id(), 'created_at' => now()]);
            } elseif ($action === 'recur') {
                abort_unless($expense->workflow_status === 'approved' && $expense->recurrence !== 'none' && $expense->next_occurrence && $expense->next_occurrence <= now()->toDateString(), 422, 'Only an approved expense with a due recurrence can create the next draft.');
                $date = \Carbon\Carbon::parse($expense->next_occurrence);
                $next = $expense->replicate();
                $next->forceFill(['expense_date' => $date->toDateString(), 'workflow_status' => 'draft', 'invoice_reference' => null, 'due_date' => null, 'allocation_group' => (string) Str::uuid(), 'next_occurrence' => $date->copy()->addMonthsNoOverflow(match ($expense->recurrence) { 'monthly' => 1, 'quarterly' => 3, default => 12 })->toDateString()])->save();
                $expense->forceFill(['next_occurrence' => null])->save();
                $service->event($next, 'recurring_draft', ['source_expense' => $id]);
                $details = ['next_expense' => $next->id];
            }
            $service->event($expense, $action, ['before' => $before, 'after' => $expense->getAttributes(), 'input' => $details]);
            return redirect()->route('business.expenses.workflow', $id)->with('success', 'Expense workflow updated.');
        });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            if ($error->getStatusCode() !== 422) throw $error;
            return back()->withErrors(['workflow' => $error->getMessage()])->withInput();
        }
    }

    public function upload(Request $request, int $id)
    {
        $this->allow('edit');
        $request->validate(['evidence' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        $path = null;
        try {
            return DB::transaction(function () use ($request, $id, &$path) {
                $expense = $this->record($id, true);
                app(PropertyExpenseWorkflow::class)->assertEditable($expense);
                $path = $request->file('evidence')->store('private/expense-evidence', 'local');
                abort_unless($path, 500, 'Evidence upload failed.');
                DB::table('property_expense_evidence')->insert(['expense_id' => $id, 'actor_id' => auth()->id(), 'path' => $path, 'original_name' => basename($request->file('evidence')->getClientOriginalName()), 'created_at' => now()]);
                app(PropertyExpenseWorkflow::class)->event($expense, 'evidence_added', ['name' => basename($request->file('evidence')->getClientOriginalName())]);
                return back()->with('success', 'Evidence attached privately.');
            });
        } catch (\Throwable $error) {
            if ($path) Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    public function download(int $id, int $evidence)
    {
        $this->allow('view');
        $this->record($id);
        $file = DB::table('property_expense_evidence')->where('expense_id', $id)->where('id', $evidence)->first();
        abort_unless($file, 404);
        return Storage::disk('local')->download($file->path, $file->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
