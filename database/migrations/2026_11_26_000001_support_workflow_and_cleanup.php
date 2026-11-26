<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Brings installations made before 2026-11-26 up to the current baseline:
 * - support tickets get a reference, status, priority, assignee and activity dates, plus replies and an
 *   attachments table (files already uploaded are recorded there); deleting a topic no longer deletes tickets;
 * - the unused email_logs and identification_cards tables are dropped;
 * - the Administrator starter role's description no longer mentions plans and billing.
 * On a fresh installation the baseline already has all of this, and every step here is skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->upgradeTickets();
        $this->createReplyTables();
        $this->recordExistingAttachments();
        $this->dropOldTicketColumns();

        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('identification_cards');

        if (Schema::hasTable('business_roles')) {
            DB::table('business_roles')
                ->where('name', 'Administrator')
                ->where('description', 'like', '%Plans and billing stay with the account owner.%')
                ->update(['description' => 'Runs the business day to day: every section and action, including team and roles. Modules, payment methods and deleting the account stay with the account owner.']);
        }
    }

    public function down(): void
    {
        // Not reversible: restore the backup taken before upgrading.
    }

    private function upgradeTickets(): void
    {
        $columns = [
            'reference' => fn (Blueprint $t) => $t->string('reference', 32)->nullable()->unique(),
            'status' => fn (Blueprint $t) => $t->string('status', 20)->default('open'),
            'priority' => fn (Blueprint $t) => $t->string('priority', 10)->default('normal'),
            'assigned_to' => fn (Blueprint $t) => $t->bigInteger('assigned_to')->nullable(),
            'last_activity_at' => fn (Blueprint $t) => $t->timestamp('last_activity_at')->nullable(),
            'resolved_at' => fn (Blueprint $t) => $t->timestamp('resolved_at')->nullable(),
            'closed_at' => fn (Blueprint $t) => $t->timestamp('closed_at')->nullable(),
        ];
        foreach ($columns as $column => $add) {
            if (!Schema::hasColumn('support_tickets', $column)) {
                Schema::table('support_tickets', $add);
            }
        }

        $indexes = array_column(Schema::getIndexes('support_tickets'), 'name');
        Schema::table('support_tickets', function (Blueprint $t) use ($indexes) {
            if (!in_array('support_tickets_status_last_activity_at_index', $indexes, true)) {
                $t->index(['status', 'last_activity_at']);
            }
            if (!in_array('support_tickets_user_id_index', $indexes, true)) {
                $t->index('user_id');
            }
        });

        foreach (DB::table('support_tickets')->whereNull('reference')->orderBy('id')->get(['id', 'created_at', 'updated_at']) as $ticket) {
            $created = $ticket->created_at ? strtotime($ticket->created_at) : time();
            DB::table('support_tickets')->where('id', $ticket->id)->update([
                'reference' => sprintf('SUP-%s-%04d', date('ym', $created), $ticket->id),
                'last_activity_at' => $ticket->updated_at ?? $ticket->created_at,
            ]);
        }

        // Deleting a topic keeps its tickets; tickets can be assigned to an administrator.
        $keys = collect(Schema::getForeignKeys('support_tickets'))->keyBy('name');
        $topicKey = $keys->get('support_tickets_support_id_foreign');
        if (!$topicKey || strtolower((string) $topicKey['on_delete']) !== 'set null') {
            Schema::table('support_tickets', function (Blueprint $t) use ($topicKey) {
                if ($topicKey) {
                    $t->dropForeign('support_tickets_support_id_foreign');
                }
            });
            Schema::table('support_tickets', function (Blueprint $t) {
                $t->foreign(['support_id'], 'support_tickets_support_id_foreign')->references(['id'])->on('supports')->onDelete('set null');
            });
        }
        if (!$keys->has('support_tickets_assigned_to_foreign')) {
            Schema::table('support_tickets', function (Blueprint $t) {
                $t->foreign(['assigned_to'], 'support_tickets_assigned_to_foreign')->references(['id'])->on('users')->onDelete('set null');
            });
        }
    }

    private function createReplyTables(): void
    {
        if (!Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('ticket_id');
                $table->bigInteger('user_id')->nullable();
                $table->text('body')->nullable();
                $table->boolean('is_staff')->default(false);
                $table->string('status_from', 20)->nullable();
                $table->string('status_to', 20)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('ticket_id');
                $table->foreign(['ticket_id'], 'support_ticket_messages_ticket_id_foreign')->references(['id'])->on('support_tickets')->onDelete('cascade');
                $table->foreign(['user_id'], 'support_ticket_messages_user_id_foreign')->references(['id'])->on('users')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('support_ticket_attachments')) {
            Schema::create('support_ticket_attachments', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('ticket_id');
                $table->bigInteger('message_id')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->string('disk', 20)->default('local');
                $table->string('path');
                $table->string('original_name');
                $table->unsignedBigInteger('size')->default(0);
                $table->string('mime', 120)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('ticket_id');
                $table->foreign(['ticket_id'], 'support_ticket_attachments_ticket_id_foreign')->references(['id'])->on('support_tickets')->onDelete('cascade');
                $table->foreign(['message_id'], 'support_ticket_attachments_message_id_foreign')->references(['id'])->on('support_ticket_messages')->onDelete('cascade');
            });
        }
    }

    /** Files uploaded before attachments were recorded sit in the ticket's media_path folder. */
    private function recordExistingAttachments(): void
    {
        if (!Schema::hasColumn('support_tickets', 'media_path')) {
            return;
        }

        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);
        foreach (DB::table('support_tickets')->whereNotNull('media_path')->get(['id', 'user_id', 'media_path', 'created_at']) as $ticket) {
            foreach ($disk->files($ticket->media_path) as $path) {
                if (DB::table('support_ticket_attachments')->where('ticket_id', $ticket->id)->where('path', $path)->exists()) {
                    continue;
                }
                DB::table('support_ticket_attachments')->insert([
                    'ticket_id' => $ticket->id,
                    'user_id' => $ticket->user_id,
                    'disk' => $diskName,
                    'path' => $path,
                    'original_name' => basename($path),
                    'size' => $disk->size($path),
                    'mime' => $disk->mimeType($path) ?: null,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->created_at,
                ]);
            }
        }
    }

    private function dropOldTicketColumns(): void
    {
        foreach (['media_path', 'has_attachments', 'custom_user_id'] as $column) {
            if (Schema::hasColumn('support_tickets', $column)) {
                Schema::table('support_tickets', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
