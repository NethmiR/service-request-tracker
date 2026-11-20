<?php

// Verifies the display once temporary password delivery and its container binding.
// The delivery hands the password back unchanged so the admin can see it exactly once.

namespace Tests\Unit\Services\Users;

use App\Contracts\Users\TemporaryPasswordDelivery;
use App\Models\User;
use App\Services\Users\DisplayOnceTemporaryPasswordDelivery;
use Tests\TestCase;

class DisplayOnceTemporaryPasswordDeliveryTest extends TestCase
{
    private const string TEMPORARY_PASSWORD = 'Rk7#vQm2xTz9!pWc';

    public function test_deliver_returns_the_password_unchanged(): void
    {
        $delivery = new DisplayOnceTemporaryPasswordDelivery;

        $this->assertSame(
            self::TEMPORARY_PASSWORD,
            $delivery->deliver(User::factory()->make(), self::TEMPORARY_PASSWORD),
        );
    }

    public function test_container_resolves_the_display_once_delivery(): void
    {
        $this->assertInstanceOf(
            DisplayOnceTemporaryPasswordDelivery::class,
            $this->app->make(TemporaryPasswordDelivery::class),
        );
    }
}
