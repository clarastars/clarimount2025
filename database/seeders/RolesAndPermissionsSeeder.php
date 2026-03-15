<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Team;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Team management
            'view team',
            'edit team',
            'delete team',
            'manage team settings',
            
            // User management
            'invite users',
            'view users',
            'edit users',
            'remove users',
            'assign roles',
            
            // Content management
            'create content',
            'view content',
            'edit content',
            'delete content',
            'publish content',
            
            // Admin permissions
            'view admin panel',
            'manage billing',
            'view analytics',
            'export data',
            
            // Department management
            'view departments',
            'create departments',
            'edit departments',
            'delete departments',
            
            // Profile management (per user)
            'edit own profile',
            'view own profile',

            // Salary run approvals (4-step workflow)
            'approve_salary_run_hr',
            'approve_salary_run_director',
            'approve_salary_run_accountant',
            'approve_salary_run_ceo',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create global system role (no team_id) – super-admin has ALL permissions always
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminRole->syncPermissions(Permission::all());

        // Employee portal role: for employees who log in to see their leave balance etc.
        Role::firstOrCreate(['name' => 'employee']);

        // Create team-specific roles (these will be used within teams)
        $teamOwnerPermissions = [
            'view team', 'edit team', 'delete team', 'manage team settings',
            'invite users', 'view users', 'edit users', 'remove users', 'assign roles',
            'create content', 'view content', 'edit content', 'delete content', 'publish content',
            'view admin panel', 'manage billing', 'view analytics', 'export data',
            'view departments', 'create departments', 'edit departments', 'delete departments',
            'edit own profile', 'view own profile',
            'approve_salary_run_hr', 'approve_salary_run_director', 'approve_salary_run_accountant', 'approve_salary_run_ceo',
        ];

        $teamAdminPermissions = [
            'view team', 'edit team', 'manage team settings',
            'invite users', 'view users', 'edit users', 'assign roles',
            'create content', 'view content', 'edit content', 'delete content', 'publish content',
            'view admin panel', 'view analytics', 'export data',
            'view departments', 'create departments', 'edit departments', 'delete departments',
            'edit own profile', 'view own profile',
        ];

        $teamMemberPermissions = [
            'view team',
            'view users',
            'create content', 'view content', 'edit content',
            'view departments',
            'edit own profile', 'view own profile',
        ];

        $teamViewerPermissions = [
            'view team',
            'view users',
            'view content',
            'view own profile',
        ];

        // Note: These are template roles. Actual team roles will be created
        // when teams are created, with the team_id specified
        $teamOwnerRole = Role::firstOrCreate(['name' => 'team-owner']);
        if (!$teamOwnerRole->permissions->count()) {
            $teamOwnerRole->givePermissionTo($teamOwnerPermissions);
        }
        
        $teamAdminRole = Role::firstOrCreate(['name' => 'team-admin']);
        if (!$teamAdminRole->permissions->count()) {
            $teamAdminRole->givePermissionTo($teamAdminPermissions);
        }
        
        $teamMemberRole = Role::firstOrCreate(['name' => 'team-member']);
        if (!$teamMemberRole->permissions->count()) {
            $teamMemberRole->givePermissionTo($teamMemberPermissions);
        }
        
        $teamViewerRole = Role::firstOrCreate(['name' => 'team-viewer']);
        if (!$teamViewerRole->permissions->count()) {
            $teamViewerRole->givePermissionTo($teamViewerPermissions);
        }

        $this->command->info('Roles and permissions created successfully!');
        $this->command->info('Created ' . Permission::count() . ' permissions');
        $this->command->info('Created ' . Role::count() . ' roles');
    }
}
