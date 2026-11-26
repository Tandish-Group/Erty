<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessTenantBilling;
use Illuminate\Support\Facades\Log;

class ProcessTenantBillingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:process-billing {--async : Run the job asynchronously} {--date= : Run as of this date (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process daily tenant billing cycles for all active tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting tenant billing process...');

        try {
            if ($this->option('async')) {
                // Dispatch the job to the queue
                ProcessTenantBilling::dispatch();
                $this->info('✅ Tenant billing job dispatched to queue successfully!');
                Log::info('ProcessTenantBilling job dispatched via command');
            } else {
                // Run the job synchronously
                $job = new ProcessTenantBilling($this->option('date') ?: null);
                $stats = $job->handle();
                $this->line(json_encode($stats));
                $this->info('✅ Tenant billing process completed successfully!');
                Log::info('ProcessTenantBilling job executed synchronously via command');
            }
        } catch (\Exception $e) {
            $this->error('❌ Failed to process tenant billing: ' . $e->getMessage());
            Log::error('ProcessTenantBilling command failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}