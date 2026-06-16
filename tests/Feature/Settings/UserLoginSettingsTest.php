<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
});

test('admin can enable password login and set password', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $target = User::factory()->create([
        'uses_password_login' => false,
        'password' => null,
    ]);

    $response = $this->actingAs($admin)->put(route('settings.user-login.update', $target), [
        'uses_password_login' => true,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertRedirect(route('settings.user-login.index'));
    $response->assertSessionHas('status');

    $target->refresh();
    expect($target->uses_password_login)->toBeTrue();
    expect(Hash::check('NewPassword123!', $target->password))->toBeTrue();
});

test('admin can enable password login without new password when user already has one', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $target = User::factory()->create([
        'uses_password_login' => false,
        'password' => 'password',
    ]);

    $response = $this->actingAs($admin)->put(route('settings.user-login.update', $target), [
        'uses_password_login' => true,
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertRedirect(route('settings.user-login.index'));

    $target->refresh();
    expect($target->uses_password_login)->toBeTrue();
    expect(Hash::check('password', $target->password))->toBeTrue();
});

test('admin can switch user back to otp login', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $target = User::factory()->create([
        'uses_password_login' => true,
    ]);

    $response = $this->actingAs($admin)->put(route('settings.user-login.update', $target), [
        'uses_password_login' => false,
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertRedirect(route('settings.user-login.index'));

    expect($target->fresh()->uses_password_login)->toBeFalse();
});
