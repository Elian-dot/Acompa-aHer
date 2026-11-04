<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the web app manifest is valid, installable and its icons exist', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('AcompañaHer')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toStartWith('/')
        ->and($manifest['scope'])->toBe('/');

    $sizes = [];
    foreach ($manifest['icons'] as $icon) {
        $path = public_path(ltrim($icon['src'], '/'));
        expect(file_exists($path))->toBeTrue("Falta {$icon['src']}");
        [$width, $height] = getimagesize($path);
        expect("{$width}x{$height}")->toBe($icon['sizes']);
        $sizes[$icon['purpose']][] = $icon['sizes'];
    }

    expect($sizes['any'])->toContain('192x192', '512x512')->and($sizes['maskable'])->toContain('512x512');
});

test('the service worker handles push and never caches api responses or authenticated pages', function () {
    $worker = file_get_contents(public_path('sw.js'));

    expect($worker)->toContain("addEventListener('push'")
        ->and($worker)->toContain("addEventListener('notificationclick'")
        ->and($worker)->toContain("url.pathname.startsWith('/api/')")
        ->and($worker)->toContain('showNotification')
        ->and($worker)->not->toContain('cache.put(request, response)');
});

test('the offline page works without the network and keeps the emergency call available', function () {
    $page = file_get_contents(public_path('offline.html'));

    expect($page)->toContain('tel:123')->and($page)->not->toMatch('/<script[^>]+src=|<link[^>]+stylesheet/i');
});

test('every page declares the manifest and theme color for installation', function () {
    $this->get('/')->assertOk()->assertSee('rel="manifest" href="/manifest.webmanifest"', false)->assertSee('name="theme-color"', false);
});

test('the journey and alert pages expose real limits and the push public key', function () {
    [$user] = journeyOwner(1);
    config(['safety.webpush.public_key' => 'clave-publica-de-prueba']);

    $this->actingAs($user)->get(route('acompanamiento'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('acompanamiento')->where('vapidPublicKey', 'clave-publica-de-prueba')
            ->where('canStartJourney', true)->where('limits.minInactivityMinutes', 5)->where('limits.maxInactivityMinutes', 30)->has('mapConfiguration'));
    $this->get(route('inactividad'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('inactividad')->where('vapidPublicKey', 'clave-publica-de-prueba'));

    $withoutPermission = User::factory()->create();
    $this->actingAs($withoutPermission)->get(route('acompanamiento'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canStartJourney', false));
});

test('webpush keys can be generated with the artisan command', function () {
    $this->artisan('webpush:keys')->expectsOutputToContain('VAPID_PUBLIC_KEY=')->expectsOutputToContain('VAPID_PRIVATE_KEY=')->assertSuccessful();
});
