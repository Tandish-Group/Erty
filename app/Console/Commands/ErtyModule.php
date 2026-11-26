<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Access\ModuleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ErtyModule extends Command
{
    protected $signature = 'erty:module
        {module? : listings|sales (omit to show status)}
        {state? : on|off|clear (clear removes a business override)}
        {--business= : Business owner email or user ID; omit to change the installation switch}';

    protected $description = 'Show or switch Erty optional modules for the installation or a single business';

    public function handle(ModuleService $modules): int
    {
        $module = $this->argument('module');
        $state = $this->argument('state');
        $business = $this->option('business');

        if (!$module) {
            return $this->status($modules, $business);
        }

        if (!$modules->isKnown($module)) {
            $this->error("Unknown module [{$module}]. Use: " . implode(', ', array_keys(ModuleService::MODULES)));
            return self::FAILURE;
        }

        if (!in_array($state, ['on', 'off', 'clear'], true)) {
            $this->error('State must be on, off or clear.');
            return self::FAILURE;
        }

        if ($business === null) {
            if ($state === 'clear') {
                $this->error('clear only applies to a business override (use --business).');
                return self::FAILURE;
            }
            $modules->setInstallation($module, $state === 'on');
            $this->info(ModuleService::MODULES[$module]['label'] . ' module switched ' . strtoupper($state) . ' for the installation.');
            return self::SUCCESS;
        }

        $owner = $this->resolveOwner($business);
        if (!$owner) {
            $this->error("No business owner found for [{$business}].");
            return self::FAILURE;
        }
        if (!DB::table('businesses')->where('user_id', $owner->id)->exists()) {
            $this->error("User {$owner->id} has no business record.");
            return self::FAILURE;
        }

        $modules->setForBusiness($owner->id, $module, $state === 'clear' ? null : $state === 'on');
        $label = ModuleService::MODULES[$module]['label'];
        $this->info($state === 'clear'
            ? "{$label} override cleared for {$owner->email}; the installation switch applies."
            : "{$label} module switched " . strtoupper($state) . " for {$owner->email}.");

        if (!$modules->installationEnabled($module) && $state === 'on') {
            $this->warn("The installation switch is OFF, so {$label} stays off for this business until it is switched on.");
        }

        return self::SUCCESS;
    }

    private function status(ModuleService $modules, ?string $business): int
    {
        $owner = $business ? $this->resolveOwner($business) : null;
        if ($business && !$owner) {
            $this->error("No business owner found for [{$business}].");
            return self::FAILURE;
        }

        $rows = [];
        foreach (ModuleService::MODULES as $key => $definition) {
            $row = [$key, $modules->installationEnabled($key) ? 'on' : 'off'];
            if ($owner) {
                $override = $modules->businessOverride($owner->id, $key);
                $row[] = $override === null ? '—' : ($override ? 'on' : 'off');
                $row[] = $modules->enabled($key, $owner) ? 'on' : 'off';
            }
            $rows[] = $row;
        }

        $this->table($owner ? ['Module', 'Installation', 'Business override', 'Effective'] : ['Module', 'Installation'], $rows);
        return self::SUCCESS;
    }

    private function resolveOwner(string $value): ?User
    {
        $user = ctype_digit($value) ? User::find((int) $value) : User::where('email', $value)->first();
        if ($user && $user->is_sub_account) {
            $user = User::find($user->main_account_user_id);
        }
        return $user;
    }
}
