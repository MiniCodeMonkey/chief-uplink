<?php

use App\Models\DeviceAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Onboarding', function () {
    it('shows onboarding for new user with no devices ever', function () {
        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            expect($page->toArray()['props']['showOnboarding'])->toBeTrue();
        });
    });

    it('does not show onboarding when user has an active device', function () {
        DeviceAuthorization::factory()->for($this->user)->online()->create();

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            expect($page->toArray()['props']['showOnboarding'])->toBeFalse();
        });
    });

    it('does not show onboarding when user has a revoked device (previously authorized)', function () {
        DeviceAuthorization::factory()->for($this->user)->revoked()->create();

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            expect($page->toArray()['props']['showOnboarding'])->toBeFalse();
        });
    });

    it('does not show onboarding when user has an offline device', function () {
        DeviceAuthorization::factory()->for($this->user)->offline()->create();

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            expect($page->toArray()['props']['showOnboarding'])->toBeFalse();
        });
    });
});
