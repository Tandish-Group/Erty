<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendTenantWelcomeEmailMail extends Mailable
{
    use Queueable, SerializesModels;
    
    protected $tenantEmail;
    protected $tenantName;
    protected $tenantProfile;
    protected $complex;
    protected $files;
    protected $business_name;

    
    /**
     * Create a new message instance.
     */
    public function __construct($tenantEmail, $tenantName, $tenantProfile, $complex, $files, $business_name)
    {
        $this->tenantEmail = $tenantEmail;
        $this->tenantName = $tenantName;
        $this->tenantProfile = $tenantProfile;
        $this->complex = $complex;
        $this->files = $files;
        $this->business_name = $business_name;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $email = $this->view('emails.tenants.welcome_tenant')
            ->subject('Welcome to ' . $this->complex->title)
            ->with([
                'tenantName' => $this->tenantName,
                'complexName' => $this->complex->title,
                'businessName' => $this->business_name,
            ]);

        // Attach files
        foreach ($this->files as $file) {
            $email->attach(storage_path('app/public/' . $file));
        }

        return $email;
    }
}
