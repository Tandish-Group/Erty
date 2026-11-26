<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Property management type and assignment history, for SQLite test schemas that create their own tables. */
trait CreatesAssignmentSchema
{
    protected function createAssignmentSchema(): void
    {
        if (Schema::hasTable('complexes') && !Schema::hasColumn('complexes', 'management_type')) {
            Schema::table('complexes', fn (Blueprint $t) => $t->string('management_type', 20)->default('company'));
        }
        if (!Schema::hasTable('assignment_history')) {
            Schema::create('assignment_history', function (Blueprint $t) {
                $t->id(); $t->unsignedBigInteger('complex_id'); $t->string('scope', 20); $t->unsignedBigInteger('unit_id')->nullable();
                $t->string('assignee_type', 10); $t->unsignedBigInteger('agent_id')->nullable(); $t->string('role', 12);
                $t->timestamp('starts_at'); $t->timestamp('ends_at')->nullable(); $t->unsignedBigInteger('assigned_by')->nullable();
                $t->unsignedBigInteger('ended_by')->nullable(); $t->string('reason')->nullable(); $t->timestamps();
            });
        }
    }

    /** House columns and the sale tables (sales, plan, payments, allocations), plus what a sale touches. */
    protected function createSalesSchema(): void
    {
        $this->createCommissionSchema();
        $add = function (string $table, array $columns) {
            foreach ($columns as $name => $define) {
                if (!Schema::hasColumn($table, $name)) Schema::table($table, fn (Blueprint $t) => $define($t));
            }
        };
        $add('complex_residential_units', [
            'complex_unit_id' => fn ($t) => $t->integer('complex_unit_id')->nullable(), 'unit_category' => fn ($t) => $t->string('unit_category')->default('apartment'),
            'floor' => fn ($t) => $t->integer('floor')->nullable(), 'sq_ft' => fn ($t) => $t->double('sq_ft')->nullable(),
            'house_type' => fn ($t) => $t->string('house_type')->nullable(), 'plot_number' => fn ($t) => $t->string('plot_number')->nullable(),
            'plot_size' => fn ($t) => $t->double('plot_size')->nullable(), 'plot_unit' => fn ($t) => $t->string('plot_unit')->nullable(),
            'title_number' => fn ($t) => $t->string('title_number')->nullable(), 'offer' => fn ($t) => $t->string('offer')->default('rent'),
            'sale_price' => fn ($t) => $t->double('sale_price')->nullable(),
        ]);
        $add('commission_entries', ['sale_id' => fn ($t) => $t->unsignedBigInteger('sale_id')->nullable(), 'sale_payment_id' => fn ($t) => $t->unsignedBigInteger('sale_payment_id')->nullable()]);
        if (Schema::hasTable('leads')) $add('leads', ['converted_sale_id' => fn ($t) => $t->unsignedBigInteger('converted_sale_id')->nullable()]);
        if (!Schema::hasTable('listings')) Schema::create('listings', function (Blueprint $t) { $t->id(); $t->integer('complex_unit_id')->nullable(); $t->integer('commercial_unit_id')->nullable(); $t->timestamps(); });
        if (!Schema::hasTable('complex_changes')) Schema::create('complex_changes', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->string('field_name'); $t->text('old_value')->nullable(); $t->text('new_value')->nullable(); $t->integer('user_id')->nullable(); $t->string('user_ip')->nullable(); $t->timestamps(); });
        if (!Schema::hasTable('history_categories')) Schema::create('history_categories', function (Blueprint $t) { $t->id(); $t->string('tag'); $t->string('name')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); });
        if (!Schema::hasTable('complex_histories')) Schema::create('complex_histories', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->integer('complex_residential_unit_id')->nullable(); $t->integer('commercial_unit_id')->nullable(); $t->integer('history_category_id')->nullable(); $t->string('category_tag')->nullable(); $t->json('data')->nullable(); $t->json('files')->nullable(); $t->text('notes')->nullable(); $t->integer('created_by')->nullable(); $t->timestamps(); });
        Schema::create('property_sales', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('business_id'); $t->integer('complex_id'); $t->integer('residential_unit_id'); $t->integer('listing_id')->nullable(); $t->integer('lead_id')->nullable();
            $t->integer('agent_id')->nullable(); $t->string('reference')->unique(); $t->string('buyer_name'); $t->string('buyer_phone')->nullable(); $t->string('buyer_email')->nullable();
            $t->string('buyer_id_number')->nullable(); $t->string('buyer_kra_pin')->nullable(); $t->decimal('price', 15, 2); $t->string('currency')->default('KES'); $t->date('agreement_date');
            $t->decimal('deposit_amount', 15, 2)->default(0); $t->string('status')->default('reserved'); $t->timestamp('completed_at')->nullable(); $t->string('completion_note')->nullable();
            $t->date('title_transferred_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->string('cancel_reason')->nullable(); $t->text('notes')->nullable(); $t->integer('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('property_sale_instalments', function (Blueprint $t) {
            $t->id(); $t->integer('sale_id'); $t->integer('sequence'); $t->string('number')->unique(); $t->string('label'); $t->date('due_date'); $t->decimal('amount', 15, 2);
            $t->decimal('paid_amount', 15, 2)->default(0); $t->string('status')->default('due'); $t->timestamp('overdue_notified_at')->nullable(); $t->timestamps();
        });
        Schema::create('property_sale_payments', function (Blueprint $t) {
            $t->id(); $t->integer('sale_id'); $t->string('receipt_number')->unique(); $t->date('paid_on'); $t->decimal('amount', 15, 2); $t->string('method'); $t->string('reference')->nullable();
            $t->string('status')->default('confirmed'); $t->integer('recorded_by')->nullable(); $t->integer('reversed_by')->nullable(); $t->timestamp('reversed_at')->nullable(); $t->string('reversal_reason')->nullable(); $t->timestamps();
        });
        Schema::create('property_sale_payment_allocations', function (Blueprint $t) { $t->id(); $t->integer('payment_id'); $t->integer('instalment_id'); $t->decimal('amount', 15, 2); $t->timestamps(); });
    }

    /** Commission agreements and ledger (and the assignment tables they depend on). */
    protected function createCommissionSchema(): void
    {
        $this->createAssignmentSchema();
        if (Schema::hasTable('agent_commission_payouts') && !Schema::hasColumn('agent_commission_payouts', 'kind')) {
            Schema::table('agent_commission_payouts', fn (Blueprint $t) => $t->string('kind', 10)->default('agent'));
        }
        if (!Schema::hasTable('commission_agreements')) {
            Schema::create('commission_agreements', function (Blueprint $t) {
                $t->id(); $t->unsignedBigInteger('business_id'); $t->string('name')->nullable(); $t->string('scope', 20);
                $t->unsignedBigInteger('complex_id')->nullable(); $t->unsignedBigInteger('agent_id')->nullable(); $t->string('unit_type', 12)->nullable(); $t->unsignedBigInteger('unit_id')->nullable();
                $t->string('entitlement', 24); $t->string('method', 20)->default('percent_collected'); $t->decimal('rate', 7, 3)->default(0); $t->decimal('flat_amount', 14, 2)->nullable();
                $t->json('tiers')->nullable(); $t->string('sharing', 16)->default('separate'); $t->decimal('sub_share_pct', 6, 2)->nullable(); $t->string('funding', 16)->default('company_fee');
                $t->json('applies_to')->nullable(); $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->unsignedBigInteger('replaces_id')->nullable();
                $t->string('change_reason')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->unsignedBigInteger('ended_by')->nullable(); $t->timestamps();
            });
        }
        if (!Schema::hasTable('commission_entries')) {
            Schema::create('commission_entries', function (Blueprint $t) {
                $t->id(); $t->unsignedBigInteger('business_id'); $t->string('kind', 12); $t->string('beneficiary_type', 10); $t->unsignedBigInteger('agent_id')->nullable();
                $t->string('entitlement', 24); $t->string('funding', 16)->nullable(); $t->unsignedBigInteger('complex_id')->nullable(); $t->string('unit_type', 12)->nullable();
                $t->unsignedBigInteger('unit_id')->nullable(); $t->unsignedBigInteger('tenancy_id')->nullable(); $t->unsignedBigInteger('tenant_profile_id')->nullable();
                $t->unsignedBigInteger('allocation_id')->nullable(); $t->unsignedBigInteger('payment_id')->nullable(); $t->unsignedBigInteger('invoice_id')->nullable();
                $t->string('rental_period', 40)->nullable(); $t->date('collected_on')->nullable(); $t->unsignedBigInteger('primary_agent_id')->nullable(); $t->string('management_type', 20)->nullable();
                $t->unsignedBigInteger('agreement_id')->nullable(); $t->string('method', 20)->nullable(); $t->decimal('rate', 7, 3)->nullable(); $t->decimal('basis_amount', 14, 2)->default(0);
                $t->decimal('amount', 14, 2); $t->string('currency', 8)->default('KES'); $t->json('breakdown')->nullable(); $t->date('period_from')->nullable(); $t->date('period_to')->nullable();
                $t->string('status', 12); $t->unsignedBigInteger('reverses_entry_id')->nullable(); $t->unsignedBigInteger('payout_id')->nullable();
                $t->unsignedBigInteger('approved_by')->nullable(); $t->timestamp('approved_at')->nullable(); $t->timestamps();
                $t->unique(['allocation_id', 'kind', 'entitlement', 'beneficiary_type', 'agent_id']);
            });
        }
    }
}
