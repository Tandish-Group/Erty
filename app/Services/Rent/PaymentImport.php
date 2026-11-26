<?php

namespace App\Services\Rent;

use App\Models\TenantInvoice;
use App\Models\TenantManagement;
use App\Models\TenantPayment;
use App\Models\TenantProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Importing payments received (Tenants → bulk upload → payments). Each staged row is checked the same way on the
 * review page and when it is imported: which tenancy it is for, that the date and amount make sense, that the
 * optional invoice belongs to that tenant, and that the transaction reference has not been recorded already.
 * Importing records it like a payment entered by hand (TenantAccountService::record): oldest unpaid invoice
 * first (or the named invoice first), anything extra kept as credit.
 */
class PaymentImport
{
    public function __construct(private TenantAccountService $accounts)
    {
    }

    /**
     * @return array{tenancy: ?TenantManagement, invoice: ?TenantInvoice, problems: string[], notes: string[], pending: bool}
     */
    public function check(object $row, array $seenReferences = []): array
    {
        $problems = $row->processing_status === 'failed' && $row->error_message ? [rtrim($row->error_message, '.')] : [];
        $notes = [];
        $tenancy = $this->tenancyFor($row, $problems);

        if (!$tenancy && !$problems) {
            $problems[] = 'no tenancy reference, tenant reference or e-mail';
        }
        $date = $row->payment_date ? Carbon::parse($row->payment_date) : null;
        if (!$date && !$problems) {
            $problems[] = 'no payment date';
        } elseif ($date && $date->isAfter(today())) {
            $problems[] = 'the payment date ' . $date->format('j M Y') . ' is in the future';
        }
        if ((float) $row->amount_paid <= 0 && !$problems) {
            $problems[] = 'the amount is missing or zero';
        }

        $invoice = null;
        if ($tenancy && $row->invoice_id) {
            $invoice = TenantInvoice::query()->whereIn('tenancy_id', $this->accounts->chainIds($tenancy))
                ->where(fn ($q) => $q->where('invoice_number', $row->invoice_id)->orWhere('combined_invoice_number', $row->invoice_id))->first();
            if (!$invoice) {
                $problems[] = 'invoice ' . $row->invoice_id . ' is not one of this tenant\'s invoices';
            } elseif ((float) $invoice->balance <= 0) {
                $notes[] = 'invoice ' . $row->invoice_id . ' is already paid, so the oldest unpaid invoice is paid instead';
                $invoice = null;
            }
        }

        if ($tenancy && $row->transaction_reference) {
            $recorded = TenantPayment::query()->whereIn('tenant_id', $this->accounts->chainIds($tenancy))
                ->where('transaction_reference', $row->transaction_reference)->where('payment_status', '!=', TenantPayment::REVERSED)->exists();
            if ($recorded) {
                $problems[] = 'a payment with reference ' . $row->transaction_reference . ' is already recorded for this tenant';
            } elseif (in_array($tenancy->id . '|' . $row->transaction_reference, $seenReferences, true)) {
                $problems[] = 'the reference ' . $row->transaction_reference . ' appears twice in this file';
            }
        } elseif ($tenancy && !$row->transaction_reference) {
            $notes[] = 'no transaction reference, so a repeated upload would record it twice';
        }

        return ['tenancy' => $tenancy, 'invoice' => $invoice, 'problems' => $problems, 'notes' => $notes, 'pending' => $row->payment_status === 'pending'];
    }

    /** Record one staged row on the tenant's account. Throws with the reason when the row cannot be imported. */
    public function import(object $row, array $seenReferences = []): TenantPayment
    {
        $check = $this->check($row, $seenReferences);
        if ($check['problems']) {
            throw new \RuntimeException(ucfirst(implode('; ', $check['problems'])) . '.');
        }

        return $this->accounts->record($check['tenancy'], [
            'amount' => (float) $row->amount_paid,
            'payment_date' => Carbon::parse($row->payment_date)->toDateString(),
            'payment_method' => $row->payment_method ?: 'other',
            'transaction_reference' => $row->transaction_reference,
            'payment_notes' => $row->payment_notes ?: 'Imported from a file',
            'pending' => $check['pending'],
            'first_invoice_id' => $check['invoice']?->id,
        ]);
    }

    /**
     * The tenancy a row is for: by tenancy reference, otherwise the tenant's current tenancy (by tenant reference or
     * e-mail). Payments on an ended tenancy go to the account it was carried into (a transfer). Property limits
     * apply through the models.
     */
    public function tenancyFor(object $row, array &$problems): ?TenantManagement
    {
        if ($row->tenancy_ref) {
            $tenancy = TenantManagement::forBusiness()->where('tenancy_ref', trim($row->tenancy_ref))->first();
            if (!$tenancy) {
                $problems[] = 'no tenancy ' . $row->tenancy_ref . ' at the properties you manage';

                return null;
            }

            return $this->accounts->currentTenancy($tenancy);
        }

        $profile = null;
        if ($row->tenant_profile_ref) {
            $profile = TenantProfile::query()->where('managed_by', business_owner_id())->where('tenant_ref', trim($row->tenant_profile_ref))->first();
        } elseif ($row->tenant_email) {
            $email = mb_strtolower(trim($row->tenant_email));
            $profile = TenantProfile::query()->where('managed_by', business_owner_id())
                ->where(fn ($q) => $q->whereRaw('LOWER(tenant_email) = ?', [$email])->orWhereRaw('LOWER(business_email) = ?', [$email]))->first();
        }
        if (!$profile) {
            if ($row->tenant_profile_ref || $row->tenant_email) {
                $problems[] = 'no tenant ' . ($row->tenant_profile_ref ?: $row->tenant_email) . ' at the properties you manage';
            }

            return null;
        }

        $current = TenantManagement::forBusiness()->where('tenant_profile_id', $profile->id)->whereIn('status', ['Active', 'On Notice'])->get();
        if ($current->count() > 1) {
            $problems[] = $profile->display_name . ' has ' . $current->count() . ' current tenancies: use the tenancy reference';

            return null;
        }
        $tenancy = $current->first() ?? TenantManagement::forBusiness()->where('tenant_profile_id', $profile->id)->latest('id')->first();
        if (!$tenancy) {
            $problems[] = $profile->display_name . ' has no tenancy at the properties you manage';
        }

        return $tenancy;
    }

    /** Staged payment rows for the review page, each with its check. */
    public function staged(int $ownerId)
    {
        $seen = [];

        return DB::table('temp_tenant_payments')->where('user_id', $ownerId)->orderBy('row_number')->get()
            ->map(function ($row) use (&$seen) {
                $check = $this->check($row, $seen);
                if ($check['tenancy'] && $row->transaction_reference) {
                    $seen[] = $check['tenancy']->id . '|' . $row->transaction_reference;
                }

                return (object) (['row' => $row] + $check);
            });
    }
}
