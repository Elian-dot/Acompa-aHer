<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

dataset('modulos', [
    'rutas' => ['rutas'],
    'mapa' => ['mapa'],
    'horarios' => ['horarios'],
    'reportes' => ['reportes'],
    'sos' => ['sos'],
    'acompanamiento' => ['acompanamiento'],
    'inactividad' => ['inactividad'],
    'comunidad' => ['comunidad'],
]);

test('guests are redirected to login from every module', function (string $module) {
    $user = User::factory()->create();

    $this->get(route($module, ['current_team' => $user->currentTeam->slug]))
        ->assertRedirect(route('login'));
})->with('modulos');

test('authenticated users can open every module', function (string $module) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($module))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($module));
})->with('modulos');
