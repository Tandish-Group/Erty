<?php

namespace App\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\SendTenantWelcomeEmailMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendTenantWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenantEmail;
    protected $tenantName;
    protected $tenantProfile;
    protected $complex;
    protected $attachments;
    protected $business_name;

    /**
     * Create a new job instance.
     */
    public function __construct($tenantEmail, $tenantName, $tenantProfile, $complex, $attachments, $business_name)
    {
        Log::info("Job dispatched for tenant: {$tenantEmail}");

        $this->tenantEmail = $tenantEmail;
        $this->tenantName = $tenantName;
        $this->tenantProfile = $tenantProfile;
        $this->complex = $complex;
        $this->attachments = $attachments;
        $this->business_name = $business_name;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Processing welcome email job for tenant: {$this->tenantEmail}");
            
            $mail = new SendTenantWelcomeEmailMail(
                $this->tenantEmail,
                $this->tenantName,
                $this->tenantProfile,
                $this->complex,
                $this->attachments,
                $this->business_name
            );

            Mail::to($this->tenantEmail)
                ->send($mail);

        } catch (\Exception $e) {
            Log::error("Failed to process welcome email job: " . $e->getMessage());
            throw $e; // Re-throw to mark the job as failed
        }
        
        
    }
}