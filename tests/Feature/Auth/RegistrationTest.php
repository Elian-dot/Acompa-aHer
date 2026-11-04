<?php

use App\Enums\TeamRole;
use App\Enums\UserRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen includes team invitation context', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Laravel Team']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this->get(route('register', ['invitation' => $invitation->code]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/register')
        ->where('teamInvitation.code', $invitation->code)
        ->where('teamInvitation.teamName', 'Laravel Team'),
    );
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->first();
    expect($user->getRoleNames()->all())->toBe(['registered_user']);
    $response->assertRedirect(route('dashboard'));
});

test('registration cannot assign roles or permissions supplied in the request', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => UserRole::MunicipalAdministrator->value,
        'roles' => [UserRole::CommunityModerator->value],
        'permissions' => ['report:moderate', 'statistics:export'],
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));
    $user = User::query()->where('email', 'test@example.com')->firstOrFail();
    expect($user->getRoleNames()->all())->toBe(['registered_user']);
    expect($user->getDirectPermissions())->toBeEmpty();
    expect($user->can('report:moderate'))->toBeFalse();
    expect($user->can('statistics:export'))->toBeFalse();
});
