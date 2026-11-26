<?php

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Optional Erty modules.
 *
 * A module is enabled for a business only when:
 *  1. the installation switch (a `site_sections` row keyed by the module) is on, and
 *  2. the owning business has not switched it off (`businesses.modules` JSON).
 *
 * Missing rows/columns mean "on", so existing installations keep their current behaviour.
 */
class ModuleService
{
    public const LISTINGS = 'listings';
    public const SALES = 'sales';

    public const MODULES = [
        self::LISTINGS => [
            'label' => 'Listings',
            'description' => 'One listing per unit type of a property, with rent, deposit and photos, published to renters through the public listings API.',
            // Business owners may switch this module off for their own account.
            'business_managed' => true,
        ],
        self::SALES => [
            'label' => 'Sales',
            'description' => 'Sell houses: buyer, price, deposit and instalment plan, payments with receipts, sales commission and completion.',
            'business_managed' => true,
            'off_hint' => 'Turning this off hides Sales. Houses can still be marked for sale, but no sale can be started.',
        ],
    ];

    /** @var array<int, array<string, bool>> per-request memo of business overrides */
    private array $businessOverrides = [];

    public function enabled(string $module, ?User $user = null): bool
    {
        if (!$this->installationEnabled($module)) {
            return false;
        }

        $user ??= auth()->user();
        if (!$user) {
            return true;
        }

        return $this->businessOverride($this->ownerId($user), $module) ?? true;
    }

    public function disabled(string $module, ?User $user = null): bool
    {
        return !$this->enabled($module, $user);
    }

    public function installationEnabled(string $module): bool
    {
        $this->assertKnown($module);

        return Cache::remember($this->cacheKey($module), 60, function () use ($module) {
            try {
                $value = DB::table('site_sections')->where('key', $module)->value('is_enabled');
            } catch (\Throwable $e) {
                return true;
            }

            return $value === null ? true : (bool) $value;
        });
    }

    /** null = no override (follow the installation switch). */
    public function businessOverride(int $ownerId, string $module): ?bool
    {
        $this->assertKnown($module);

        if (!array_key_exists($ownerId, $this->businessOverrides)) {
            try {
                $raw = DB::table('businesses')->where('user_id', $ownerId)->value('modules');
            } catch (\Throwable $e) {
                $raw = null;
            }
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $this->businessOverrides[$ownerId] = is_array($decoded) ? array_map('boolval', $decoded) : [];
        }

        return $this->businessOverrides[$ownerId][$module] ?? null;
    }

    public function setInstallation(string $module, bool $enabled): void
    {
        $this->assertKnown($module);

        DB::table('site_sections')->updateOrInsert(
            ['key' => $module],
            [
                'label' => self::MODULES[$module]['label'],
                'description' => self::MODULES[$module]['description'],
                'is_enabled' => $enabled,
                'updated_at' => now(),
            ]
        );
        DB::table('site_sections')->where('key', $module)->whereNull('created_at')->update(['created_at' => now()]);

        Cache::forget($this->cacheKey($module));
        Cache::forget("site_section:{$module}");
    }

    /** Pass null to remove the business override. */
    public function setForBusiness(int $ownerId, string $module, ?bool $enabled): void
    {
        $this->assertKnown($module);

        $raw = DB::table('businesses')->where('user_id', $ownerId)->value('modules');
        $modules = is_string($raw) ? (json_decode($raw, true) ?: []) : [];

        if ($enabled === null) {
            unset($modules[$module]);
        } else {
            $modules[$module] = $enabled;
        }

        DB::table('businesses')->where('user_id', $ownerId)->update([
            'modules' => $modules ? json_encode($modules) : null,
            'updated_at' => now(),
        ]);

        unset($this->businessOverrides[$ownerId]);
    }

    /**
     * Owner user IDs whose business has explicitly switched a module off.
     * Used to keep those businesses out of public (guest) listing queries.
     */
    public function ownersWithModuleOff(string $module): array
    {
        $this->assertKnown($module);

        try {
            return DB::table('businesses')->whereNotNull('modules')->get(['user_id', 'modules'])
                ->filter(function ($row) use ($module) {
                    $modules = is_string($row->modules) ? json_decode($row->modules, true) : (array) $row->modules;
                    return is_array($modules) && array_key_exists($module, $modules) && !$modules[$module];
                })
                ->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function ownerId(User $user): int
    {
        return (int) $user->businessOwnerId();
    }

    public function isKnown(string $module): bool
    {
        return array_key_exists($module, self::MODULES);
    }

    private function assertKnown(string $module): void
    {
        if (!$this->isKnown($module)) {
            throw new \InvalidArgumentException("Unknown module [{$module}].");
        }
    }

    private function cacheKey(string $module): string
    {
        return "erty_module:{$module}";
    }
}
