<?php

namespace App\Providers;

use App\Models\Lead;
use App\Observers\LeadObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        \App\Events\LeadPhaseAdvanced::class => [
            \App\Listeners\CreatePhaseTasksListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        // A team member who sets their password from an invitation has accepted it.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\PasswordReset::class, function ($event) {
            if ($event->user->invited_at && !$event->user->invitation_accepted_at) {
                $event->user->forceFill(['invitation_accepted_at' => now()])->save();
            }
        });

        // Sign-ins for the activity log (failed sign-ins are logged by the sign-in and code controllers).
        $activity = fn () => app(\App\Services\Access\ActivityLogger::class);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, fn ($e) => $activity()->auth('signed_in', $e->user));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, fn ($e) => $e->user ? $activity()->auth('signed_out', $e->user) : null);

        parent::boot();
        Lead::observe(LeadObserver::class);
    }
}
