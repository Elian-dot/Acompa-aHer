<?php

use App\Enums\UserRole;
use App\Models\EmergencyContact;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

test('the domain permission matrix is enforced by policies independently from team ownership', function (UserRole $role, bool $canModerate) {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole($role);
    $contact = EmergencyContact::factory()->for($user)->create();
    $otherContact = EmergencyContact::factory()->create();
    $gate = Gate::forUser($user);
    expect($gate->allows('create', Report::class))->toBeTrue();
    expect($gate->allows('moderate', Report::class))->toBe($canModerate);
    expect($gate->allows('viewAny', EmergencyContact::class))->toBeTrue();
    expect($gate->allows('update', $contact))->toBeTrue();
    expect($gate->inspect('update', $otherContact)->status())->toBe(404);
})->with([
    'registered user' => [UserRole::RegisteredUser, false],
    'community moderator' => [UserRole::CommunityModerator, true],
    'municipal administrator' => [UserRole::MunicipalAdministrator, false],
]);

test('team ownership alone does not authorize domain actions', function () {
    $user = User::factory()->create();
    expect($user->ownsTeam($user->currentTeam))->toBeTrue();
    expect(Gate::forUser($user)->allows('create', Report::class))->toBeFalse();
    expect(Gate::forUser($user)->allows('moderate', Report::class))->toBeFalse();
    expect(Gate::forUser($user)->allows('viewAny', EmergencyContact::class))->toBeFalse();
});

test('moderation endpoints reject municipal administrators without moderation permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $administrator = User::factory()->create()->assignRole(UserRole::MunicipalAdministrator);
    $report = Report::factory()->create();
    $this->actingAs($administrator)->getJson(route('api.v1.moderation.index'))->assertForbidden();
    $this->patchJson(route('api.v1.moderation.update', $report), ['status' => 'verified', 'privacy_reviewed' => true, 'coverage_reviewed' => true])->assertForbidden();
    $this->get(route('moderation'))->assertForbidden();
    $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'pending']);
});

test('domain pages expose permissions and render dedicated views for authorized users', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $moderator = User::factory()->create()->assignRole(UserRole::CommunityModerator);
    $this->actingAs($moderator)->get(route('moderation'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('moderacion')->has('discardReasons')->where('auth.permissions', fn ($permissions) => $permissions->contains('report:moderate')));
    $this->get(route('contacts'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('contactos'));
});
