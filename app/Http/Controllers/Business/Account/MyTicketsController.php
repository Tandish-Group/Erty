<?php

namespace App\Http\Controllers\Business\Account;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\Request;

/** "My support tickets": the tickets a person has posted, the replies on them, and replying back. */
class MyTicketsController extends Controller
{
    public function __construct(private SupportTicketService $tickets)
    {
    }

    public function index(Request $request)
    {
        $tickets = SupportTicket::with('topic')
            ->where('user_id', $request->user()->id)
            ->withCount(['messages as staff_replies_count' => fn ($q) => $q->where('is_staff', true)->whereNotNull('body')])
            ->orderByRaw("CASE WHEN status IN ('open','in_progress','waiting_on_user') THEN 0 ELSE 1 END")
            ->orderByDesc('last_activity_at')
            ->paginate(20);

        return view('pages.business.support.index', ['tickets' => $tickets]);
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        $this->own($request, $ticket);
        $ticket->load(['topic', 'messages.user', 'attachments']);

        return view('pages.business.support.show', [
            'ticket' => $ticket,
            'fileRules' => config('erty.support.attachments'),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->own($request, $ticket);
        $data = $request->validate([
            'body' => 'nullable|string|max:20000',
            'files' => 'nullable|array',
        ]);

        $this->tickets->reply($ticket, $request->user(), $data['body'] ?? null, false, null, $request->file('files', []));

        return redirect()->route('support.tickets.show', $ticket->reference)->with('success', 'Your reply was sent.');
    }

    public function file(Request $request, SupportTicket $ticket, SupportTicketAttachment $attachment)
    {
        $this->own($request, $ticket);
        abort_unless((int) $attachment->ticket_id === (int) $ticket->id, 404);

        return $this->tickets->download($attachment);
    }

    /** People only ever see their own tickets; anyone else's is "not found". */
    private function own(Request $request, SupportTicket $ticket): void
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 404);
    }
}
