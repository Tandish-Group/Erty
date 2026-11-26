<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Support;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The support inbox: every ticket posted on this installation, for platform administrators and support agents. */
class SupportInboxController extends Controller
{
    public function __construct(private SupportTicketService $tickets)
    {
    }

    public function index(Request $request)
    {
        $status = (string) $request->query('status', 'active');
        $topic = $request->integer('topic') ?: null;
        $priority = (string) $request->query('priority', '');
        $search = trim((string) $request->query('q', ''));

        $query = SupportTicket::with(['user', 'topic', 'assignee']);
        match ($status) {
            'active' => $query->open(),
            'all' => null,
            default => array_key_exists($status, SupportTicket::STATUSES) ? $query->where('status', $status) : $query->open(),
        };
        $query->when($topic, fn ($q) => $q->where('support_id', $topic))
            ->when(array_key_exists($priority, SupportTicket::PRIORITIES), fn ($q) => $q->where('priority', $priority))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->whereLike('reference', "%{$search}%")
                        ->orWhereLike('description', "%{$search}%")
                        ->orWhereLike('custom_topic', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->whereLike('first_name', "%{$search}%")->orWhereLike('last_name', "%{$search}%")->orWhereLike('email', "%{$search}%"));
                });
            });

        $tickets = $query
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('last_activity_at')
            ->paginate(25)->withQueryString();

        $counts = SupportTicket::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);

        return view('pages.platform.support.index', [
            'tickets' => $tickets,
            'counts' => $counts,
            'activeCount' => collect(SupportTicket::OPEN_STATUSES)->sum(fn ($s) => $counts[$s] ?? 0),
            'topics' => Support::orderBy('id')->get(['id', 'topic']),
            'status' => $status,
            'topicFilter' => $topic,
            'priority' => $priority,
            'search' => $search,
            'filtered' => $topic || $priority !== '' || $search !== '',
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user.businessRole', 'topic', 'assignee', 'messages.user', 'attachments']);

        return view('pages.platform.support.show', [
            'ticket' => $ticket,
            'admins' => User::role(['admin', 'support'])->where('is_suspended', false)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']),
            'otherTickets' => SupportTicket::where('user_id', $ticket->user_id)->whereKeyNot($ticket->id)->latest('id')->limit(5)->get(['id', 'reference', 'status', 'description', 'created_at']),
            'fileRules' => config('erty.support.attachments'),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'body' => 'nullable|string|max:20000',
            'status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))],
            'files' => 'nullable|array',
        ]);
        if (trim(strip_tags((string) ($data['body'] ?? ''))) === '' && !$request->hasFile('files')) {
            return back()->withErrors(['body' => 'Write a reply or add a file.'])->withInput();
        }

        $this->tickets->reply($ticket, $request->user(), $data['body'] ?? null, true, $data['status'] ?? null, $request->file('files', []));

        return redirect()->route('platform.support.show', $ticket->reference)->with('success', 'Reply sent to ' . ($ticket->user?->first_name ?: 'the person who posted the ticket') . '.');
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $adminIds = User::role(['admin', 'support'])->where('is_suspended', false)->pluck('id')->all();
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'assigned_to' => ['nullable', 'integer', Rule::in($adminIds)],
        ]);
        $data['assigned_to'] = $data['assigned_to'] ?? null;

        $this->tickets->update($ticket, $request->user(), $data);

        return redirect()->route('platform.support.show', $ticket->reference)->with('success', 'Ticket updated.');
    }

    public function file(SupportTicket $ticket, SupportTicketAttachment $attachment)
    {
        abort_unless((int) $attachment->ticket_id === (int) $ticket->id, 404);

        return $this->tickets->download($attachment);
    }
}
