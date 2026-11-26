<?php

namespace App\Mail;

use App\Models\TenantManagement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** Tells a tenant about the invoices just issued (deposit, rent, service charge, fees) and what is due now. */
class TenantChargesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TenantManagement $tenancy, public Collection $invoices, public array $summary)
    {
    }

    public function build()
    {
        $this->tenancy->loadMissing(['tenantProfile', 'manager.business']);
        $business = $this->tenancy->manager?->business?->business_name ?: config('app.name');
        $count = $this->invoices->count();
        return $this->subject($count === 1 ? "Invoice {$this->invoices->first()->invoice_number} from {$business}" : "{$count} new invoices from {$business}")
            ->view('emails.tenants.charges', ['business' => $business]);
    }
}
