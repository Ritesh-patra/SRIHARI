<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent demo users only — no dummy Bhopal hierarchy / surveys.
        $password = 'password';

        User::query()->updateOrCreate(
            ['email' => 'super@seas.test'],
            [
                'name' => 'Super Admin',
                'password' => $password,
                'role' => User::ROLE_SUPER_ADMIN,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@seas.test'],
            [
                'name' => 'Admin SEAS',
                'password' => $password,
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'pm@seas.test'],
            [
                'name' => 'Project Manager Neha',
                'password' => $password,
                'role' => User::ROLE_PROJECT_MANAGER,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $manager = User::query()->updateOrCreate(
            ['email' => 'manager@seas.test'],
            [
                'name' => 'Manager Raj',
                'password' => $password,
                'role' => User::ROLE_MANAGER,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'supervisor@seas.test'],
            [
                'name' => 'Supervisor Raj (alias)',
                'password' => $password,
                'role' => User::ROLE_MANAGER,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'surveyor@seas.test'],
            [
                'name' => 'Field Executive Anuj',
                'password' => $password,
                'role' => User::ROLE_FIELD_EXECUTIVE,
                'supervisor_id' => $manager->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'fe@seas.test'],
            [
                'name' => 'Field Executive Riya',
                'password' => $password,
                'role' => User::ROLE_FIELD_EXECUTIVE,
                'supervisor_id' => $manager->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $xlsx = storage_path('app/imports/Sample_Data.xlsx');
        $hasJson = is_file(storage_path('app/imports/feeders.json'))
            && is_file(storage_path('app/imports/dtrs.json'))
            && is_file(storage_path('app/imports/consumers.json'));

        if (is_file($xlsx) || $hasJson) {
            Artisan::call('seas:import-sample-data', [], $this->command?->getOutput());
        } else {
            $this->command?->warn('No Sample_Data.xlsx / import JSON found — skipped seas:import-sample-data.');
        }
    }
}
