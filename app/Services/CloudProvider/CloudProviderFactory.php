<?php

namespace App\Services\CloudProvider;

use App\Contracts\CloudProviderInterface;
use App\Enums\CloudProvider;
use InvalidArgumentException;

class CloudProviderFactory
{
    /**
     * Resolve the correct cloud provider implementation.
     */
    public static function make(CloudProvider $provider, string $apiKey): CloudProviderInterface
    {
        return match ($provider) {
            CloudProvider::Hetzner => new HetznerProvider($apiKey),
            CloudProvider::DigitalOcean => new DigitalOceanProvider($apiKey),
            default => throw new InvalidArgumentException("Unsupported cloud provider: {$provider->value}"),
        };
    }
}
