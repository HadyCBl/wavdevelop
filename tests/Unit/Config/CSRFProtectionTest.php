<?php
use PHPUnit\Framework\TestCase;

class CSRFProtectionTest extends TestCase
{
    public function testCsrfTokenIsGenerated()
    {
        $csrfToken = generateCsrfToken();
        $this->assertNotEmpty($csrfToken);
    }

    public function testCsrfTokenIsValid()
    {
        $csrfToken = generateCsrfToken();
        $this->assertTrue(isValidCsrfToken($csrfToken));
    }

    public function testCsrfTokenIsInvalid()
    {
        $this->assertFalse(isValidCsrfToken('invalid_token'));
    }
}