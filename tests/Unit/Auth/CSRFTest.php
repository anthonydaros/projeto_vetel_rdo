<?php
namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Auth\CSRF;

class CSRFTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../auth/CSRF.php';
        // Clear session for each test
        $_SESSION = [];
    }
    
    protected function tearDown(): void
    {
        $_SESSION = [];
    }
    
    public function testGenerateTokenCreatesUniqueToken(): void
    {
        $token1 = CSRF::generateToken();
        $token2 = CSRF::generateToken();
        
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertNotEquals($token1, $token2);
    }
    
    public function testGetTokenReturnsConsistentToken(): void
    {
        $token1 = CSRF::getToken();
        $token2 = CSRF::getToken();
        
        $this->assertEquals($token1, $token2);
    }
    
    public function testValidateTokenReturnsTrueForValidToken(): void
    {
        $token = CSRF::generateToken();
        $isValid = CSRF::validateToken($token);
        
        $this->assertTrue($isValid);
    }
    
    public function testValidateTokenReturnsFalseForInvalidToken(): void
    {
        CSRF::generateToken();
        $isValid = CSRF::validateToken('invalid_token_12345');
        
        $this->assertFalse($isValid);
    }
    
    public function testGetTokenFieldGeneratesHiddenInput(): void
    {
        $field = CSRF::getTokenField();
        
        $this->assertStringContainsString('<input type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }
    
    public function testVerifyPostReturnsTrueWithValidToken(): void
    {
        $token = CSRF::generateToken();
        $_POST['csrf_token'] = $token;
        
        $this->assertTrue(CSRF::verifyPost());
    }
    
    public function testVerifyPostReturnsFalseWithoutToken(): void
    {
        CSRF::generateToken();
        $_POST = [];
        
        $this->assertFalse(CSRF::verifyPost());
    }
    
    public function testVerifyPostReturnsFalseWithInvalidToken(): void
    {
        CSRF::generateToken();
        $_POST['csrf_token'] = 'invalid_token';
        
        $this->assertFalse(CSRF::verifyPost());
    }
    
    public function testClearTokenRemovesToken(): void
    {
        $token = CSRF::generateToken();
        $this->assertNotEmpty($_SESSION['csrf_token']);
        
        CSRF::clearToken();
        $this->assertArrayNotHasKey('csrf_token', $_SESSION);
    }
}