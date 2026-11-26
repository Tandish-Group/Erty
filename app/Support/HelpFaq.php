<?php

namespace App\Support;

use App\Models\User;
use App\Services\Access\ModuleService;

/**
 * The questions on the Help & FAQ page (/business/faq), grouped into sections.
 *
 * Each section lists the rights that open its pages (any one is enough, as in config/business_access.php;
 * null: everyone), and optionally the module that must be switched on. A question can be limited to the business owner. Answers are HTML written
 * here; they use the same names as the buttons and pages in the app, so update them when those change.
 * Question ids are used in links (/business/faq#id): keep them stable.
 */
class HelpFaq
{
    public function __construct(private ModuleService $modules)
    {
    }

    /**
     * The sections and questions this user can use.
     *
     * @return list<array{key: string, title: string, icon: string, items: list<array{id: string, question: string, answer: string}>}>
     */
    public function sectionsFor(User $user): array
    {
        $owner = $user->isBusinessOwner();
        $sections = [];

        foreach ($this->catalog() as $section) {
            if (!$owner && $section['needs'] && !$this->allowedAny($user, $section['needs'])) {
                continue;
            }
            if ($section['module'] && !$this->modules->enabled($section['module'], $user)) {
                continue;
            }

            $items = [];
            foreach ($section['items'] as $item) {
                if (($item['owner'] ?? false) && !$owner) {
                    continue;
                }
                if (isset($item['module']) && !$this->modules->enabled($item['module'], $user)) {
                    continue;
                }
                $items[] = ['id' => $item['id'], 'question' => $item['q'], 'answer' => $item['a']];
            }

            if ($items) {
                $sections[] = ['key' => $section['key'], 'title' => $section['title'], 'icon' => $section['icon'], 'items' => $items];
            }
        }

        return $sections;
    }

    /** Whether the user holds any of these rights ("group.action"), as the pages of that area require. */
    private function allowedAny(User $user, array $rights): bool
    {
        foreach ($rights as $right) {
            [$group, $action] = explode('.', $right, 2);
            if ($user->hasBusinessPermission($group, $action)) {
                return true;
            }
        }

        return false;
    }

    /** Every section and question, before filtering. */
    public function catalog(): array
    {
        $app = e(config('app.name'));

        return [
            [
                'key' => 'getting-started', 'title' => 'Getting started', 'icon' => 'fa-flag', 'needs' => null, 'module' => null,
                'items' => [
                    ['id' => 'start-first-steps', 'q' => "How do I get started with {$app}?",
                        'a' => '<ol><li>Open <strong>Property portfolio</strong> and choose <strong>Add a property</strong>: the building or estate, then its unit types (for example 2-bedroom flats), houses or commercial spaces.</li>'
                            . '<li>Check the summary and choose <strong>Confirm setup</strong>.</li>'
                            . '<li>Open <strong>Tenants</strong> and choose <strong>Onboard Tenant</strong> to move your first tenant into a vacant unit.</li></ol>'
                            . '<p>From then on rent is billed automatically, and you record payments as they come in.</p>'],
                    ['id' => 'start-menu', 'q' => 'What does each part of the menu cover?',
                        'a' => '<ul><li><strong>Home</strong>: the dashboard, with occupancy, rent collected and what needs attention.</li>'
                            . '<li><strong>CRM</strong>: leads, from first contact until they become tenants.</li>'
                            . '<li><strong>Agents</strong>: your agents, their tasks and their commission.</li>'
                            . '<li><strong>Tenants</strong>: tenancies, rent, payments, notices and transfers.</li>'
                            . '<li><strong>Sales</strong>: houses sold on a deposit and instalments (when the Sales module is on).</li>'
                            . '<li><strong>Maintenance</strong>: repair requests and who is handling them.</li>'
                            . '<li><strong>Legal</strong>: lease agreements and other documents, in folders.</li></ul>'
                            . '<p>You only see the parts your role gives you access to. Your profile, Business settings and Team &amp; roles are in the menu under your picture at the bottom of the sidebar.</p>'],
                    ['id' => 'start-import', 'q' => 'Can I bring in properties and tenants I already manage?',
                        'a' => 'Yes. <strong>Property portfolio</strong> has <strong>Import properties</strong>, and <strong>Tenants</strong> has <strong>Import data</strong>. '
                            . 'Upload a spreadsheet and confirm how its columns match before the records are created.'],
                ],
            ],
            [
                'key' => 'properties', 'title' => 'Properties & units', 'icon' => 'fa-building', 'needs' => ['complex_management.view'], 'module' => null,
                'items' => [
                    ['id' => 'properties-terms', 'q' => 'What is a property, a unit type and a unit?',
                        'a' => 'A <strong>property</strong> is a building, estate or commercial centre. A <strong>unit type</strong> describes a group of identical units in it, such as "2-bedroom, 2-bathroom, 850 sq ft". '
                            . 'A <strong>unit</strong> is one rentable flat, house or commercial space, such as "A-12". Tenancies, bills and repairs are always for a specific unit.'],
                    ['id' => 'properties-houses', 'q' => 'Can a property have houses, and can a house be sold?',
                        'a' => 'Yes. An estate can hold houses alongside flats and shops. Each house is offered for rent, for sale or both. A house offered for sale can be sold through <strong>Sales</strong> when that module is on.'],
                    ['id' => 'properties-commercial', 'q' => 'How do commercial spaces work?',
                        'a' => 'Commercial spaces are set up with their floor area. A space can be shared by several tenancies according to the area each one occupies, and occupancy is tracked by the area let.'],
                    ['id' => 'properties-facilities', 'q' => 'What are facilities, parking and service charges?',
                        'a' => '<strong>Facilities</strong> and <strong>parking</strong> are the shared amenities a property offers. <strong>Service charges</strong> are recurring charges billed to tenants for those shared facilities. '
                            . 'They are separate from the property\'s own operating expenses.'],
                    ['id' => 'properties-history', 'q' => 'What is the property history?',
                        'a' => 'A record of what happened to a property or one of its units: ownership changes, renovations, legal matters, utilities, incidents and documents. '
                            . 'Records are kept as written; to correct one, add a new record.'],
                    ['id' => 'properties-expenses', 'q' => 'Where do I record property expenses?',
                        'a' => 'Open <strong>Property portfolio</strong> and choose <strong>Property expenses</strong>, or use the <strong>Expenses</strong> link on a property. '
                            . 'Repair costs from closed maintenance requests are added there automatically.',
                        ],
                ],
            ],
            [
                'key' => 'listings', 'title' => 'Listings', 'icon' => 'fa-bullhorn', 'needs' => ['listings_management.view'], 'module' => ModuleService::LISTINGS,
                'items' => [
                    ['id' => 'listings-what', 'q' => 'What is a listing?',
                        'a' => 'A listing advertises one unit type of a property to renters. A property with three 4-bedroom flats and five 2-bedroom flats has two listings. '
                            . "{$app} creates a draft listing for every unit type (and every commercial space) when a property setup is confirmed, and for unit types added later."],
                    ['id' => 'listings-publish', 'q' => 'How do I publish a listing?',
                        'a' => 'Open <strong>Listings</strong>, choose the draft, add the monthly rent, deposit, a description and photos, then publish it. You can unpublish it at any time.'],
                    ['id' => 'listings-availability', 'q' => 'How is the number of available units worked out?',
                        'a' => 'It is counted live: units of that type with no current tenancy that are not reserved or under maintenance. When a tenant moves in the count goes down, and when one moves out it goes up. '
                            . 'A published listing is only shown while at least one unit is available.'],
                    ['id' => 'listings-website', 'q' => 'Can my website show my listings?',
                        'a' => 'Yes. Published listings are available from a read-only feed at <code>/api/v1/listings</code>, which a website developer can use to show them on your site.'],
                ],
            ],
            [
                'key' => 'tenants', 'title' => 'Tenants & leases', 'icon' => 'fa-users', 'needs' => ['tenant_management.view'], 'module' => null,
                'items' => [
                    ['id' => 'tenants-onboard', 'q' => 'How do I move a tenant in?',
                        'a' => 'In <strong>Tenants</strong>, choose <strong>Onboard Tenant</strong>. Enter the tenant\'s details, choose a vacant unit, the move-in date, the rent and the deposit. '
                            . 'The first bills (the deposit and the first rent period) are created straight away, and the unit is marked occupied.'],
                    ['id' => 'tenants-from-lead', 'q' => 'Can I turn a lead into a tenant?',
                        'a' => 'Yes. When a lead has agreed terms, open it in <strong>CRM</strong> and choose <strong>Convert to tenant</strong>. The onboarding form is filled in from the lead.'],
                    ['id' => 'tenants-lease', 'q' => 'Where is the lease?',
                        'a' => 'Open the tenancy and choose <strong>View lease</strong>. It holds the dates, rent and how often it is paid, the deposit, the grace period and any late fee. Signed copies can go on the tenancy\'s <strong>Documents</strong> tab or in <strong>Legal</strong>.'],
                    ['id' => 'tenants-give-notice', 'q' => 'How do notices work?',
                        'a' => 'On the tenancy, choose <strong>Give notice</strong> and enter the move-out date. The tenancy shows as <strong>On Notice</strong> until then; notices are listed on its <strong>Notices</strong> tab.'],
                    ['id' => 'tenants-transfer', 'q' => 'How do I move a tenant to another unit?',
                        'a' => 'On the tenancy, choose <strong>Move / transfer</strong> and pick the new unit and moving date; the preview shows any change in rent and deposit. Once approved, on the moving date the current tenancy is marked <strong>Transferred</strong> and a new tenancy and lease start in the new unit. '
                            . 'Every move is tracked under <strong>Unit transfers</strong>.'],
                    ['id' => 'tenants-end', 'q' => 'What happens when a tenancy ends?',
                        'a' => 'Choose <strong>End tenancy</strong>, the leaving date and the reason. The tenant\'s account is settled for that date: rent for periods starting after they leave is cancelled, damages are charged, '
                            . 'the deposit pays what is still owed, and the rest of the deposit and any prepaid rent is shown as owed back to the tenant. The unit becomes vacant again.'],
                ],
            ],
            [
                'key' => 'rent', 'title' => 'Rent & payments', 'icon' => 'fa-receipt', 'needs' => ['tenant_management.payments', 'tenant_management.billing', 'reports.financial'], 'module' => null,
                'items' => [
                    ['id' => 'rent-billing', 'q' => 'When is rent billed?',
                        'a' => 'In advance, on each tenancy\'s own cycle counted from its move-in date. Every night, rent that has fallen due is billed automatically.'],
                    ['id' => 'rent-late-fees', 'q' => 'How do grace periods and late fees work?',
                        'a' => 'A bill can be paid during the tenancy\'s grace period without penalty. A late fee is added after that only if the lease sets one; with no late fee on the lease, none is charged.'],
                    ['id' => 'rent-record-payment', 'q' => 'How do I record a payment?',
                        'a' => 'Open the tenancy and choose <strong>Record payment</strong>. Each tenant has one running account: a payment settles the oldest open bill first, and anything extra is kept as credit for the next bill.'],
                    ['id' => 'rent-reconciliation', 'q' => 'What is POP reconciliation?',
                        'a' => 'It attaches proof of payment to the payments you recorded. Open <strong>Tenants</strong> → <strong>POP reconciliation</strong>, upload the receipts, pick a receipt, pick the payment it belongs to, then match them. '
                            . 'Payments still without proof are listed, newest first.'],
                    ['id' => 'rent-statement', 'q' => 'How do I give a tenant a statement?',
                        'a' => 'Open the tenancy and download its <strong>Statement (PDF)</strong>: every bill, payment and credit on the tenant\'s account, with the balance.'],
                ],
            ],
            [
                'key' => 'agents', 'title' => 'Agents & commission', 'icon' => 'fa-user-tie', 'needs' => ['agent_management.view'], 'module' => null,
                'items' => [
                    ['id' => 'agents-add', 'q' => 'How do I add an agent?',
                        'a' => 'Open <strong>Agents</strong> and onboard the agent with their contact details and how they earn commission. An agent can be one of your team members or someone outside the team.'],
                    ['id' => 'agents-assign', 'q' => 'How do I give an agent properties or units?',
                        'a' => 'Open the property and use <strong>Assign agents</strong> for the whole property, or <strong>Assign units</strong> to give an agent single units. Agents see and earn on only what they are assigned.'],
                    ['id' => 'agents-commission', 'q' => 'How is agent commission worked out?',
                        'a' => '<p>Agents earn on rent actually collected, not on deposits, late fees or service charges, and it goes to the agent responsible for the tenancy at the time the money comes in.</p>'
                            . '<p>A commission agreement sets how much: a <strong>% of each rent payment</strong> (earned as each payment is recorded), a <strong>% of rent less property expenses</strong>, a <strong>flat amount per paying tenant</strong>, or <strong>tiered by rent collected</strong> (these three are worked out per month). '
                            . 'On house sales an agent earns a <strong>% of the sale price</strong> or a <strong>flat amount per house sold</strong>, as the buyer pays.</p>'
                            . '<p>Changing an agreement starts a new version from a date you choose; commission already earned keeps the terms it was earned under.</p>'],
                    ['id' => 'agents-payouts', 'q' => 'How are agents paid?',
                        'a' => 'Open <strong>Agents</strong> → <strong>Commissions</strong>. Monthly commission (expenses-based, per-tenant and tiered agreements) is worked out each month and waits for your approval. '
                            . 'Each agent\'s <strong>Statement &amp; payout</strong> shows what they earned and what has been paid, and is where you record a payout.'],
                    ['id' => 'agents-sign-in', 'q' => 'Can agents sign in?',
                        'a' => 'Yes. On the agent\'s profile, link them to an existing team member or invite them to sign in. Signed-in agents see their own leads, tasks, tenancies and commission on <strong>My agent dashboard</strong>.'],
                ],
            ],
            [
                'key' => 'crm', 'title' => 'CRM & leads', 'icon' => 'fa-address-card', 'needs' => ['crm_management.view', 'lead_management.view', 'task_management.view'], 'module' => null,
                'items' => [
                    ['id' => 'crm-lifecycle', 'q' => 'How does a lead become a tenant?',
                        'a' => '<ol><li><strong>Capture &amp; qualify</strong>: record the source, contact details, what they need and the agent handling them.</li>'
                            . '<li><strong>Engage</strong>: calls and viewings, with the agent\'s follow-up tasks.</li>'
                            . '<li><strong>Agree terms</strong>: confirm the unit, rent and move-in date.</li>'
                            . '<li><strong>Convert &amp; onboard</strong>: <strong>Convert to tenant</strong> creates the tenancy.</li></ol>'],
                    ['id' => 'crm-tasks', 'q' => 'How do follow-up tasks work?',
                        'a' => 'Each phase of a lead comes with follow-up tasks, each assigned to an agent with a due date. Agents work them under <strong>Agents</strong> → <strong>Tasks</strong>. '
                            . 'A task is overdue once its due date or its response deadline has passed.'],
                    ['id' => 'crm-documents', 'q' => 'What is the Document Center?',
                        'a' => 'One place for the documents collected while working leads, such as IDs and application papers, so they are at hand when the lead becomes a tenant.'],
                ],
            ],
            [
                'key' => 'maintenance', 'title' => 'Maintenance', 'icon' => 'fa-wrench', 'needs' => ['complex_management.view'], 'module' => null,
                'items' => [
                    ['id' => 'maintenance-log', 'q' => 'How do I log a repair?',
                        'a' => 'Open <strong>Maintenance</strong> and choose <strong>Log a request</strong>. Choose the property and unit; the current tenant is linked automatically. Requests can come from a tenant, an inspection or the team.'],
                    ['id' => 'maintenance-statuses', 'q' => 'What do the statuses mean?',
                        'a' => '<strong>Open</strong> (reported), <strong>Assigned</strong> (someone is responsible), <strong>In progress</strong>, <strong>Completed</strong> (the work is done and is awaiting sign-off) and <strong>Closed</strong> (signed off, with its cost). A request that will not go ahead is <strong>Cancelled</strong>.'],
                    ['id' => 'maintenance-costs', 'q' => 'Where do repair costs go?',
                        'a' => 'When a request is closed, its cost is booked as a property expense, so it shows in the property\'s expenses and reports.'],
                    ['id' => 'maintenance-mine', 'q' => 'How do I see only my requests?',
                        'a' => 'Choose <strong>Assigned to me</strong> on the Maintenance page. <strong>Awaiting sign-off</strong> lists completed work still to be checked and closed.'],
                ],
            ],
            [
                'key' => 'sales', 'title' => 'Sales', 'icon' => 'fa-handshake', 'needs' => ['sales_management.view'], 'module' => ModuleService::SALES,
                'items' => [
                    ['id' => 'sales-start', 'q' => 'How do I sell a house?',
                        'a' => 'Open <strong>Sales</strong> and choose <strong>Start a sale</strong>. Record the buyer, the agreed price, an optional deposit and the instalments of the balance; the instalments must add up to the price less the deposit. '
                            . 'The house goes under offer straight away and leaves the rentable inventory.'],
                    ['id' => 'sales-payments', 'q' => 'How do I record the buyer\'s payments?',
                        'a' => 'Open the sale and record each payment as it comes in. Each payment gets a receipt and settles the oldest open instalment first, and the sale shows what is paid and what is still due.'],
                    ['id' => 'sales-complete', 'q' => 'When is a sale complete?',
                        'a' => 'Complete the sale once it is fully paid and the house has been handed over. The agent on the sale earns sales commission as the buyer pays.'],
                ],
            ],
            [
                'key' => 'legal', 'title' => 'Legal documents', 'icon' => 'fa-balance-scale', 'needs' => ['legal_documents.view'], 'module' => null,
                'items' => [
                    ['id' => 'legal-what', 'q' => 'What can I keep in Legal?',
                        'a' => 'Lease agreements, contracts, notices, inspection reports and other paperwork. Upload PDF, Word, Excel, image (JPG or PNG) or text files of up to 20 MB, each with a document type.'],
                    ['id' => 'legal-folders', 'q' => 'How are documents organised?',
                        'a' => 'In folders you create, for example one per property or one for each kind of agreement. Create a folder first, then upload into it. A whole folder can be downloaded as a zip.'],
                    ['id' => 'legal-tenancy-documents', 'q' => 'Where are a tenant\'s own documents?',
                        'a' => 'On the tenancy\'s <strong>Documents</strong> tab, alongside the tenancy they belong to.'],
                ],
            ],
            [
                'key' => 'reports', 'title' => 'Reports', 'icon' => 'fa-chart-bar', 'needs' => ['reports.view', 'reports.financial', 'reports.occupancy', 'reports.agents', 'reports.sales', 'reports.maintenance'], 'module' => null,
                'items' => [
                    ['id' => 'reports-what', 'q' => 'What reports are there?',
                        'a' => 'Open <strong>Reports</strong> in the menu. The page starts with a business overview, every property side by side, and links to the reports: '
                            . 'Income &amp; expenses, Rent roll &amp; arrears, Occupancy &amp; vacancy, Leases &amp; notices, Agents &amp; leads, Sales and Maintenance. You see the ones your role allows.'],
                    ['id' => 'reports-access', 'q' => 'Who can open which report?',
                        'a' => 'Each role has a <strong>Reports</strong> group in Team &amp; roles: Overview, Money (income and rent roll), Occupancy &amp; leases, Agents &amp; leads, Sales, Maintenance, and Download for Excel and PDF. '
                            . 'Give a role only the reports it needs.'],
                    ['id' => 'reports-filters', 'q' => 'Can I run a report for one property or another period?',
                        'a' => 'Yes. Choose a period (this month, last quarter, the last 12 months, custom dates and more) and a property, then <strong>Show</strong>. The agent reports can also be run for one agent. '
                            . 'Team members limited to assigned properties only see those properties in every report.'],
                    ['id' => 'reports-download', 'q' => 'Can I download a report?',
                        'a' => 'Every report downloads as <strong>Excel</strong> (amounts as numbers, ready to work with) or <strong>PDF</strong> (to send to a landlord or owner), with the same period and property you chose. '
                            . 'Downloading needs the Reports → Download right in your role.'],
                    ['id' => 'reports-counting', 'q' => 'How are the figures counted?',
                        'a' => 'Billed and collected follow each charge\'s due date; money received follows the date it came in. Deposits are held for tenants and are not income. Repair costs count once, as property expenses. '
                            . 'Each report ends with a short note on how its figures are counted.'],
                ],
            ],
            [
                'key' => 'account', 'title' => 'Sign-in, team & account', 'icon' => 'fa-user-shield', 'needs' => null, 'module' => null,
                'items' => [
                    ['id' => 'account-sign-in', 'q' => 'How does signing in work?',
                        'a' => 'Enter your email and password, then the 6-digit code emailed to you. The first time you sign in, you choose your own password.'],
                    ['id' => 'account-forgot-password', 'q' => 'I forgot my password.',
                        'a' => 'Choose <strong>Forgot password?</strong> on the sign-in page and follow the link sent to your email.'],
                    ['id' => 'account-missing-page', 'q' => 'Why can\'t I see a page or a button?',
                        'a' => 'Your role decides what you can open and change, and a team member can be limited to the properties assigned to them. Ask your account owner if you need more access.'],
                    ['id' => 'account-support-tickets', 'q' => 'Where are my support tickets?',
                        'a' => 'Open the menu under your picture and choose <strong>My support tickets</strong>. Each ticket shows its status and the replies, and you can reply or add files there. You also get a notification and an email when the support team answers.'],
                    ['id' => 'account-profile', 'q' => 'Where do I change my details?',
                        'a' => 'Open the menu under your picture at the bottom of the sidebar and choose <strong>My profile</strong>.'],
                    ['id' => 'account-team', 'q' => 'How do I add team members and decide what they can do?', 'owner' => true,
                        'a' => 'Open <strong>Team &amp; roles</strong> from the menu under your picture. Invite people and give each a role. Starter roles (such as property manager, finance officer, caretaker and accountant) are ready to use, and you can make your own. '
                            . 'A role can be limited to the properties assigned to each member.'],
                    ['id' => 'account-activity-log', 'q' => 'Can I see what people did, and when?', 'owner' => true,
                        'a' => 'Yes. Open <strong>Activity log</strong> in the menu under your picture: every page opened, every change and download, refused requests and sign-ins, with the time, the person and where they signed in from. '
                            . 'Filter by person, area and dates, or download it. Form contents and passwords are never recorded. Give other roles the <strong>Activity log</strong> rights in Team &amp; roles if they should see it too.'],
                    ['id' => 'account-roles-explained', 'q' => 'How do I know what a role can see and do?', 'owner' => true,
                        'a' => 'In Team &amp; roles, each role card lists the menu it gets. Opening a role shows its rights under the same headings as the left menu, each tick box with what it allows, and a live preview of the person\'s menu. '
                            . 'Ticking an action also ticks the "View" it needs.'],
                    ['id' => 'account-support-agents', 'q' => 'Who answers support tickets?', 'owner' => true,
                        'a' => 'Platform administrators, and anyone they make a <strong>support agent</strong>: open <strong>Platform</strong>, find the person on the team list and choose <strong>Make support agent</strong>. '
                            . 'Support agents get the support inbox and hear about new tickets, but not the rest of platform administration.'],
                    ['id' => 'account-modules', 'q' => 'Can I switch off parts I don\'t use?', 'owner' => true,
                        'a' => 'Yes. <strong>Listings</strong> and <strong>Sales</strong> are optional: switch them on or off under <strong>Business settings</strong> → <strong>Modules</strong>.'],
                ],
            ],
        ];
    }
}
