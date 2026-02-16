<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.fake()->uuid(),
            'p256dh_key' => base64_encode(random_bytes(65)),
            'auth_token' => base64_encode(random_bytes(16)),
            'content_encoding' => 'aesgcm',
        ];
    }
}
