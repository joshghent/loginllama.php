<?php

use PHPUnit\Framework\TestCase;
use LoginLlama\LoginLlama;
use LoginLlama\LoginCheckStatus;
use LoginLlama\Api;

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

        $result = $this->loginLlama->check('validUser', [
            'ipAddress' => '192.168.1.1',
            'userAgent' => 'Mozilla/5.0',
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

        $result = $this->loginLlama->check('invalidUser', [
            'ipAddress' => '192.168.1.1',
            'userAgent' => 'Mozilla/5.0',
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Login check failed', $result['message']);
        $this->assertContains(LoginCheckStatus::KNOWN_PROXY, $result['codes']);
    }

    public function testReportSuccess() {
        $this->apiMock->expects($this->once())
                      ->method('post')
                      ->with(
                          '/login/check',
                          $this->callback(function($params) {
                              return $params['authentication_outcome'] === 'success';
                          })
                      )
                      ->willReturn([
                          'status' => 'success',
                          'message' => 'Login recorded',
                          'codes' => [LoginCheckStatus::VALID]
                      ]);

        $result = $this->loginLlama->reportSuccess('user123', [
            'ipAddress' => '192.168.1.1',
            'userAgent' => 'Mozilla/5.0',
        ]);

        $this->assertEquals('success', $result['status']);
    }

    public function testReportFailure() {
        $this->apiMock->expects($this->once())
                      ->method('post')
                      ->with(
                          '/login/check',
                          $this->callback(function($params) {
                              return $params['authentication_outcome'] === 'failed';
                          })
                      )
                      ->willReturn([
                          'status' => 'success',
                          'message' => 'Failed login recorded',
                          'codes' => []
                      ]);

        $result = $this->loginLlama->reportFailure('user123', [
            'ipAddress' => '192.168.1.1',
            'userAgent' => 'Mozilla/5.0',
        ]);

        $this->assertEquals('success', $result['status']);
    }
}
