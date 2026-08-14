<?php

namespace Tests\Feature;

use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_password_reset_routes_are_disabled(): void
    {
        $this->assertFalse(Features::enabled(Features::resetPasswords()));
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password')->assertNotFound();
        $this->get('/reset-password/test-token')->assertNotFound();
        $this->post('/reset-password')->assertNotFound();
    }
}
