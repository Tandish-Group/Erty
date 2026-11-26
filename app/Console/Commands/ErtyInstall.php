<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Access\StarterRoles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sets up a new Erty installation, and is safe to run again:
 *  1. an application key when there is none;
 *  2. the database tables (migrations) and the reference data every installation needs (seeders);
 *  3. the public storage link for uploaded files;
 *  4. the first administrator, who owns the business and holds platform admin, unless a business exists.
 *
 * The administrator's password is random, shown once, and must be changed at the first sign-in.
 */
class ErtyInstall extends Command
{
    protected $signature = 'erty:install
        {--email= : The first administrator\'s email (default: ADMIN_EMAIL)}
        {--business= : The business name (default: BUSINESS_NAME, then the app name)}
        {--force : Do not ask for confirmation in production}';

    protected $description = 'Set up Erty: database, reference data, storage link and the first administrator';

    public function handle(): int
    {
        if (app()->environment('production') && !$this->option('force')
            && !$this->confirm('This will migrate the production database. Continue?')) {
            return self::FAILURE;
        }

        if (!config('app.key')) {
            $this->call('key:generate', ['--force' => true]);
        }

        $this->components->info('Database');
        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--force' => true]);

        if (!File::exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        return $this->createAdministrator();
    }

    private function createAdministrator(): int
    {
        if (DB::table('businesses')->exists()) {
            $this->components->info('A business already exists, so no administrator was created. Erty is installed.');
            return self::SUCCESS;
        }

        $email = mb_strtolower(trim((string) ($this->option('email') ?: config('erty.admin_email'))));
        if ($email === '' && !app()->environment('production')) {
            $email = (string) $this->ask('Email for the first administrator (sign-in sends a code here)');
            $email = mb_strtolower(trim($email));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Set ADMIN_EMAIL (or pass --email) to a mailbox you can read: sign-in sends a code there.');
            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error("{$email} already has an account.");
            return self::FAILURE;
        }

        $password = Str::password(20);
        $businessName = (string) ($this->option('business') ?: config('erty.business_name') ?: config('app.name'));

        DB::transaction(function () use ($email, $password, $businessName) {
            $user = User::forceCreate([
                'first_name' => 'Erty',
                'last_name' => 'Administrator',
                'full_name_slug' => 'erty-administrator-' . Str::lower(Str::random(6)),
                'email' => $email,
                'mobile' => 0, // set in My profile
                'account_type' => 'Business',
                'password' => Hash::make($password),
                'must_change_password' => true,
                'is_sub_account' => false,
            ]);

            DB::table('businesses')->insert([
                'user_id' => $user->id,
                'business_name' => $businessName,
                'business_email' => $email,
                'account_type' => 'company',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (['business', 'admin'] as $role) {
                $user->assignRole(Role::findOrCreate($role, 'web'));
            }
            app(StarterRoles::class)->ensureFor($user->id);
        });

        $this->newLine();
        $this->components->warn('First administrator created. This password is shown only once:');
        $this->line("  Email:     {$email}");
        $this->line("  Password:  {$password}");
        $this->line("  Business:  {$businessName}");
        $this->newLine();
        $this->line('Sign in at ' . url('/login') . ', enter the code emailed to you, then choose your own password.');

        return self::SUCCESS;
    }
}
