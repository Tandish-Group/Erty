<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: Users, sign-in and framework tables.
 * Generated from the schema of a running installation; each table is skipped when it already exists,
 * so existing installations can run it safely. Foreign keys are added by the last baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('full_name_slug');
                $table->bigInteger('mobile');
                $table->string('email');
                $table->string('account_type');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('avatar')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->integer('incorrect_attempts')->default(0);
                $table->boolean('is_suspended')->default(false);
                $table->enum('verification_status', ['pending', 'verified', 'failed'])->default('pending');
                $table->string('verification_message')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->boolean('walkthrough_completed')->default(false);
                $table->timestamp('walkthrough_completed_at')->nullable();
                $table->boolean('delete_account')->default(false);
                $table->string('user_type')->nullable();
                $table->boolean('is_sub_account')->default(false);
                $table->bigInteger('main_account_user_id')->nullable();
                $table->bigInteger('business_role_id')->nullable();
                $table->string('cover_photo')->nullable();
                $table->json('business_permissions')->nullable();
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('invitation_accepted_at')->nullable();
                $table->bigInteger('invited_by')->nullable();
                $table->boolean('must_change_password')->default(false);
                $table->unique(['email'], 'users_email_unique');
                $table->index(['is_sub_account', 'main_account_user_id'], 'users_is_sub_account_main_account_user_id_index');
            });
        }

        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->string('email');
                $table->string('token');
                $table->timestamp('created_at')->nullable();
                $table->index(['email'], 'password_resets_email_index');
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id');
                $table->bigInteger('user_id')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('payload');
                $table->integer('last_activity');
                $table->unique(['id'], 'sessions_id_unique');
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue');
                $table->text('payload');
                $table->smallInteger('attempts');
                $table->integer('reserved_at')->nullable();
                $table->integer('available_at');
                $table->integer('created_at');
                $table->index(['queue'], 'jobs_queue_index');
            });
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->text('connection');
                $table->text('queue');
                $table->text('payload');
                $table->text('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id');
                $table->string('type');
                $table->string('notifiable_type');
                $table->bigInteger('notifiable_id');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->primary(['id'], 'notifications_pkey');
                $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
            });
        }

        if (!Schema::hasTable('otps')) {
            Schema::create('otps', function (Blueprint $table) {
                $table->id();
                $table->text('otp');
                $table->string('status');
                $table->timestamp('expiry');
                $table->bigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('blocked_ips')) {
            Schema::create('blocked_ips', function (Blueprint $table) {
                $table->id();
                $table->string('ip_address');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->text('path');
                $table->unique(['ip_address'], 'blocked_ips_ip_address_unique');
            });
        }

        if (!Schema::hasTable('supports')) {
            Schema::create('supports', function (Blueprint $table) {
                $table->id();
                $table->string('topic');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        // Support tickets: posted by team members, answered by the platform administrators.
        if (!Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->nullable()->unique(); // SUP-2611-0042, set once the ticket has an id
                $table->text('description');
                $table->bigInteger('support_id')->nullable();
                $table->string('custom_topic')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->string('status', 20)->default('open'); // open, in_progress, waiting_on_user, resolved, closed
                $table->string('priority', 10)->default('normal'); // low, normal, high, urgent
                $table->bigInteger('assigned_to')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['status', 'last_activity_at']);
                $table->index('user_id');
            });
        }

        // Replies on a ticket, and status changes (status_from/status_to) recorded with them.
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
            });
        }

        // Files added to a ticket or to one of its replies (kept on a private disk, downloaded through the app).
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
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('supports');
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('otps');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();
    }
};
