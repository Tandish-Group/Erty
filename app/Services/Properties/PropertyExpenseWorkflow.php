<?php

namespace App\Services\Properties;

use App\Models\ComplexExpense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PropertyExpenseWorkflow
{
    public function available(): bool
    {
        return Schema::hasTable('property_expense_events') && Schema::hasColumn('complex_expenses', 'workflow_status');
    }

    public static function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    public function paid(ComplexExpense $expense): int
    {
        return DB::table('property_expense_payments')->where('expense_id', $expense->id)->pluck('amount')->sum(fn ($value) => self::cents((string) $value));
    }

    public function event(ComplexExpense $expense, string $action, array $details = []): void
    {
        DB::table('property_expense_events')->insert(['expense_id' => $expense->id, 'actor_id' => auth()->id(), 'action' => $action, 'details' => json_encode($details), 'created_at' => now()]);
    }

    public function assertEditable(ComplexExpense $expense): void
    {
        if (!in_array($expense->workflow_status, ['unreviewed', 'draft', 'returned'], true) || $this->paid($expense) > 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['expense' => 'Only unpaid drafts or returned records may be corrected. Approved or paid records cannot be overwritten.']);
        }
    }
}
