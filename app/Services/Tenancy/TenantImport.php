<?php

namespace App\Services\Tenancy;

use App\Models\CommercialUnit;
use App\Models\Complex;
use App\Models\ComplexResidentialUnit;
use App\Models\TenantInvoice;
use App\Models\TenantManagement;
use App\Models\TenantPayment;
use App\Models\TenantProfile;
use App\Services\Properties\ComplexInventoryService;
use App\Services\Properties\ComplexSetupService;
use App\Services\Rent\TenantAccountService;
use App\Services\Rent\TenantChargesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Importing tenants with their tenancies (Tenants → Import data → tenants): one row per tenancy that already
 * exists outside Erty. Each staged row is checked the same way on the review page and when it is imported, and
 * importing goes through the normal onboarding (TenancyOnboardingService) so the unit, lease and billing follow
 * the usual rules. Moving in from another system:
 *  - past rent periods are not billed: the tenancy is marked paid up to the end of the current period and what the
 *    tenant owes today ("balance_owed") becomes one opening invoice (or credit when negative);
 *  - the deposit already held is recorded as brought forward (not as money received today, and with no commission);
 *  - nobody is e-mailed: the tenant already lives there.
 */
class TenantImport
{
    public const FREQUENCIES = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annually' => 'Annually', 'annual' => 'Annually', 'yearly' => 'Annually'];

    public function __construct(private TenantAccountService $accounts, private TenantChargesService $charges)
    {
    }

    /** Staged tenant rows with their tenancy, each checked, for the review page. */
    public function staged(int $ownerId)
    {
        $tenancies = DB::table('temp_tenant_management')->where('user_id', $ownerId)->get()->keyBy('temp_tenant_profile_id');
        $seenUnits = [];

        return DB::table('temp_tenant_profiles')->where('user_id', $ownerId)->orderBy('row_number')->get()
            ->map(function ($profile) use ($tenancies, &$seenUnits) {
                $check = $this->check($profile, $tenancies[$profile->id] ?? null, $seenUnits);
                if ($check['unit']) {
                    $seenUnits[] = $check['unit_key'];
                }

                return (object) (['row' => $profile, 'tenancy_row' => $tenancies[$profile->id] ?? null] + $check);
            });
    }

    /** Everything the review page shows for one row, and the problems that stop it being imported. */
    public function check(object $p, ?object $t, array $seenUnits = []): array
    {
        $problems = (($p->status ?? null) === 'failed' || ($p->processing_status ?? null) === 'failed') && $p->error_message ? [rtrim($p->error_message, '.')] : [];
        if ($t && $t->processing_status === 'failed' && $t->error_message) {
            $problems[] = rtrim($t->error_message, '.');
        }
        $notes = [];
        $type = $this->tenantType($p);

        // The tenant: an existing profile with the same e-mail or ID number is used; otherwise a new one is created.
        $existing = $this->existingProfile($p, $type);
        if ($existing) {
            if ($existing->profile_status === 'Blacklisted') {
                $problems[] = $existing->display_name . ' (' . $existing->tenant_ref . ') is blacklisted';
            } elseif ($existing->tenant_type !== $type) {
                $problems[] = 'an existing ' . strtolower($existing->tenant_type) . ' tenant (' . $existing->tenant_ref . ') has this e-mail or ID number';
            } else {
                $notes[] = 'uses the existing tenant ' . $existing->tenant_ref;
            }
        } else {
            $missing = array_keys(array_filter($type === 'Business'
                ? ['business name' => $p->business_name, 'registration number' => $p->business_registration, 'contact person' => $p->business_contact_person, 'business e-mail' => $p->business_email, 'business phone' => $p->business_phone]
                : ['name' => $p->tenant_name, 'e-mail' => $p->tenant_email, 'phone' => $p->tenant_phone, 'ID number' => $p->tenant_id_number], fn ($v) => trim((string) $v) === ''));
            if ($missing) {
                $problems[] = 'no ' . implode(', ', $missing);
            }
            $email = $type === 'Business' ? $p->business_email : $p->tenant_email;
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $problems[] = $email . ' is not an e-mail address';
            }
        }

        // The tenancy.
        $complex = $unit = $listingType = null;
        $commercial = false;
        if (!$t) {
            $problems[] = 'no tenancy details (property, unit, rent and start date)';
        } else {
            $complex = $this->property($t->property ?: $t->complex_slug);
            if (!$complex) {
                $problems[] = ($t->property ?: $t->complex_slug) ? 'no property called "' . ($t->property ?: $t->complex_slug) . '" at the properties you manage' : 'no property';
            } elseif (($complex->setup_status ?? 'draft') !== 'ready' || !app(ComplexSetupService::class)->ready($complex)) {
                $problems[] = $complex->title . ' is not set up yet: confirm its set-up first';
                $complex = null;
            }
            if ($complex) {
                [$unit, $commercial, $unitProblem] = $this->unit($complex, $t);
                if ($unitProblem) {
                    $problems[] = $unitProblem;
                } elseif ($unit && in_array(($commercial ? 'c' : 'r') . $unit->id, $seenUnits, true) && !$commercial) {
                    $problems[] = 'unit ' . $unit->unit_number . ' appears twice in this file';
                }
                $listingType = $commercial ? 'Commercial Unit' : collect(explode(',', (string) $complex->type))->map(fn ($x) => trim($x))->first(fn ($x) => $x !== '' && $x !== 'Commercial Unit');
                if (!$listingType) {
                    $problems[] = $complex->title . ' has no residential property type';
                }
            }
            if ((float) $t->monthly_payment <= 0) {
                $problems[] = 'no rent';
            }
            if (!$t->start_date) {
                $problems[] = 'no start date';
            } elseif ($t->end_date && Carbon::parse($t->end_date)->lte(Carbon::parse($t->start_date))) {
                $problems[] = 'the end date is not after the start date';
            }
            if ($t->payment_frequency && !isset(self::FREQUENCIES[strtolower(trim($t->payment_frequency))])) {
                $problems[] = '"' . $t->payment_frequency . '" is not a payment frequency (Monthly, Quarterly or Annually)';
            }
            if ((float) $t->deposit_held > (float) $t->security_deposit && $t->deposit_held !== null) {
                $problems[] = 'the deposit held is more than the deposit';
            }
            if ($t->currency && !preg_match('/^[A-Za-z]{3}$/', $t->currency)) {
                $problems[] = '"' . $t->currency . '" is not a currency code';
            }
        }

        $start = $t?->start_date ? Carbon::parse($t->start_date) : null;
        $billing = $start && !$problems ? $this->billingFrom($t, $start) : null;

        return [
            'type' => $type,
            'name' => $existing?->display_name ?? ($type === 'Business' ? $p->business_name : $p->tenant_name),
            'existing' => $existing,
            'complex' => $complex,
            'unit' => $unit,
            'unit_key' => $unit ? ($commercial ? 'c' : 'r') . $unit->id : null,
            'commercial' => $commercial,
            'listing_type' => $listingType,
            'frequency' => self::FREQUENCIES[strtolower(trim((string) ($t?->payment_frequency ?: 'monthly')))] ?? 'Monthly',
            'currency' => strtoupper($t?->currency ?: default_currency()),
            'deposit_held' => $t ? ($t->deposit_held === null ? (float) $t->security_deposit : (float) $t->deposit_held) : 0,
            'billing' => $billing,
            'problems' => $problems,
            'notes' => $notes,
        ];
    }

    /** Import one staged row. Throws with the reason when it cannot be imported. */
    public function import(object $p, ?object $t): TenantManagement
    {
        $c = $this->check($p, $t);
        if ($c['problems']) {
            throw new \RuntimeException(ucfirst(implode('; ', $c['problems'])) . '.');
        }

        return DB::transaction(function () use ($p, $t, $c) {
            $start = Carbon::parse($t->start_date)->startOfDay();
            $rent = (float) $t->monthly_payment;
            $data = [
                'imported' => 1,
                'tenant_option' => $c['existing'] ? 'existing' : 'new',
                'existing_tenant_id' => $c['existing']?->id,
                'tenant_type' => $c['type'],
                'tenant_name' => $p->tenant_name, 'tenant_email' => $p->tenant_email, 'tenant_phone' => $p->tenant_phone, 'tenant_id_number' => $p->tenant_id_number,
                'date_of_birth' => $p->date_of_birth, 'gender' => $p->gender, 'nationality' => $p->nationality, 'occupation' => $p->occupation,
                'employer' => $p->employer, 'monthly_income' => $p->monthly_income,
                'business_name' => $p->business_name, 'business_registration' => $p->business_registration, 'business_contact_person' => $p->business_contact_person,
                'business_email' => $p->business_email, 'business_phone' => $p->business_phone, 'business_type' => $p->business_type,
                'tax_id_number' => $p->tax_id_number, 'annual_revenue' => $p->annual_revenue, 'years_in_business' => $p->years_in_business,
                'emergency_contact_name' => $p->emergency_contact_name, 'emergency_contact_phone' => $p->emergency_contact_phone,
                'emergency_contact_relationship' => $p->emergency_contact_relationship,
                'complex' => $c['complex']->slug,
                'listing_type' => $c['listing_type'],
                'residential_unit_id' => $c['commercial'] ? null : $c['unit']?->id,
                'commercial_unit_id' => $c['commercial'] ? $c['unit']->id : null,
                'occupied_sq_area' => $c['commercial'] ? $t->occupied_sq_area : null,
                'rate_per_sq_ft' => $c['commercial'] ? ($t->rate_per_sq_ft ?: ($t->occupied_sq_area ? round($rent / (float) $t->occupied_sq_area, 2) : null)) : null,
                'service_charge_per_sq_ft' => $c['commercial'] ? $t->service_charge_per_sq_ft : null,
                'specific_unit_number' => $t->specific_unit_number,
                'furnishing' => $c['commercial'] ? null : $t->furnishing,
                'number_of_occupants' => $t->number_of_occupants ?: 1,
                'agreement_type' => in_array(strtolower((string) $t->agreement_type), ['rental', 'lease'], true) ? strtolower($t->agreement_type) : ($t->end_date ? 'lease' : 'rental'),
                'currency' => $c['currency'],
                'monthly_payment' => $rent,
                'security_deposit' => (float) $t->security_deposit,
                'start_date' => $start->toDateString(),
                'end_date' => $t->end_date,
                'rent_paid_up_to' => $c['billing']['paid_up_to'],
                'payment_interval' => $c['frequency'],
                'late_fee_amount' => $t->late_fee_amount,
                'grace_period' => $t->grace_period_days,
                'notes' => $t->general_notes,
            ];
            $result = app(TenancyOnboardingService::class)->onboard(Request::create('/tenant_management/import', 'POST', $data));
            $tenancy = TenantManagement::findOrFail($result['tenant_management']->id);

            // The deposit already held when the tenancy came to Erty pays the deposit invoice, as brought forward.
            $held = round(min($c['deposit_held'], (float) $t->security_deposit), 2);
            $depositInvoice = TenantInvoice::query()->where('tenancy_id', $tenancy->id)->where('kind', 'deposit')->first();
            if ($held > 0 && $depositInvoice) {
                $entry = TenantPayment::create([
                    'tenant_id' => $tenancy->id, 'entry_type' => 'opening', 'recorded_by' => auth()->id(),
                    'payment_date' => today()->toDateString(), 'payment_period' => today()->format('Y-m'),
                    'amount_paid' => $held, 'total_amount' => $held, 'unallocated_amount' => $held,
                    'payment_method' => 'other', 'payment_status' => TenantPayment::CONFIRMED,
                    'confirmed_at' => now(), 'confirmed_by' => auth()->id(),
                    'payment_notes' => 'Deposit held before the tenancy was imported into Erty', 'late_fee_paid_now' => false,
                ]);
                $this->accounts->allocate($entry, $tenancy, $depositInvoice->id, today());
            }

            // What the tenant owed (or was owed) on the day of the move to Erty.
            $owed = round((float) $t->balance_owed, 2);
            if ($owed > 0) {
                $this->charges->charge($tenancy, 'adjustment', $owed, 'Balance brought forward from before Erty', today());
            } elseif ($owed < 0) {
                $this->accounts->addCredit($tenancy, abs($owed), today()->toDateString(), 'Credit brought forward from before Erty');
            }

            return $tenancy->fresh();
        });
    }

    /**
     * When rent billing starts. A tenancy that began in the past is paid up to the end of the period running
     * today (what is owed today is the opening balance); one starting later is billed from its start as usual.
     *
     * @return array{paid_up_to: ?string, first_bill: string}
     */
    private function billingFrom(object $t, Carbon $start): array
    {
        if ($start->isAfter(today())) {
            return ['paid_up_to' => null, 'first_bill' => $start->toDateString()];
        }
        $probe = new TenantManagement(['lease_start_date' => $start->toDateString(), 'move_in_date' => $start->toDateString(),
            'payment_frequency' => self::FREQUENCIES[strtolower(trim((string) ($t->payment_frequency ?: 'monthly')))] ?? 'Monthly']);
        $n = 0;
        $period = $this->charges->period($probe, 0);
        while ($period['end']->lt(today()) && $n < 1200) {
            $period = $this->charges->period($probe, ++$n);
        }

        return ['paid_up_to' => $period['end']->toDateString(), 'first_bill' => $period['end']->copy()->addDay()->toDateString()];
    }

    private function tenantType(object $p): string
    {
        $type = strtolower(trim((string) $p->tenant_type));
        if (in_array($type, ['business', 'company', 'corporate'], true)) {
            return 'Business';
        }
        if ($type === '' && trim((string) $p->business_name) !== '' && trim((string) $p->tenant_name) === '') {
            return 'Business';
        }

        return 'Individual';
    }

    private function existingProfile(object $p, string $type): ?TenantProfile
    {
        [$email, $id] = $type === 'Business' ? [$p->business_email, $p->business_registration] : [$p->tenant_email, $p->tenant_id_number];
        [$emailColumn, $idColumn] = $type === 'Business' ? ['business_email', 'business_registration'] : ['tenant_email', 'tenant_id_number'];
        if (!trim((string) $email) && !trim((string) $id)) {
            return null;
        }

        return TenantProfile::query()->where('managed_by', business_owner_id())
            ->where(function ($q) use ($email, $id, $emailColumn, $idColumn) {
                if (trim((string) $email)) {
                    $q->orWhereRaw("LOWER({$emailColumn}) = ?", [mb_strtolower(trim($email))]);
                }
                if (trim((string) $id)) {
                    $q->orWhere($idColumn, trim($id));
                }
            })->first();
    }

    private function property(?string $name): ?Complex
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return Complex::forBusiness()->where(fn ($q) => $q->whereRaw('LOWER(title) = ?', [mb_strtolower($name)])->orWhere('slug', $name))->first();
    }

    /** @return array{0: ComplexResidentialUnit|CommercialUnit|null, 1: bool, 2: ?string} unit, commercial?, problem */
    private function unit(Complex $complex, object $t): array
    {
        $number = trim((string) $t->unit_number);
        if ($number === '') {
            return [null, false, 'no unit number'];
        }
        $wantsCommercial = (float) $t->occupied_sq_area > 0;
        $residential = $wantsCommercial ? null : ComplexResidentialUnit::where('complex_id', $complex->id)->whereRaw('LOWER(unit_number) = ?', [mb_strtolower($number)])->first();
        $commercialUnit = $residential ? null : CommercialUnit::where('complex_id', $complex->id)->whereRaw('LOWER(unit_number) = ?', [mb_strtolower($number)])->first();

        if ($residential) {
            if ($residential->activeTenancies()->exists()) {
                return [$residential, false, 'unit ' . $residential->unit_number . ' already has a tenant in Erty'];
            }
            if ($residential->status !== 'available') {
                return [$residential, false, 'unit ' . $residential->unit_number . ' is marked ' . str_replace('_', ' ', (string) $residential->status) . ', not available'];
            }
            if (!$residential->forRent()) {
                return [$residential, false, 'house ' . $residential->unit_number . ' is for sale only'];
            }

            return [$residential, false, null];
        }
        if ($commercialUnit) {
            if ((float) $t->occupied_sq_area <= 0) {
                return [$commercialUnit, true, 'unit ' . $commercialUnit->unit_number . ' is commercial: give the area the tenant occupies (occupied_sq_area)'];
            }
            try {
                app(ComplexInventoryService::class)->assertCommercialCapacity($commercialUnit, (float) $t->occupied_sq_area);
            } catch (ValidationException $e) {
                return [$commercialUnit, true, 'unit ' . $commercialUnit->unit_number . ': ' . lcfirst(rtrim(collect($e->errors())->flatten()->first(), '.'))];
            }

            return [$commercialUnit, true, null];
        }

        return [null, $wantsCommercial, 'no unit ' . $number . ' at ' . $complex->title];
    }
}
