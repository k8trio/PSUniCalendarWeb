<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function passwordResetAccount(string $role, string $email, string $name): User
{
    return User::create([
        'name' => $name,
        'email' => $email,
        'password' => 'OriginalPassword123',
        'role' => $role,
        'campus' => $role === 'office' ? 'University Offices' : null,
    ]);
}

test('planning office reset issues a one-time password and visibly records who reset it', function () {
    $planning = passwordResetAccount('planning_office', 'planning@psu.local', 'Planning Administrator');
    $office = passwordResetAccount('office', 'op@psu.local', 'Office of the President');

    $this->actingAs($planning)
        ->post(route('planning_office.offices.password-reset', $office))
        ->assertRedirect(route('planning_office.offices.show', $office))
        ->assertSessionHas('temporary_password');

    $temporaryPassword = session('temporary_password');

    expect($temporaryPassword)->toBe('Office@123');
    $office->refresh();
    expect($office->must_change_password)->toBeTrue();
    expect(Hash::check($temporaryPassword, $office->password))->toBeTrue();

    $activity = ActivityLog::where('email', $office->email)
        ->where('action', 'password_reset')
        ->sole();
    expect($activity->description)->toContain('Planning Administrator')
        ->not->toContain($temporaryPassword);

    $this->get(route('planning_office.offices.show', $office))
        ->assertOk()
        ->assertSee('Account access')
        ->assertSee('Reset password')
        ->assertSee($temporaryPassword);

    $this->get(route('planning_office.offices.show', $office))
        ->assertOk()
        ->assertDontSee($temporaryPassword)
        ->assertSee('Password reset by Planning Office account');

    $this->get(route('planning_office.notifications'))
        ->assertOk()
        ->assertSee('Password reset by Planning Office account')
        ->assertSee('Planning Administrator');
});

test('office must replace the temporary password before accessing the dashboard', function () {
    $planning = passwordResetAccount('planning_office', 'planning@psu.local', 'Planning Administrator');
    $office = passwordResetAccount('office', 'op@psu.local', 'Office of the President');

    $this->actingAs($planning)
        ->post(route('planning_office.offices.password-reset', $office));
    $temporaryPassword = session('temporary_password');
    auth()->logout();

    $this->post(route('login.submit'), [
        'email' => $office->email,
        'password' => $temporaryPassword,
    ])->assertRedirect(route('office.password.force'));

    $this->get(route('office.dashboard'))
        ->assertRedirect(route('office.password.force'));
    $this->get(route('office.password.force'))
        ->assertOk()
        ->assertSee('Set a new password');

    $this->patch(route('office.password.force.update'), [
        'new_password' => 'NewOfficePassword456',
        'new_password_confirmation' => 'NewOfficePassword456',
    ])->assertRedirect(route('office.dashboard'));

    expect($office->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('NewOfficePassword456', $office->fresh()->password))->toBeTrue();
    expect(ActivityLog::where('email', $office->email)->where('action', 'password_changed')->exists())->toBeTrue();
});

test('only planning office can reset an office password and normal office changes remain audited', function () {
    $office = passwordResetAccount('office', 'op@psu.local', 'Office of the President');
    $initialPassword = $office->password;

    $this->actingAs($office)
        ->post(route('planning_office.offices.password-reset', $office))
        ->assertRedirect(route('login'));

    expect($office->fresh()->password)->toBe($initialPassword);
    expect(ActivityLog::where('email', $office->email)->where('action', 'password_reset')->exists())->toBeFalse();

    $this->actingAs($office)
        ->patch(route('office.password.update'), [
            'current_password' => 'OriginalPassword123',
            'new_password' => 'OfficePassword789',
            'new_password_confirmation' => 'OfficePassword789',
        ])
        ->assertRedirect(route('office.dashboard') . '#office-profile');

    expect(ActivityLog::where('email', $office->email)->where('action', 'password_changed')->exists())->toBeTrue();
});
