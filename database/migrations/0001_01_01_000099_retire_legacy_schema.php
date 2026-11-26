<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings an installation that predates the baseline in line with it. On a new installation there is nothing
 * to do. On an existing one it removes what Erty no longer has:
 *  - tables of removed features: the public marketplace (viewings, applications, bookmarks, reviews, ratings,
 *    blogs), rent-to-own, plans and subscriptions, the per-route switch, and other leftovers;
 *  - the columns businesses.storage_addon and listings.subscription_status;
 *  - module switches, permission groups and notifications of removed features;
 *  - the rows of the migrations it replaces.
 * It also makes an agent's email unique per business instead of across all businesses.
 * Data in the remaining tables is kept as it is.
 */
return new class extends Migration
{
    private const RETIRED_TABLES = [
        'active_subscriptions',
        'admin_users',
        'amenities',
        'badges',
        'bathrooms',
        'bedrooms',
        'billing_history',
        'billings',
        'blog_analytics',
        'blogs',
        'bookmarks',
        'books',
        'client_listing',
        'clients',
        'company_contacts',
        'complex_expense_temps',
        'contacts',
        'coupon_code_usages',
        'coupon_codes',
        'description_type',
        'error_logs',
        'faiss_index_settings',
        'faq_answers',
        'faqs',
        'floor_plans',
        'income_types',
        'invoices',
        'limit_packages',
        'listing_changes',
        'listing_documents',
        'listing_expense_temps',
        'listing_expenses',
        'listing_reviews',
        'listing_views',
        'log_activities',
        'payment_transactions',
        'personal_access_tokens',
        'price_predictions',
        'promotes',
        'property_applications',
        'ratings',
        'ratings_response',
        'rent_to_owns',
        'rentals',
        'reviews',
        'routes',
        'schedules',
        'subscription_discounts',
        'subscription_tax_rates',
        'subscriptions',
        'system_activity_logs',
        'task_and_job_logs',
        'tenant_volume_anchors',
        'track_liked_blogs',
        'user_limit_purchases',
        'verification',
    ];

    private const RETIRED_PERMISSION_GROUPS = ['analytics_reports', 'appointment_management', 'ratings_management', 'rent_to_own', 'subscription_management', 'billing_management'];

    private const RETIRED_MODULES = ['subscriptions', 'rentals', 'renter', 'business'];

    private const RETIRED_NOTIFICATIONS = [
        'App\Notifications\ViewingRequestNotification',
        'App\Notifications\PropertyApplicationNotification',
        'App\Notifications\TransactionSucceededNotification',
        'App\Notifications\TransactionFailedNotification',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::RETIRED_TABLES as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP TABLE IF EXISTS "' . $table . '" CASCADE');
            } else {
                Schema::dropIfExists($table);
            }
        }
        Schema::enableForeignKeyConstraints();

        foreach (['businesses' => 'storage_addon', 'listings' => 'subscription_status'] as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }

        // An agent's email is unique within a business, not across every business on the installation.
        $indexes = array_column(Schema::getIndexes('agents'), 'name');
        if (in_array('agents_email_unique', $indexes, true)) {
            Schema::table('agents', fn (Blueprint $t) => $t->dropUnique('agents_email_unique'));
        }
        if (!in_array('agents_created_by_email_unique', $indexes, true)) {
            Schema::table('agents', fn (Blueprint $t) => $t->unique(['created_by', 'email'], 'agents_created_by_email_unique'));
        }

        DB::table('site_sections')->whereIn('key', self::RETIRED_MODULES)->delete();
        foreach (DB::table('businesses')->whereNotNull('modules')->get(['id', 'modules']) as $business) {
            $modules = json_decode((string) $business->modules, true);
            if (!is_array($modules)) {
                continue;
            }
            $kept = array_diff_key($modules, array_flip(self::RETIRED_MODULES));
            if ($kept !== $modules) {
                DB::table('businesses')->where('id', $business->id)->update(['modules' => $kept ? json_encode($kept) : null]);
            }
        }

        foreach ([['business_roles', 'permissions'], ['users', 'business_permissions']] as [$table, $column]) {
            if (!Schema::hasColumn($table, $column)) {
                continue;
            }
            foreach (DB::table($table)->whereNotNull($column)->get(['id', $column]) as $row) {
                $groups = json_decode((string) $row->{$column}, true);
                if (!is_array($groups) || array_is_list($groups)) {
                    continue;
                }
                $kept = array_diff_key($groups, array_flip(self::RETIRED_PERMISSION_GROUPS));
                if ($kept !== $groups) {
                    DB::table($table)->where('id', $row->id)->update([$column => json_encode($kept)]);
                }
            }
        }

        DB::table('notifications')->whereIn('type', self::RETIRED_NOTIFICATIONS)->delete();

        // Role assignments of user accounts that no longer exist, then the roles of the removed public
        // marketplace (renters, moving companies) once nobody holds them.
        DB::table('model_has_roles')->where('model_type', 'App\Models\User')
            ->whereNotIn('model_id', DB::table('users')->select('id'))->delete();
        foreach (DB::table('roles')->whereIn('name', ['renter', 'mover'])->pluck('id') as $role) {
            if (!DB::table('model_has_roles')->where('role_id', $role)->exists()) {
                DB::table('role_has_permissions')->where('role_id', $role)->delete();
                DB::table('roles')->where('id', $role)->delete();
            }
        }

        // The migrations these baseline files replace.
        DB::table('migrations')->where('migration', 'not like', '0001_01_01_%')->delete();
    }

    public function down(): void
    {
        // Removed features and their data are not restored.
    }
};
