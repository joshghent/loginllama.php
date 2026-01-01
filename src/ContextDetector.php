<?php

namespace LoginLlama;

use LoginLlama\IPExtractor;

/**
 * Stores extracted request information
 */
class RequestContext {
    public ?string $ipAddress = null;
    public ?string $userAgent = null;
    public string $framework = 'unknown';
    public $rawRequest = null;

    public function __construct(?string $ipAddress = null, ?string $userAgent = null, string $framework = 'unknown', $rawRequest = null) {
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->framework = $framework;
        $this->rawRequest = $rawRequest;
    }
}

/**
 * Context detector for automatically capturing request information
 * using static storage for single-threaded PHP environments.
 */
class ContextDetector {
    private static ?RequestContext $context = null;

    /**
     * Store request context for the current request
     *
     * @param mixed $request Request object or null to use $_SERVER
     * @return void
     */
    public static function setContext($request = null): void {
        // Use $_SERVER if no request object provided
        if ($request === null) {
            $request = $_SERVER;
        }

        $context = new RequestContext(
            IPExtractor::extract($request),
            self::extractUserAgent($request),
            self::detectFramework($request),
            $request
        );

        self::$context = $context;
    }

    /**
     * Retrieve current request context
     *
     * @return RequestContext|null Request context or null if not set
     */
    public static function getContext(): ?RequestContext {
        return self::$context;
    }

    /**
     * Clear the current context
     *
     * @return void
     */
    public static function clearContext(): void {
        self::$context = null;
    }

    /**
     * Detect which framework the request is from
     *
     * @param mixed $request Request object or array
     * @return string Framework name
     */
    private static function detectFramework($request): string {
        // Laravel: Request object with input() method
        if (is_object($request) && method_exists($request, 'input')) {
            return 'laravel';
        }

        // Symfony: Request from HttpFoundation
        if (is_object($request) && get_class($request) === 'Symfony\\Component\\HttpFoundation\\Request') {
            return 'symfony';
        }

        // WordPress: defined ABSPATH
        if (defined('ABSPATH')) {
            return 'wordpress';
        }

        // Vanilla PHP: $_SERVER array
        if (is_array($request)) {
            return 'vanilla';
        }

        return 'unknown';
    }

    /**
     * Extract User-Agent from request
     *
     * @param mixed $request Request object or array
     * @return string|null User-Agent or null
     */
    private static function extractUserAgent($request): ?string {
        // Array (vanilla PHP $_SERVER)
        if (is_array($request)) {
            return $request['HTTP_USER_AGENT'] ?? null;
        }

        // Laravel Request
        if (is_object($request) && method_exists($request, 'header')) {
            return $request->header('User-Agent');
        }

        // Symfony Request
        if (is_object($request) && method_exists($request, 'headers')) {
            return $request->headers->get('User-Agent');
        }

        return null;
    }
}
