<?php

use App\Enums\PermissionName;
use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Route::get('/_test/moderation', fn (): array => ['authorized' => true])
        ->middleware(['web', 'auth', 'permission:report:moderate']);
});

test('global roles grant only their configured domain permissions', function (UserRole $role, array $allowedPermissions) {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();

    $user->assignRole($role);

    foreach (PermissionName::cases() as $permission) {
        expect($user->can($permission))->toBe(in_array($permission->value, $allowedPermissions, true));
    }

    expect($user->can('undefined:permission'))->toBeFalse();
})->with([
    'registered user' => [UserRole::RegisteredUser, [
        'report:create',
        'journey:start',
        'journey:share',
        'emergency-contact:manage',
    ]],
    'community moderator' => [UserRole::CommunityModerator, [
        'report:create',
        'journey:start',
        'journey:share',
        'emergency-contact:manage',
        'report:moderate',
        'statistics:view-disaggregated',
    ]],
    'municipal administrator' => [UserRole::MunicipalAdministrator, [
        'report:create',
        'journey:start',
        'journey:share',
        'emergency-contact:manage',
        'statistics:view-disaggregated',
        'statistics:export',
        'zone-alert:receive',
    ]],
]);

test('seeding repeatedly assigns the default role only to users without roles', function () {
    $user = User::factory()->create();
    $moderator = User::factory()->create();
    $moderator->assignRole(Role::findOrCreate(UserRole::CommunityModerator, 'web'));

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('roles', 3);
    $this->assertDatabaseCount('permissions', 8);
    $this->assertDatabaseCount('users', 2);
    expect($user->fresh()->getRoleNames()->all())->toBe(['registered_user']);
    expect($moderator->fresh()->getRoleNames()->all())->toBe(['community_moderator']);
});

test('seeding removes permissions outside the declared role matrix', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(UserRole::RegisteredUser);
    Role::findByName('registered_user', 'web')->givePermissionTo(PermissionName::ModerateReport);
    expect($user->can(PermissionName::ModerateReport))->toBeTrue();

    $this->seed(RolesAndPermissionsSeeder::class);

    expect($user->fresh()->can(PermissionName::ModerateReport))->toBeFalse();
});

test('permission middleware returns 403 to a registered user without moderation permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(UserRole::RegisteredUser);

    $response = $this->actingAs($user)->get('/_test/moderation');

    $response->assertForbidden();
});

test('permission middleware allows a community moderator', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(UserRole::CommunityModerator);

    $response = $this->actingAs($user)->get('/_test/moderation');

    $response->assertOk()->assertExactJson(['authorized' => true]);
});

test('permission protected web routes redirect guests to login', function () {
    $response = $this->get('/_test/moderation');

    $response->assertRedirect(route('login'));
});

test('municipal administrators cannot update teams they do not belong to', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $administrator = User::factory()->create();
    $administrator->assignRole(UserRole::MunicipalAdministrator);
    $team = Team::factory()->create(['name' => 'Original Team']);

    $response = $this->actingAs($administrator)->patch(route('teams.update', $team), [
        'name' => 'Changed Team',
    ]);

    $response->assertForbidden();
    expect($team->fresh()->name)->toBe('Original Team');
});

test('team owners do not inherit global moderation or municipal permissions', function () {
    $user = User::factory()->create();
    $this->seed(RolesAndPermissionsSeeder::class);

    expect($user->ownsTeam($user->personalTeam()))->toBeTrue();
    expect($user->can(PermissionName::ModerateReport))->toBeFalse();
    expect($user->can(PermissionName::ExportStatistics))->toBeFalse();
});
