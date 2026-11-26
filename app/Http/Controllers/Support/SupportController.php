<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Support;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\Request;

/** The "Create ticket" modal: topics, posting a ticket and adding its files. */
class SupportController extends Controller
{
    public function __construct(private SupportTicketService $tickets)
    {
    }

    /** Topics for the modal, and the file rules the uploader enforces. */
    public function index()
    {
        $rules = config('erty.support.attachments');

        return response()->json([
            'status' => 200,
            'topics' => Support::orderBy('id')->get(['id', 'topic'])->map(fn (Support $t) => [
                'id' => $t->id, 'topic' => $t->topic, 'is_other' => $t->isOther(),
            ]),
            'attachments' => [
                'extensions' => array_map(fn ($e) => '.' . $e, $rules['extensions']),
                'max_bytes' => $rules['max_kb'] * 1024,
                'max_files' => $rules['per_ticket'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'values.support_description' => 'required|string|max:20000',
            'values.support_topic' => 'required|integer|exists:supports,id',
            'values.custom_topic' => 'nullable|string|max:255',
        ]);
        $values = $data['values'];
        if (trim(strip_tags($values['support_description'])) === '') {
            return response()->json(['status' => 422, 'message' => 'Describe what you need help with.'], 422);
        }

        $ticket = $this->tickets->open($request->user(), (int) $values['support_topic'], $values['support_description'], $values['custom_topic'] ?? null);

        return response()->json([
            'status' => 200,
            'message' => 'Your ticket ' . $ticket->reference . ' has been created. You will be notified when there is a reply.',
            'ticket_id' => $ticket->id,
            'reference' => $ticket->reference,
            'url' => route('support.tickets.show', $ticket->reference),
        ]);
    }

    /** Files for a ticket just posted from the modal (one request per file from the uploader). */
    public function uploadFiles(Request $request)
    {
        $ticketId = $request->input('ticket_id') ?? (json_decode((string) $request->input('metadata'), true)['ticket_id'] ?? null);
        // Only the person who posted the ticket can add files to it.
        $ticket = $ticketId ? SupportTicket::where('user_id', $request->user()->id)->find($ticketId) : null;
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }
        if ($ticket->isClosed()) {
            return response()->json(['success' => false, 'message' => 'This ticket is closed.'], 422);
        }

        $files = $request->file('files');
        $files = is_array($files) ? $files : array_filter([$files]);
        if (!$files) {
            return response()->json(['success' => false, 'message' => 'No files were uploaded.'], 422);
        }

        $saved = $this->tickets->attach($ticket, $files, $request->user());

        return response()->json([
            'success' => true,
            'message' => count($saved) . ' ' . (count($saved) === 1 ? 'file' : 'files') . ' added.',
            'files' => array_map(fn ($a) => ['name' => $a->original_name, 'size' => $a->size], $saved),
        ]);
    }
}
