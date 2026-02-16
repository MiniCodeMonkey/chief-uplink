<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

describe('Security Headers', function () {
    it('includes Content-Security-Policy header on HTML responses', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertHeader('Content-Security-Policy');
    });

    it('includes a nonce in the CSP script-src directive', function () {
        $user = User::factory()->create();

        $response = actingAs($user)->get('/dashboard');

        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/");
    });

    it('includes a nonce in the CSP style-src directive', function () {
        $user = User::factory()->create();

        $response = actingAs($user)->get('/dashboard');

        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toMatch("/style-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/");
    });

    it('sets X-Content-Type-Options to nosniff', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->get('/dashboard')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('sets X-Frame-Options to DENY', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->get('/dashboard')
            ->assertHeader('X-Frame-Options', 'DENY');
    });

    it('sets Referrer-Policy header', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->get('/dashboard')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    });

    it('sets Permissions-Policy header', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->get('/dashboard')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    });

    it('includes frame-ancestors none in CSP', function () {
        $user = User::factory()->create();

        $response = actingAs($user)->get('/dashboard');

        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toContain("frame-ancestors 'none'");
    });

    it('includes object-src none in CSP', function () {
        $user = User::factory()->create();

        $response = actingAs($user)->get('/dashboard');

        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toContain("object-src 'none'");
    });

    it('allows fonts from bunny.net in CSP', function () {
        $user = User::factory()->create();

        $response = actingAs($user)->get('/dashboard');

        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toContain('https://fonts.bunny.net');
    });

    it('allows GitHub avatars in img-src', function () {
        $user = User::factory()->create();

        $response = actingAs($user)->get('/dashboard');

        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toContain('https://avatars.githubusercontent.com');
    });

    it('restricts form-action to self and GitHub', function () {
        $user = User::factory()->create();

        $response = actingAs($user)->get('/dashboard');

        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toContain("form-action 'self' https://github.com");
    });

    it('includes security headers on unauthenticated pages', function () {
        get('/login')
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    });

    it('includes WebSocket connect-src in CSP', function () {
        $user = User::factory()->create();

        $response = actingAs($user)->get('/dashboard');

        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toMatch('/connect-src .+ws[s]?:\/\//');
    });
});
