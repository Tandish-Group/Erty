<?php

namespace App\Console\Commands;

use App\Services\Access\StarterRoles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ErtySeedRoles extends Command
{
    protected $signature = 'erty:seed-roles {--business= : Owner user ID; omit for every business}';

    protected $description = 'Create any standard starter roles a business has not been offered yet (see App\\Services\\Access\\StarterRoles)';

    public function handle(StarterRoles $roles): int
    {
        $owners = $this->option('business')
            ? collect([(int) $this->option('business')])
            : DB::table('businesses')->orderBy('user_id')->pluck('user_id');

        $total = 0;
        foreach ($owners as $ownerId) {
            $created = $roles->ensureFor((int) $ownerId);
            $total += $created;
            $this->line("Business owner {$ownerId}: {$created} roles created");
        }
        $this->info("Done. {$total} roles created.");
        return self::SUCCESS;
    }
}
