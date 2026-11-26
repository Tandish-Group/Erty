<?php

namespace Tests\Unit;

use App\Models\BusinessRole;
use App\Models\User;
use App\Services\Access\ModuleService;
use App\Services\Access\StarterRoles;
use App\Support\HelpFaq;
use Tests\TestCase;

/** The Help & FAQ page shows each person the questions about what they can open, and links to questions stay unique. */
class HelpFaqTest extends TestCase
{
    private function modules(array $off = []): ModuleService
    {
        return new class($off) extends ModuleService {
            public function __construct(private array $off) {}
            public function enabled(string $module, ?User $user = null): bool { return !in_array($module, $this->off, true); }
        };
    }

    private function owner(): User
    {
        return new class extends User {
            public function isBusinessOwner(): bool { return true; }
            public function hasBusinessPermission(string $group, string $action = 'view'): bool { return true; }
        };
    }

    /** A team member with a starter role's permissions. */
    private function member(string $roleName): User
    {
        [, , $spec] = StarterRoles::definitions()[$roleName];
        $granted = StarterRoles::permissions($spec);

        $user = new class extends User {
            public array $granted = [];
            public function isBusinessOwner(): bool { return false; }
            public function hasBusinessPermission(string $group, string $action = 'view'): bool { return in_array($action, $this->granted[$group] ?? [], true); }
        };
        $user->granted = $granted;
        $user->setRelation('businessRole', new BusinessRole(['name' => $roleName]));

        return $user;
    }

    private function keys(array $sections): array
    {
        return array_column($sections, 'key');
    }

    private function ids(array $sections): array
    {
        return array_merge(...array_map(fn ($s) => array_column($s['items'], 'id'), $sections));
    }

    public function test_the_owner_sees_every_section_and_the_owner_only_questions(): void
    {
        $sections = (new HelpFaq($this->modules()))->sectionsFor($this->owner());

        $this->assertSame(
            ['getting-started', 'properties', 'listings', 'tenants', 'rent', 'agents', 'crm', 'maintenance', 'sales', 'legal', 'reports', 'account'],
            $this->keys($sections)
        );
        $this->assertContains('account-team', $this->ids($sections));
        $this->assertContains('account-modules', $this->ids($sections));
    }

    public function test_switched_off_modules_drop_their_sections(): void
    {
        $sections = (new HelpFaq($this->modules([ModuleService::LISTINGS, ModuleService::SALES])))->sectionsFor($this->owner());

        $this->assertNotContains('listings', $this->keys($sections));
        $this->assertNotContains('sales', $this->keys($sections));
        $this->assertContains('tenants', $this->keys($sections));
    }

    public function test_a_caretaker_sees_maintenance_and_tenants_but_not_money_agents_or_team(): void
    {
        $sections = (new HelpFaq($this->modules()))->sectionsFor($this->member('Caretaker'));
        $keys = $this->keys($sections);

        foreach (['getting-started', 'properties', 'tenants', 'maintenance', 'account'] as $key) {
            $this->assertContains($key, $keys);
        }
        foreach (['rent', 'agents', 'sales', 'legal', 'listings'] as $key) {
            $this->assertNotContains($key, $keys);
        }
        $this->assertNotContains('account-team', $this->ids($sections), 'Team questions are for the owner');
        $this->assertNotContains('account-modules', $this->ids($sections));
    }

    public function test_a_finance_officer_sees_rent_and_payments(): void
    {
        $keys = $this->keys((new HelpFaq($this->modules()))->sectionsFor($this->member('Finance Officer')));

        $this->assertContains('rent', $keys);
    }

    public function test_every_question_has_a_unique_link_id_that_does_not_clash_with_a_section(): void
    {
        $catalog = (new HelpFaq($this->modules()))->catalog();
        $ids = array_merge(...array_map(fn ($s) => array_column($s['items'], 'id'), $catalog));

        $this->assertSame(count($ids), count(array_unique($ids)), 'Question ids must be unique');
        $this->assertEmpty(array_intersect($ids, array_column($catalog, 'key')));
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $id);
        }
    }
}
