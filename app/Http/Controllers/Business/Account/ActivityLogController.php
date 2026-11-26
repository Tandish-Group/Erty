<?php

namespace App\Http\Controllers\Business\Account;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Access\BusinessAccessResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The activity log: who opened, changed or downloaded what, and when, for everyone in the business. */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        [$query, $filters] = $this->query($request);
        $owner = business_owner_id();

        return view('pages.business.activity.index', [
            'entries' => $query->with('user:id,first_name,last_name,email,is_sub_account,business_role_id', 'user.businessRole:id,name')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'people' => User::where(fn ($q) => $q->where('id', $owner)->orWhere('main_account_user_id', $owner))->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']),
            'areas' => $this->areas(),
            'keepDays' => (int) config('erty.activity_log.keep_days'),
            'canExport' => $request->user()->isBusinessOwner() || $request->user()->hasBusinessPermission('activity_log', 'export'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$query] = $this->query($request);
        $areas = $this->areas();
        $name = 'activity-log-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query, $areas) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'Person', 'Email', 'Event', 'Area', 'Right', 'Page', 'Record', 'Result', 'IP address', 'Browser']);
            $query->with('user:id,first_name,last_name,email')->chunk(500, function ($rows) use ($out, $areas) {
                foreach ($rows as $e) {
                    // Cells that start like a formula are prefixed so spreadsheets show them as text.
                    $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
                    fputcsv($out, array_map($safe, [
                        $e->created_at?->format('Y-m-d H:i:s'),
                        $e->user ? trim($e->user->first_name . ' ' . $e->user->last_name) : '',
                        $e->user?->email,
                        $e->eventLabel(),
                        $areas[$e->area] ?? $e->area,
                        $e->action,
                        $e->method . ' ' . $e->path,
                        $e->subject,
                        $e->status,
                        $e->ip,
                        $e->user_agent,
                    ]));
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: \Illuminate\Database\Eloquent\Builder, 1: array} */
    private function query(Request $request): array
    {
        $from = $this->date($request->query('from')) ?? now()->subDays(6)->startOfDay();
        $to = $this->date($request->query('to'))?->endOfDay() ?? now()->endOfDay();
        $filters = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'user' => $request->integer('user') ?: null,
            'area' => (string) $request->query('area', ''),
            'event' => (string) $request->query('event', ''),
            'type' => in_array($request->query('type'), ['changes', 'views', 'sign_ins', 'refused'], true) ? $request->query('type') : '',
            'q' => trim((string) $request->query('q', '')),
        ];

        $query = ActivityLog::query()->where('business_id', business_owner_id())
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['user'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['area'] !== '', fn ($q) => $q->where('area', $filters['area']))
            ->when(array_key_exists($filters['event'], ActivityLog::EVENTS), fn ($q) => $q->where('event', $filters['event']))
            ->when($filters['type'] === 'changes', fn ($q) => $q->whereIn('event', ['created', 'changed', 'deleted', 'downloaded']))
            ->when($filters['type'] === 'views', fn ($q) => $q->where('event', 'viewed'))
            ->when($filters['type'] === 'sign_ins', fn ($q) => $q->whereIn('event', ['signed_in', 'signed_out', 'sign_in_failed']))
            ->when($filters['type'] === 'refused', fn ($q) => $q->whereIn('event', ['refused', 'sign_in_failed']))
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('path', '%' . $filters['q'] . '%')->orWhereLike('subject', '%' . $filters['q'] . '%')->orWhereLike('ip', '%' . $filters['q'] . '%')))
            ->orderByDesc('created_at')->orderByDesc('id');

        return [$query, $filters];
    }

    /** Area key => name (permission groups, plus sign-in, support and platform). */
    private function areas(): array
    {
        $resolver = app(BusinessAccessResolver::class);

        return collect(config('business_route_permissions'))->mapWithKeys(fn ($d) => [$d['group'] => $d['display_name'] ?? $resolver->label($d['group'])])
            ->merge(['auth' => 'Sign-in', 'support_inbox' => 'Support inbox', 'platform' => 'Platform administration'])
            ->sort()->all();
    }

    private function date($value): ?Carbon
    {
        try {
            return $value ? Carbon::parse((string) $value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
