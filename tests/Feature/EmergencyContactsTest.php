<?php

use App\Enums\UserRole;
use App\Models\EmergencyContact;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

test('contacts can be created edited listed and deleted only by their owner', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    $other = User::factory()->create();
    EmergencyContact::factory()->for($other)->create(['name' => 'Otra persona']);
    $response = $this->actingAs($user)->postJson(route('api.v1.emergency-contacts.store'), [
        'name' => 'Mi contacto', 'phone' => '+573001234567', 'email' => 'contacto@example.test', 'user_id' => $other->id,
    ])->assertCreated()->assertJsonPath('data.name', 'Mi contacto')->assertJsonMissingPath('data.user_id');
    $id = $response->json('data.id');
    $contact = EmergencyContact::query()->findOrFail($id);
    expect($contact->user_id)->toBe($user->id);
    expect(DB::table('emergency_contacts')->where('id', $id)->value('phone'))->not->toBe('+573001234567');
    $this->getJson(route('api.v1.emergency-contacts.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)->assertHeader('Cache-Control', 'no-store, private');
    $this->patchJson(route('api.v1.emergency-contacts.update', $id), ['name' => 'Nombre corregido', 'phone' => '+573002345678', 'email' => null])->assertOk()->assertJsonPath('data.name', 'Nombre corregido');
    expect($contact->fresh()->phone)->toBe('+573002345678');
    $this->deleteJson(route('api.v1.emergency-contacts.destroy', $id))->assertNoContent();
    $this->assertDatabaseMissing('emergency_contacts', ['id' => $id]);
});

test('contacts reject unauthenticated callers missing permissions and malformed telephone numbers', function () {
    $this->getJson(route('api.v1.emergency-contacts.index'))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson(route('api.v1.emergency-contacts.index'))->assertForbidden();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->postJson(route('api.v1.emergency-contacts.store'), ['name' => 'Amiga', 'phone' => '3001234567'])->assertUnprocessable()
        ->assertJsonPath('errors.phone.0', 'Usa el formato internacional, por ejemplo +573001234567.');
});

test('another account cannot edit or delete a private contact even with a municipal administrator role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $administrator = User::factory()->create()->assignRole(UserRole::MunicipalAdministrator);
    $contact = EmergencyContact::factory()->create();
    $this->actingAs($administrator)->patchJson(route('api.v1.emergency-contacts.update', $contact), ['name' => 'Cambio ajeno', 'phone' => '+573001234567'])->assertNotFound();
    $this->deleteJson(route('api.v1.emergency-contacts.destroy', $contact))->assertNotFound();
    expect($contact->fresh()->name)->not->toBe('Cambio ajeno');
});

test('a user cannot exceed the private contact limit', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    EmergencyContact::factory()->count(20)->for($user)->create();
    $this->actingAs($user)->postJson(route('api.v1.emergency-contacts.store'), ['name' => 'Otro contacto', 'phone' => '+573001234567'])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseCount('emergency_contacts', 20);
});

test('SOS requires explicit confirmation and only prepares messages without claiming delivery or persisting location', function () {
    Http::preventStrayRequests();
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    $contact = EmergencyContact::factory()->for($user)->create(['name' => 'Amiga', 'phone' => '+573001234567']);
    $this->actingAs($user)->postJson(route('api.v1.sos.store'), ['contact_ids' => [$contact->id], 'confirmed' => false])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
    $this->postJson(route('api.v1.sos.store'), ['contact_ids' => [$contact->id], 'confirmed' => true, 'latitude' => 4.303, 'longitude' => -74.8039])
        ->assertOk()->assertJsonPath('data.0.status', 'prepared')->assertJsonPath('data.0.call_url', 'tel:+573001234567')
        ->assertJsonPath('message', 'Necesito ayuda. Esta es mi ubicación aproximada: https://www.openstreetmap.org/?mlat=4.303000&mlon=-74.803900#map=17/4.303000/-74.803900');
    $this->assertDatabaseCount('journeys', 0);
    $this->assertDatabaseCount('journey_alerts', 0);
    $this->assertDatabaseCount('journey_locations', 0);
    Http::assertNothingSent();
});

test('SOS can prepare an honest message without GPS and rejects another users recipients', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    $contact = EmergencyContact::factory()->for($user)->create();
    $otherContact = EmergencyContact::factory()->create();
    $this->actingAs($user)->postJson(route('api.v1.sos.store'), ['contact_ids' => [$contact->id], 'confirmed' => true])
        ->assertOk()->assertJsonPath('message', 'Necesito ayuda. No pude obtener mi ubicación.');
    $this->postJson(route('api.v1.sos.store'), ['contact_ids' => [$otherContact->id], 'confirmed' => true])->assertNotFound();
    $this->postJson(route('api.v1.sos.store'), ['contact_ids' => [$contact->id], 'confirmed' => true, 'latitude' => 4.303])->assertUnprocessable()->assertJsonValidationErrors('longitude');
});
