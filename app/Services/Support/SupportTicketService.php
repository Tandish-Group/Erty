<?php

namespace App\Services\Support;

use App\Mail\SupportTicketMail;
use App\Models\Support;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\NotificationCenter;
use App\Support\SafeHtml;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Support tickets: posting, replies, status changes and files.
 *
 * - Anyone signed in can post a ticket and see their own tickets; the installation's platform
 *   administrators see and answer every ticket.
 * - An administrator's reply sets the ticket to "waiting on user" unless they choose another status.
 *   A reply from the poster sends a waiting or resolved ticket back to "open". Closed tickets take no replies.
 * - Files go to the private local disk and are counted against the per-ticket limit.
 */
class SupportTicketService
{
    public function __construct(private NotificationCenter $notify)
    {
    }

    public function canView(User $user, SupportTicket $ticket): bool
    {
        return (int) $ticket->user_id === (int) $user->id || $user->answersSupport();
    }

    public function open(User $user, int $topicId, string $description, ?string $customTopic = null): SupportTicket
    {
        $topic = Support::findOrFail($topicId);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'support_id' => $topic->id,
            'custom_topic' => $topic->isOther() && trim((string) $customTopic) !== '' ? Str::limit(trim($customTopic), 250, '') : null,
            'description' => SafeHtml::clean($description),
        ]);
        $ticket->refresh();

        $this->notify->supportTicketOpened($ticket);
        $this->email(
            $this->notify->supportAgents()->pluck('email')->push(config('app.support_email'))->filter()->unique()->values()->all(),
            new SupportTicketMail($ticket, 'opened', null, route('platform.support.show', $ticket->reference))
        );

        return $ticket;
    }

    /**
     * A reply from the poster ($asStaff false) or an administrator. An administrator may also set the status;
     * a reply with only a status change and no text is allowed for administrators.
     */
    public function reply(SupportTicket $ticket, User $by, ?string $body, bool $asStaff, ?string $status = null, array $files = []): SupportTicketMessage
    {
        $body = SafeHtml::fromText($body); // replies come from plain text boxes
        if ($ticket->isClosed() && !$asStaff) {
            throw ValidationException::withMessages(['body' => 'This ticket is closed. Post a new ticket if you still need help.']);
        }
        if (!$asStaff && strip_tags($body) === '' && !$files) {
            throw ValidationException::withMessages(['body' => 'Write a reply or add a file.']);
        }
        if ($status !== null && !array_key_exists($status, SupportTicket::STATUSES)) {
            throw ValidationException::withMessages(['status' => 'Choose a valid status.']);
        }

        $to = $asStaff
            ? ($status ?? (strip_tags($body) !== '' ? 'waiting_on_user' : $ticket->status))
            : (in_array($ticket->status, ['waiting_on_user', 'resolved'], true) ? 'open' : $ticket->status);

        $message = DB::transaction(function () use ($ticket, $by, $body, $asStaff, $to, $files) {
            $from = $ticket->status !== $to ? $ticket->moveTo($to) : null;
            $ticket->last_activity_at = now();
            $ticket->save();

            $message = $ticket->messages()->create([
                'user_id' => $by->id,
                'body' => $body !== '' ? $body : null,
                'is_staff' => $asStaff,
                'status_from' => $from,
                'status_to' => $from !== null ? $to : null,
            ]);
            if ($files) {
                $this->attach($ticket, $files, $by, $message);
            }

            return $message;
        });

        $this->announce($ticket, $message);

        return $message;
    }

    /** Administrator changes: status, priority and who handles the ticket. */
    public function update(SupportTicket $ticket, User $by, array $changes): ?SupportTicketMessage
    {
        $status = $changes['status'] ?? $ticket->status;
        $message = DB::transaction(function () use ($ticket, $by, $changes, $status) {
            $ticket->fill(array_intersect_key($changes, array_flip(['priority', 'assigned_to'])));
            $from = $status !== $ticket->status ? $ticket->moveTo($status) : null;
            $ticket->last_activity_at = now();
            $ticket->save();

            return $from === null ? null : $ticket->messages()->create([
                'user_id' => $by->id, 'is_staff' => true, 'status_from' => $from, 'status_to' => $status,
            ]);
        });

        if ($message) {
            $this->announce($ticket, $message);
        }

        return $message;
    }

    /**
     * Store files on the ticket (or one of its replies). Rejects the whole batch when a file is not allowed
     * or the ticket would go over its file limit.
     *
     * @param  UploadedFile[]  $files
     * @return SupportTicketAttachment[]
     */
    public function attach(SupportTicket $ticket, array $files, User $by, ?SupportTicketMessage $message = null): array
    {
        $rules = config('erty.support.attachments');
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        $already = $ticket->attachments()->count();
        if ($already + count($files) > $rules['per_ticket']) {
            throw ValidationException::withMessages(['files' => 'A ticket can have up to ' . $rules['per_ticket'] . ' files' . ($already ? " ({$already} added already)" : '') . '.']);
        }
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            if (!$file->isValid() || !in_array($extension, $rules['extensions'], true) || $file->getSize() > $rules['max_kb'] * 1024) {
                throw ValidationException::withMessages(['files' => 'Add ' . strtoupper(implode(', ', $rules['extensions'])) . ' files of up to ' . round($rules['max_kb'] / 1024) . ' MB.']);
            }
        }

        $saved = [];
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs('support/' . $ticket->id, Str::uuid() . '.' . $extension, 'local');
            $saved[] = $ticket->attachments()->create([
                'message_id' => $message?->id,
                'user_id' => $by->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'size' => (int) $file->getSize(),
                'mime' => $file->getMimeType(),
            ]);
        }

        return $saved;
    }

    public function download(SupportTicketAttachment $attachment)
    {
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }

    private function announce(SupportTicket $ticket, SupportTicketMessage $message): void
    {
        $ticket->loadMissing('user', 'assignee');
        $this->notify->supportTicketUpdated($ticket, $message);
        if ($message->is_staff && $ticket->user?->email) {
            $this->email([$ticket->user->email], new SupportTicketMail($ticket, 'updated', $message, route('support.tickets.show', $ticket->reference)));
        }
    }

    /** Email is a courtesy: a mail failure never undoes the ticket change. */
    private function email(array $to, SupportTicketMail $mail): void
    {
        if (!$to) {
            return;
        }
        $send = function () use ($to, $mail) {
            try {
                Mail::to($to)->send($mail);
            } catch (\Throwable $e) {
                Log::warning('Support email not sent: ' . $e->getMessage());
            }
        };
        DB::transactionLevel() > 0 ? DB::afterCommit($send) : $send();
    }
}
