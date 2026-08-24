<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Idempotent: creates/resets a Field Executive for mobile app testing.
 * Run on production after deploy if the APK has no working login:
 *   php artisan seas:ensure-mobile-demo-user
 */
class EnsureMobileDemoUser extends Command
{
    protected $signature = 'seas:ensure-mobile-demo-user
                            {--email=surveyor@seas.test : Login email}
                            {--password=password : Plain password (hashed by User cast)}
                            {--name=Field Executive Anuj : Display name}';

    protected $description = 'Create or reset a Field Executive user for SEAS mobile app login';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');
        $name = (string) $this->option('name');

        $manager = User::query()
            ->whereIn('role', [User::ROLE_MANAGER, User::ROLE_PROJECT_MANAGER])
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'role' => User::ROLE_FIELD_EXECUTIVE,
                'supervisor_id' => $manager?->id,
                'email_verified_at' => now(),
                'is_active' => true,
                'force_password_change' => false,
            ]
        );

        $this->info("Mobile FE ready: {$user->email} / {$password} (role={$user->role}, active=".($user->is_active ? 'yes' : 'no').')');
        $this->line('Sign in on the release APK against https://mrhari.co.in/api — not local admin.');

        return self::SUCCESS;
    }
}
