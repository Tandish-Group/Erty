<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Access\BusinessPermissionService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Signs in (in-process) as every person of a business and requests pages through the full middleware stack,
 * comparing what comes back with an independent rule of what that person may see:
 *   sections  — open only with the role's permission;
 *   data      — properties in full (assigned, company-managed, or managed as primary), and on properties
 *               reached only through units, just those units' tenancies; leads at full properties or their
 *               own; repairs on visible units or assigned to them; commission only their own unless their
 *               role covers every property.
 * Read-only: it only sends GET requests. Exits non-zero on any mismatch.
 */
class ErtyAccessAudit extends Command
{
    protected $signature = 'erty:access-audit {--owner= : Business owner user id (default: the only business)} {--user= : Audit one user id only} {--details : Print every check, not just failures} {--links : Also open each person\'s pages and fail on any link, button or form there that leads somewhere they may not go (slower)}';

    protected $description = 'Check what every team member can open and see, through the real routes (read-only)';

    private HttpKernel $kernel;
    private int $ip = 0;

    public function handle(BusinessPermissionService $permissions): int
    {
        $ownerId = (int) ($this->option('owner') ?: DB::table('businesses')->orderBy('id')->value('user_id'));
        $this->kernel = app(HttpKernel::class);
        config(['erty.activity_log.enabled' => false]); // the audit's own page visits are not people's activity
        $data = $this->dataset($ownerId);

        $people = User::with('businessRole')->where(fn ($q) => $q->where('id', $ownerId)->orWhere('main_account_user_id', $ownerId))
            ->where(fn ($q) => $q->where('is_suspended', false)->orWhereNull('is_suspended'))
            ->when($this->option('user'), fn ($q, $id) => $q->where('id', $id))->orderBy('id')->get();

        $summary = [];
        $failures = 0;
        foreach ($people as $user) {
            $expect = $this->expectations($user, $ownerId, $data, $permissions);
            $results = $this->audit($user, $expect, $data);
            if ($this->option('links') && $user->is_sub_account) {
                $results = array_merge($results, $this->linkChecks($user));
            }
            $bad = array_filter($results, fn ($r) => !$r['ok']);
            $failures += count($bad);
            $summary[] = [$user->id, trim($user->first_name . ' ' . $user->last_name), $user->is_sub_account ? ($user->businessRole->name ?? 'No role') : 'Owner',
                $expect['scope'], count($results) - count($bad) . '/' . count($results), $bad ? 'FAIL' : 'ok'];
            foreach ($results as $r) {
                if (!$r['ok'] || $this->option('details')) {
                    $this->line(sprintf('  %s  %-28s %-58s %s', $r['ok'] ? 'ok  ' : 'FAIL', mb_strimwidth($user->first_name . ' ' . $user->last_name, 0, 28), mb_strimwidth($r['check'], 0, 58), $r['got']));
                }
            }
        }
        $this->table(['User', 'Name', 'Role', 'Sees', 'Checks passed', 'Result'], $summary);
        $failures ? $this->error("{$failures} access checks failed.") : $this->info('Every person sees exactly what their role and assignments allow.');
        return $failures ? self::FAILURE : self::SUCCESS;
    }

    /** Everything in the business the audit asks about. */
    private function dataset(int $ownerId): array
    {
        return [
            'complexes' => DB::table('complexes')->where('user_id', $ownerId)->get(['id', 'title', 'slug', 'assigned_agent_id', 'management_type']),
            'tenancies' => DB::table('tenant_management')->where('managed_by', $ownerId)->get(['id', 'tenancy_ref', 'complex_id', 'residential_unit_id', 'commercial_unit_id', 'status', 'tenant_profile_id']),
            'leads' => DB::table('leads')->where('user_id', $ownerId)->whereNull('deleted_at')->get(['id', 'complex_id', 'assigned_agent_id']),
            'repairs' => DB::table('maintenance_requests')->where('managed_by', $ownerId)->get(['id', 'reference', 'complex_id', 'residential_unit_id', 'commercial_unit_id', 'assigned_user_id']),
            'agents' => DB::table('agents')->where('created_by', $ownerId)->whereNull('deleted_at')->get(['id', 'user_id', 'first_name']),
            'residential' => DB::table('complex_residential_units')->whereIn('complex_id', DB::table('complexes')->where('user_id', $ownerId)->select('id'))->get(['id', 'complex_id', 'unit_number', 'assigned_agent_id']),
            'commercial' => DB::table('commercial_units')->whereIn('complex_id', DB::table('complexes')->where('user_id', $ownerId)->select('id'))->get(['id', 'complex_id', 'unit_number', 'assigned_agent_id']),
            'sales' => \Illuminate\Support\Facades\Schema::hasTable('property_sales') ? DB::table('property_sales')->where('business_id', $ownerId)->get(['id', 'reference', 'complex_id', 'residential_unit_id', 'agent_id']) : collect(),
        ];
    }

    /** The independent rule: what this person should be able to open and see. */
    private function expectations(User $user, int $ownerId, array $data, BusinessPermissionService $permissions): array
    {
        $can = fn ($group, $action = 'view') => $permissions->can($user, $group, $action);
        $agent = $data['agents']->firstWhere('user_id', $user->id);
        $allProperties = !$user->is_sub_account || !$user->limitedToAssignedProperties();

        $full = $allProperties ? $data['complexes']->pluck('id') : collect()
            ->merge(DB::table('complex_user')->where('user_id', $user->id)->pluck('complex_id'))
            ->merge($data['complexes']->filter(fn ($c) => ($c->management_type ?? 'individual') === 'company' || !$c->assigned_agent_id)->pluck('id'))
            ->merge($agent ? $data['complexes']->where('assigned_agent_id', $agent->id)->pluck('id') : []);
        $full = $full->map(fn ($id) => (int) $id)->unique()->values();
        $units = ['residential' => collect(), 'commercial' => collect()];
        if (!$allProperties && $agent) {
            foreach (['residential', 'commercial'] as $kind) {
                $units[$kind] = $data[$kind]->where('assigned_agent_id', $agent->id)->reject(fn ($u) => $full->contains((int) $u->complex_id))->pluck('id')->map(fn ($id) => (int) $id);
            }
        }
        $unitOnlyComplexes = $data['residential']->whereIn('id', $units['residential'])->pluck('complex_id')
            ->merge($data['commercial']->whereIn('id', $units['commercial'])->pluck('complex_id'))->map(fn ($id) => (int) $id)->unique();

        $unitVisible = fn ($complexId, $resId, $comId) => $full->contains((int) $complexId)
            || ($resId && $units['residential']->contains((int) $resId)) || ($comId && $units['commercial']->contains((int) $comId));

        return [
            'scope' => $allProperties ? 'all properties' : ($full->count() . ' in full' . ($unitOnlyComplexes->count() ? ', units on ' . $unitOnlyComplexes->count() : '')),
            'can' => $can,
            'complex' => fn ($id) => $full->contains((int) $id) || $unitOnlyComplexes->contains((int) $id),
            'tenancy' => fn ($t) => $unitVisible($t->complex_id, $t->residential_unit_id, $t->commercial_unit_id),
            'unit' => fn ($kind, $u) => $full->contains((int) $u->complex_id) || $units[$kind]->contains((int) $u->id),
            'lead' => fn ($l) => $full->contains((int) $l->complex_id) || ($agent && (int) $l->assigned_agent_id === (int) $agent->id),
            // A sale: their properties (their houses on unit-only ones), or a sale they are the agent on.
            'sale' => fn ($x) => $unitVisible($x->complex_id, $x->residential_unit_id, null) || ($agent && (int) $x->agent_id === (int) $agent->id),
            'repair' => fn ($m) => $unitVisible($m->complex_id, $m->residential_unit_id, $m->commercial_unit_id) || (int) $m->assigned_user_id === (int) $user->id,
            'commission' => fn ($a) => !$user->is_sub_account || ($agent && (int) $agent->id === (int) $a->id)
                || ($allProperties && !$agent && $can('agent_management')),
            'agent' => $agent,
        ];
    }

    private function audit(User $user, array $e, array $d): array
    {
        $can = $e['can'];
        $out = [];
        $salesOn = app(\App\Services\Access\ModuleService::class)->enabled('sales', $user);
        $check = function (string $label, string $path, bool $shouldOpen) use ($user, &$out) {
            [$status, , $location] = $this->get($user, $path);
            // Refused means a proper refusal (403, 404 or a redirect away); a server error is never a pass.
            $ok = $shouldOpen ? $status === 200 : in_array($status, [302, 401, 403, 404], true);
            $out[] = ['check' => ($shouldOpen ? 'opens ' : 'refused ') . $label, 'ok' => $ok, 'got' => $status . ($location ? ' → ' . $location : '')];
        };

        // Sections follow the role.
        foreach ([
            ['dashboard', '/dashboard', $can('dashboard_access')],
            ['properties', '/complex', $can('complex_management')],
            ['tenants', '/tenant_management', $can('tenant_management')],
            ['leads', '/customer_relationship/lead_management', $can('lead_management')],
            ['agents', '/agents', $can('agent_management')],
            ['tasks', '/agents/tasks', $can('task_management')],
            ['expenses', '/complex-expenses', $can('expense_management')],
            ['commissions', '/agents/commissions', $can('agent_management')],
            ['repairs', '/maintenance', $can('maintenance_management')],
            ['legal', '/legal', $can('legal_documents')],
            ['sales', '/sales', $salesOn && $can('sales_management')],
            ['team', '/business_account/team', $can('subaccount_management') || $can('role_management')],
            ['payment reconciliation', '/tenant_management/payment-reconciliation', $can('tenant_management', 'payments')],
            ['platform', '/platform', $user->isPlatformAdmin()],
            ['reports', '/reports', app(\App\Services\Reports\ReportRegistry::class)->availableTo($user)->isNotEmpty()],
        ] as [$label, $path, $allowed]) {
            $check('section ' . $label, $path, $allowed);
        }

        // Dashboard figures follow the data they may see.
        if ($can('dashboard_access')) {
            [$status, $body] = $this->get($user, '/dashboard');
            if ($status === 200 && $can('complex_management')) {
                $want = $d['complexes']->filter(fn ($c) => $e['complex']($c->id))->count();
                $got = preg_match('~<strong>(\d+)</strong><small>Complexes you manage~', $body, $m) ? (int) $m[1] : null;
                $out[] = ['check' => "dashboard shows {$want} properties", 'ok' => $got === $want, 'got' => (string) $got];
            }
            if ($status === 200 && $can('tenant_management')) {
                $want = $d['tenancies']->where('status', 'Active')->filter(fn ($t) => $e['tenancy']($t))->pluck('tenant_profile_id')->unique()->count();
                $got = preg_match('~<strong>(\d+)</strong><small>People in active tenancy~', $body, $m) ? (int) $m[1] : null;
                $out[] = ['check' => "dashboard shows {$want} tenants", 'ok' => $got === $want, 'got' => (string) $got];
            }
        }

        // Properties and their units.
        foreach ($d['complexes'] as $c) {
            $check("property {$c->title}", '/complex/' . $c->slug, $can('complex_management') && $e['complex']($c->id));
        }
        if ($can('complex_management')) {
            foreach ($d['complexes']->filter(fn ($c) => $e['complex']($c->id)) as $c) {
                foreach (['residential' => '/residential-units?complex_id=', 'commercial' => '/commercial-units?complex_id='] as $kind => $path) {
                    [$status, $body] = $this->get($user, $path . $c->id);
                    if ($status !== 200) continue;
                    $leaks = $d[$kind]->where('complex_id', $c->id)->reject(fn ($u) => $e['unit']($kind, $u))
                        ->filter(fn ($u) => $u->unit_number && preg_match('~(?<![\w-])' . preg_quote($u->unit_number, '~') . '(?![\w-])~', $body));
                    $out[] = ['check' => "{$kind} units at {$c->title}: only theirs", 'ok' => $leaks->isEmpty(), 'got' => $leaks->isEmpty() ? 'none of others' : 'shows ' . $leaks->pluck('unit_number')->implode(', ')];
                }
            }
        }

        // Tenancies: direct links, the tenants list and the money report.
        foreach ($d['tenancies'] as $t) {
            $check("tenancy {$t->tenancy_ref}", '/tenant_management/tenancy/' . $t->tenancy_ref . '/view', $can('tenant_management') && $e['tenancy']($t));
        }
        if ($can('tenant_management')) {
            [$status, $body] = $this->get($user, '/tenant_management');
            $leaks = $d['tenancies']->reject(fn ($t) => $e['tenancy']($t))->filter(fn ($t) => $t->tenancy_ref && str_contains($body, $t->tenancy_ref));
            $out[] = ['check' => 'tenants list: no one else\'s tenancies', 'ok' => $status === 200 && $leaks->isEmpty(), 'got' => $status . ($leaks->isNotEmpty() ? ' shows ' . $leaks->pluck('tenancy_ref')->implode(', ') : '')];
        }
        if ($can('reports', 'financial')) {
            // The Income & expenses report lists money received per tenancy; the rent roll lists every tenancy.
            foreach (['income' => '/reports/income?period=custom&from=2020-01-01&to=' . now()->addYear()->toDateString(), 'rent roll' => '/reports/rent-roll'] as $name => $url) {
                [$status, $body] = $this->get($user, $url);
                $leaks = $d['tenancies']->reject(fn ($t) => $e['tenancy']($t))->filter(fn ($t) => $t->tenancy_ref && str_contains($body, $t->tenancy_ref));
                $out[] = ['check' => "{$name} report: only their tenancies", 'ok' => $status === 200 && $leaks->isEmpty(), 'got' => $status . ($leaks->isNotEmpty() ? ' shows ' . $leaks->pluck('tenancy_ref')->implode(', ') : '')];
            }
        }

        // Leads and repairs.
        foreach ($d['leads'] as $l) {
            $check("lead #{$l->id}", '/customer_relationship/lead_management/lead/' . Crypt::encrypt($l->id), $can('lead_management') && $e['lead']($l));
        }
        foreach ($d['repairs'] as $m) {
            $check("repair {$m->reference}", '/maintenance/' . $m->reference, $can('maintenance_management') && $e['repair']($m));
        }

        // House sales: direct links, and the sales list shows no one else's.
        foreach ($d['sales'] as $x) {
            $check("sale {$x->reference}", '/sales/' . $x->reference, $salesOn && $can('sales_management') && $e['sale']($x));
        }
        if ($salesOn && $can('sales_management') && $d['sales']->isNotEmpty()) {
            [$status, $body] = $this->get($user, '/sales');
            $leaks = $d['sales']->reject(fn ($x) => $e['sale']($x))->filter(fn ($x) => str_contains($body, $x->reference));
            $out[] = ['check' => 'sales list: no one else\'s sales', 'ok' => $status === 200 && $leaks->isEmpty(), 'got' => $status . ($leaks->isNotEmpty() ? ' shows ' . $leaks->pluck('reference')->implode(', ') : '')];
        }

        // Commission statements: their own, or anyone's for roles that cover every property.
        if ($can('agent_management')) {
            foreach ($d['agents'] as $a) {
                $check("commission statement of {$a->first_name}", '/agents/commissions/' . $a->id . '/statement', $e['commission']($a));
            }
        }
        return $out;
    }

    /** One in-process request as this person, through every middleware. */
    /**
     * Open the person's pages (starting from every section and following links they may open, one page per
     * route) and report each link, form or data-url there that leads to a route their role does not allow.
     * Things a person cannot use should not be shown to them.
     */
    private function linkChecks(User $user, int $maxPages = 70): array
    {
        $resolver = app(\App\Services\Access\BusinessAccessResolver::class);
        $routes = app('router')->getRoutes();
        $resolve = function (string $url, string $method) use ($routes) {
            $path = parse_url($url, PHP_URL_PATH);
            if (!$path || str_starts_with($path, '/storage') || str_ends_with($path, '/logout') || preg_match('~\.(css|js|png|jpe?g|svg|ico|pdf|webp|gif)$~i', $path)) {
                return null;
            }
            try {
                return $routes->match(Request::create($path, $method));
            } catch (\Throwable) {
                return false;
            }
        };
        $queue = array_values(array_unique(array_merge(array_values(config('business_access.landing', [])), [
            '/customer_relationship', '/agents/commissions', '/tenant_management/billings', '/tenant_management/payment-reconciliation',
            '/tenant_management/movement', '/listings', '/sales', '/commercial-units', '/residential-units', '/business/faq', '/my-profile', '/support/tickets',
        ])));
        $seenPages = $seenRoutes = $problems = [];
        $pages = 0;
        $hosts = array_filter(['localhost', '127.0.0.1', parse_url((string) config('app.url'), PHP_URL_HOST)]);
        while ($queue && $pages < $maxPages) {
            $path = array_shift($queue);
            if (isset($seenPages[$path]) || !($route = $resolve($path, 'GET'))) {
                continue;
            }
            $seenPages[$path] = true;
            if (($rule = $resolver->ruleFor($route)) && !$resolver->allows($user, $rule)) {
                continue;
            }
            [$status, $html] = $this->get($user, $path);
            if ($status !== 200 || !str_contains($html, '<html')) {
                continue;
            }
            $pages++;
            $items = [];
            preg_match_all('~<a\b[^>]*href="([^"#]+)"[^>]*>(.*?)</a>~si', $html, $links, PREG_SET_ORDER);
            foreach ($links as $m) $items[] = ['GET', html_entity_decode($m[1]), trim(preg_replace('/\s+/', ' ', strip_tags($m[2])))];
            preg_match_all('~<form\b([^>]*)>~si', $html, $forms, PREG_SET_ORDER);
            foreach ($forms as $m) {
                if (preg_match('~action="([^"]+)"~i', $m[1], $a)) {
                    $items[] = [preg_match('~method="get"~i', $m[1]) ? 'GET' : 'POST', html_entity_decode($a[1]), 'form'];
                }
            }
            preg_match_all('~\sdata-(?:url|href|action|endpoint)="(/[^"]+)"~i', $html, $datas, PREG_SET_ORDER);
            foreach ($datas as $m) $items[] = ['GET', html_entity_decode($m[1]), 'button'];
            foreach ($items as [$method, $url, $text]) {
                $host = parse_url($url, PHP_URL_HOST);
                if (($host && !in_array($host, $hosts, true)) || preg_match('~^(mailto|tel|javascript):~', $url)) {
                    continue;
                }
                $target = $resolve($url, $method);
                if ($target === false && $method === 'POST') {
                    $target = $resolve($url, 'PUT') ?: $resolve($url, 'DELETE') ?: $resolve($url, 'PATCH');
                }
                if (!$target) {
                    continue;
                }
                $targetRule = $resolver->ruleFor($target);
                if ($targetRule && !$resolver->allows($user, $targetRule)) {
                    $needs = ($targetRule['group'] ?? $targetRule['type']) . (isset($targetRule['action']) ? '.' . $targetRule['action'] : '');
                    $problems["\"" . mb_strimwidth($text, 0, 30, '…') . "\" → " . ($target->getName() ?: $target->uri()) . " (needs {$needs})"][] = $path;
                } elseif ($method === 'GET' && !isset($seenRoutes[$name = $target->getName() ?: $target->uri()])) {
                    $seenRoutes[$name] = true;
                    $queue[] = parse_url($url, PHP_URL_PATH);
                }
            }
        }
        $out = [['check' => "pages opened while following links ({$pages})", 'ok' => $pages > 0, 'got' => (string) $pages]];
        foreach ($problems as $what => $where) {
            $out[] = ['check' => 'shows ' . $what, 'ok' => false, 'got' => 'on ' . implode(', ', array_slice(array_unique($where), 0, 2))];
        }
        if (!$problems) {
            $out[] = ['check' => 'no links to anything their role does not allow', 'ok' => true, 'got' => 'none'];
        }

        return $out;
    }

    private function get(User $user, string $path, bool $json = false): array
    {
        app()->forgetScopedInstances();
        Auth::forgetGuards();
        Auth::login($user);
        $request = Request::create($path, 'GET', [], [], [], ['REMOTE_ADDR' => '10.77.' . intdiv(++$this->ip, 250) % 250 . '.' . ($this->ip % 250 + 1)]);
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }
        try {
            $response = $this->kernel->handle($request);
            $this->kernel->terminate($request, $response);
        } catch (\Throwable $e) {
            return [500, $e->getMessage(), $e->getMessage()];
        }
        $location = $response->headers->get('Location');
        return [$response->getStatusCode(), (string) $response->getContent(), $location ? parse_url($location, PHP_URL_PATH) : null];
    }
}
