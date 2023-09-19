<?php

use PHPUnit\Framework\TestCase;

final class LoginLlamaTest extends TestCase {
    private $loginLlama;
    private $apiMock;

    protected function setUp(): void {
        $this->apiMock = $this->createMock(Api::class);
        $this->loginLlama = new LoginLlama("mockToken", $this->apiMock);
    }

    public function testValidLogin() {
        // Mock the post method of the Api class to return a specific response
        $this->apiMock->expects($this->once())
                      ->method('post')
                      ->willReturn([
                          'status' => 'success',
                          'message' => 'Valid login',
                          'codes' => [LoginCheckStatus::VALID]
                      ]);

        $result = $this->loginLlama->check_login([
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'identity_key' => 'validUser'
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Valid login', $result['message']);
        $this->assertContains(LoginCheckStatus::VALID, $result['codes']);
    }

    public function testInvalidLogin() {
        // Mock the post method of the Api class to return an error response
        $this->apiMock->expects($this->once())
                    ->method('post')
                    ->willReturn([
                        'status' => 'error',
                        'message' => 'Login check failed',
                        'codes' => [LoginCheckStatus::KNOWN_PROXY]
                    ]);

        $result = $this->loginLlama->check_login([
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'identity_key' => 'invalidUser'
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Login check failed', $result['message']);
        $this->assertContains(LoginCheckStatus::KNOWN_PROXY, $result['codes']);
    }
}
?>
