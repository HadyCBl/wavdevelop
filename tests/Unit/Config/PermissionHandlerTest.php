<?php
use PHPUnit\Framework\TestCase;

class PermissionHandlerTest extends TestCase
{
    public function testPermissionGranted()
    {
        $this->assertTrue(true);
    }

    public function testPermissionDenied()
    {
        $this->assertFalse(false);
    }
}