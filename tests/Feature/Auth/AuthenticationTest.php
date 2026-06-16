<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('password users can authenticate using the login screen', function () {
    $user = User::factory()->create([
        'uses_password_login' => true,
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('otp users cannot authenticate with password login', function () {
    $user = User::factory()->create([
        'uses_password_login' => false,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('password users can not authenticate with invalid password', function () {
    $user = User::factory()->create([
        'uses_password_login' => true,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('otp user can sign in with valid otp', function () {
    $user = User::factory()->create([
        'uses_password_login' => false,
    ]);

    $otp = '1234';
    Cache::put('login_otp:' . hash('sha256', strtolower($user->email)), [
        'otp_hash' => Hash::make($otp),
        'user_id' => $user->id,
        'attempts' => 0,
    ], now()->addMinutes(10));

    $response = $this->post('/login/verify-otp', [
        'email' => $user->email,
        'otp' => $otp,
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('identify sends otp for otp users', function () {
    Mail::fake();

    $user = User::factory()->create([
        'uses_password_login' => false,
    ]);

    $response = $this->post('/login/identify', [
        'email' => $user->email,
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('login_step', 'otp');
    Mail::assertSent(\App\Mail\LoginOtpMail::class);
});

test('identify redirects password users to password step', function () {
    $user = User::factory()->create([
        'uses_password_login' => true,
    ]);

    $response = $this->post('/login/identify', [
        'email' => $user->email,
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('login_step', 'password');
});

test('identify rejects unregistered work email', function () {
    Mail::fake();

    $response = $this->post('/login/identify', [
        'email' => 'unknown@example.com',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $response->assertSessionMissing('login_step');
    Mail::assertNothingSent();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
