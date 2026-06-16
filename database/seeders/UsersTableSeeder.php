<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Events\Registered;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin user already exists
        $existingAdmin = User::where('email', 'admin@clarimount.com')->first();
        
        if ($existingAdmin) {
            $this->command->info('Admin user already exists: admin@clarimount.com');
            return;
        }

        // Ensure super-admin role exists
        $superAdminRole = Role::where('name', 'super-admin')->first();
        if (!$superAdminRole) {
            $this->command->error('Super admin role not found! Please run RolesAndPermissionsSeeder first.');
            return;
        }

        // Create the admin user
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@clarimount.com',
            'password' => Hash::make('password'),
            'uses_password_login' => false,
            'email_verified_at' => now(),
            'language' => 'en',
        ]);

        // Assign super-admin role (global role, no team context)
        // Explicitly set team_id to null for global role
        $admin->roles()->attach($superAdminRole->id, ['team_id' => null]);

        // Fire the Registered event to trigger company and asset category creation
        event(new Registered($admin));

        $this->command->info('✅ Super admin user created successfully!');
        $this->command->info('📧 Email: admin@clarimount.com');
        $this->command->info('🔐 Login: OTP via work email (password login can be enabled in settings)');
        $this->command->info('🌐 Login at: ' . url('/login'));
        
        // Check if company and asset categories were created
        $company = $admin->currentCompany();
        if ($company) {
            $categoriesCount = $company->assetCategories()->count();
            $this->command->info("🏢 Company created: {$company->name_en}");
            $this->command->info("📦 Asset categories created: {$categoriesCount}");
        }
    }
} 