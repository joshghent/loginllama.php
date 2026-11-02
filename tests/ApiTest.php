<?php

use PHPUnit\Framework\TestCase;
use LoginLlama\Api;

// An outage must not block logins, so post() returns an "unavailable" result
// instead of throwing.
final class ApiTest extends TestCase {
    private static $server;
    private const BASE = 'http://127.0.0.1:8765';

    public static function setUpBeforeClass(): void {
        self::$server = proc_open(
            ['php', '-S', '127.0.0.1:8765', __DIR__ . '/fixtures/server.php'],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes
        );
        usleep(300000);
    }

    public static function tearDownAfterClass(): void {
        proc_terminate(self::$server);
    }

    public function testFailsOpenWhenUnreachable() {
        $result = (new Api([], 'http://127.0.0.1:1'))->post('/login/check');

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame(0, $result['risk_score']);
        $this->assertSame([], $result['codes']);
    }

    public function testFailsOpenOnServerError() {
        $result = (new Api([], self::BASE . '/bad-gateway'))->post('/login/check');

        $this->assertSame('unavailable', $result['status']);
    }

    public function testReturnsTheApiErrorBody() {
        $result = (new Api([], self::BASE . '/quota'))->post('/login/check');

        $this->assertSame('error', $result['status']);
        $this->assertSame('quota_exceeded', $result['error']);
    }

    // Last: the built-in server is single-threaded and stays busy with it.
    public function testGivesUpAfterTwoSeconds() {
        $started = microtime(true);
        $result = (new Api([], self::BASE . '/slow'))->post('/login/check');

        $this->assertSame('unavailable', $result['status']);
        $this->assertLessThan(3, microtime(true) - $started);
    }
}
