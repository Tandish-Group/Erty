<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The activity log: who opened, changed or downloaded what in the business app, and when, plus sign-ins.
 * Form contents are never stored. Old entries are removed by `activity:prune` (config/erty.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('business_id')->nullable();   // the business owner's user id
            $table->bigInteger('user_id')->nullable();
            $table->string('event', 20);                       // viewed, created, changed, deleted, downloaded, refused, signed_in, signed_out, sign_in_failed
            $table->string('area', 40)->nullable();            // permission group, e.g. tenant_management
            $table->string('action', 30)->nullable();          // permission action, e.g. payments
            $table->string('route', 120)->nullable();
            $table->string('method', 8)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('subject', 255)->nullable();        // the record it was about, from the address
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['business_id', 'area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
