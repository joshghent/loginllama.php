<?php

namespace LoginLlama;

use LoginLlama\Api;
use LoginLlama\ContextDetector;
use LoginLlama\IPExtractor;

define('LOGINLLAMA_API_ENDPOINT', 'https://loginllama.app/api/v1');

/**
 * Status codes returned by the LoginLlama API
 */
class LoginCheckStatus {
    const VALID = "login_valid";
    const IP_ADDRESS_SUSPICIOUS = "ip_address_suspicious";
    const DEVICE_FINGERPRINT_SUSPICIOUS = "device_fingerprint_suspicious";
    const LOCATION_FINGERPRINT_SUSPICIOUS = "location_fingerprint_suspicious";
    const BEHAVIORAL_FINGERPRINT_SUSPICIOUS = "behavioral_fingerprint_suspicious";
    const KNOWN_TOR_EXIT_NODE = "known_tor_exit_node";
    const KNOWN_PROXY = "known_proxy";
    const KNOWN_VPN = "known_vpn";
    const KNOWN_BOTNET = "known_botnet";
    const KNOWN_BOT = "known_bot";
    const IP_ADDRESS_NOT_USED_BEFORE = "ip_address_not_used_before";
    const DEVICE_FINGERPRINT_NOT_USED_BEFORE = "device_fingerprint_not_used_before";
    const AI_DETECTED_SUSPICIOUS = "ai_detected_suspicious";
}

/**
 * Authentication outcome values for tracking login results
 */
class AuthenticationOutcome {
    /** User's credentials were valid (default) */
    const SUCCESS = "success";
    /** User's credentials were invalid (wrong password, MFA failed, etc.) */
    const FAILED = "failed";
    /** Pre-auth check, outcome not yet known */
    const PENDING = "pending";
}

/**
 * LoginLlama client for detecting suspicious login attempts
 */
class LoginLlama {
    private Api $api;
    private string $token;

    /**
     * Create a new LoginLlama client
     *
     * @param string|null $apiToken API key (defaults to LOGINLLAMA_API_KEY env var)
     * @param Api|null $api Custom API instance for testing
     */
    public function __construct(?string $apiToken = null, ?Api $api = null) {
        $this->token = $apiToken ?: (getenv("LOGINLLAMA_API_KEY") ?: '');
        $this->api = $api ?: new Api(["X-API-KEY" => $this->token], LOGINLLAMA_API_ENDPOINT);
    }

    /**
     * Check a login attempt for suspicious activity
     *
     * IP address and User-Agent are automatically detected from:
     * 1. Explicit overrides in options
     * 2. Explicit request object in options
     * 3. Context (if middleware is used)
     * 4. Global $_SERVER (fallback)
     *
     * @param string $identityKey User identifier (email, username, user ID, etc.)
     * @param array $options Optional overrides and additional context
     *                       - ipAddress: Override auto-detected IP
     *                       - userAgent: Override auto-detected User-Agent
     *                       - emailAddress: User's email
     *                       - geoCountry: Country name or ISO code
     *                       - geoCity: City name
     *                       - userTimeOfDay: User's local time in HH:mm format
     *                       - authenticationOutcome: 'success' (default), 'failed', or 'pending'
     *                       - request: Framework request object
     * @return array Login check result
     * @throws \Exception If required parameters cannot be detected
     *
     * @example
     * // Auto-detect from global $_SERVER
     * $result = $loginllama->check('user@example.com');
     *
     * @example
     * // Laravel with Request object
     * public function login(Request $request) {
     *     $result = $loginllama->check($request->input('email'), [
     *         'request' => $request
     *     ]);
     * }
     *
     * @example
     * // Manual override
     * $result = $loginllama->check('user@example.com', [
     *     'ipAddress' => '1.2.3.4',
     *     'userAgent' => 'Custom/1.0'
     * ]);
     *
     * @example
     * // Pre-auth check
     * $result = $loginllama->check($email, [
     *     'authenticationOutcome' => AuthenticationOutcome::PENDING,
     *     'request' => $request
     * ]);
     */
    public function check(string $identityKey, array $options = []): array {
        if (empty($identityKey)) {
            throw new \Exception("identity_key is required");
        }

        $ipAddress = $options['ipAddress'] ?? $options['ip_address'] ?? null;
        $userAgent = $options['userAgent'] ?? $options['user_agent'] ?? null;
        $request = $options['request'] ?? null;

        // Priority 1: Explicit overrides (already set)

        // Priority 2: Extract from explicit request
        if ($request && (!$ipAddress || !$userAgent)) {
            if (!$ipAddress) {
                $ipAddress = IPExtractor::extract($request);
            }
            if (!$userAgent) {
                $userAgent = $this->extractUserAgent($request);
            }
        }

        // Priority 3: Check stored context
        if (!$ipAddress || !$userAgent) {
            $context = ContextDetector::getContext();
            if ($context) {
                if (!$ipAddress) $ipAddress = $context->ipAddress;
                if (!$userAgent) $userAgent = $context->userAgent;
            }
        }

        // Priority 4: Fallback to $_SERVER
        if (!$ipAddress || !$userAgent) {
            if (!$ipAddress) {
                $ipAddress = IPExtractor::extract($_SERVER);
            }
            if (!$userAgent) {
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            }
        }

        // Validation
        if (!$ipAddress) {
            throw new \Exception(
                "ip_address could not be detected. Pass 'ipAddress' or 'request' in options."
            );
        }
        if (!$userAgent) {
            throw new \Exception(
                "user_agent could not be detected. Pass 'userAgent' or 'request' in options."
            );
        }

        return $this->api->post("/login/check", [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'identity_key' => $identityKey,
            'email_address' => $options['emailAddress'] ?? $options['email_address'] ?? null,
            'geo_country' => $options['geoCountry'] ?? $options['geo_country'] ?? null,
            'geo_city' => $options['geoCity'] ?? $options['geo_city'] ?? null,
            'user_time_of_day' => $options['userTimeOfDay'] ?? $options['user_time_of_day'] ?? null,
            'authentication_outcome' => $options['authenticationOutcome'] ?? $options['authentication_outcome'] ?? null,
        ]);
    }

    /**
     * Report a successful authentication
     *
     * Use this after the user has successfully authenticated with your system.
     * This is a convenience method equivalent to:
     * `check($identityKey, ['authenticationOutcome' => 'success', ...])`
     *
     * @param string $identityKey User identifier (email, username, user ID, etc.)
     * @param array $options Optional overrides and additional context
     * @return array Login check result
     * @throws \Exception If required parameters cannot be detected
     *
     * @example
     * // After successful login
     * if ($authResult->success) {
     *     $loginllama->reportSuccess($user->id, ['request' => $request]);
     * }
     */
    public function reportSuccess(string $identityKey, array $options = []): array {
        $options['authenticationOutcome'] = AuthenticationOutcome::SUCCESS;
        return $this->check($identityKey, $options);
    }

    /**
     * Report a failed authentication attempt
     *
     * Use this when the user's credentials are invalid (wrong password, MFA failed, etc.).
     * This helps LoginLlama detect brute force and credential stuffing attacks.
     *
     * @param string $identityKey User identifier (email, username, user ID, etc.)
     * @param array $options Optional overrides and additional context
     * @return array Login check result
     * @throws \Exception If required parameters cannot be detected
     *
     * @example
     * // After failed login
     * if (!$authResult->success) {
     *     $loginllama->reportFailure($email, ['request' => $request]);
     * }
     */
    public function reportFailure(string $identityKey, array $options = []): array {
        $options['authenticationOutcome'] = AuthenticationOutcome::FAILED;
        return $this->check($identityKey, $options);
    }

    /**
     * Create middleware for frameworks
     *
     * This middleware stores request information in static context,
     * allowing check() to automatically access IP and User-Agent.
     *
     * @return callable Middleware function
     *
     * @example
     * // Laravel (Middleware class)
     * use LoginLlama\ContextDetector;
     *
     * class LoginLlamaMiddleware {
     *     public function handle($request, Closure $next) {
     *         ContextDetector::setContext($request);
     *         return $next($request);
     *     }
     * }
     *
     * @example
     * // Vanilla PHP
     * $loginllama = new LoginLlama\LoginLlama();
     * $loginllama->middleware()(); // Call middleware
     * $result = $loginllama->check($email); // Auto-detected
     */
    public function middleware(): callable {
        return function($request = null) {
            ContextDetector::setContext($request ?? $_SERVER);
        };
    }

    /**
     * Extract User-Agent from request
     *
     * @param mixed $request Request object or array
     * @return string|null User-Agent or null
     */
    private function extractUserAgent($request): ?string {
        if (is_array($request)) {
            return $request['HTTP_USER_AGENT'] ?? null;
        }
        if (is_object($request) && method_exists($request, 'header')) {
            return $request->header('User-Agent');
        }
        if (is_object($request) && method_exists($request, 'headers')) {
            return $request->headers->get('User-Agent');
        }
        return null;
    }
}
