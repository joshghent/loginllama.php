<?php

namespace LoginLlama;

/**
 * Extracts IP address from request with multi-source priority fallback
 * and private IP filtering for proxy/CDN scenarios.
 */
class IPExtractor {
    private const PRIVATE_IP_PATTERNS = [
        '/^10\./',
        '/^172\.(1[6-9]|2\d|3[01])\./',
        '/^192\.168\./',
        '/^127\./',
        '/^::1$/',
        '/^fc00:/',
        '/^fe80:/',
    ];

    /**
     * Extract IP address from request with priority fallback
     *
     * Priority order:
     * 1. X-Forwarded-For (first non-private IP)
     * 2. CF-Connecting-IP (Cloudflare)
     * 3. X-Real-IP (nginx)
     * 4. True-Client-IP (Akamai/Cloudflare)
     * 5. Direct connection IP
     *
     * @param mixed $request Request object or $_SERVER array
     * @return string|null IP address or null
     */
    public static function extract($request): ?string {
        if ($request === null) {
            $request = $_SERVER;
        }

        // Priority 1: X-Forwarded-For
        $xForwardedFor = self::getHeader($request, 'X-Forwarded-For');
        if ($xForwardedFor) {
            $ip = self::parseForwardedFor($xForwardedFor);
            if ($ip) return $ip;
        }

        // Priority 2: CF-Connecting-IP
        $cfIP = self::getHeader($request, 'CF-Connecting-IP');
        if ($cfIP && self::isValidPublicIP($cfIP)) return $cfIP;

        // Priority 3: X-Real-IP
        $realIP = self::getHeader($request, 'X-Real-IP');
        if ($realIP && self::isValidPublicIP($realIP)) return $realIP;

        // Priority 4: True-Client-IP
        $trueClientIP = self::getHeader($request, 'True-Client-IP');
        if ($trueClientIP && self::isValidPublicIP($trueClientIP)) return $trueClientIP;

        // Priority 5: Direct connection
        return self::getDirectIP($request);
    }

    /**
     * Parse X-Forwarded-For header and return first public IP
     *
     * @param string $header X-Forwarded-For header value
     * @return string|null First public IP or null
     */
    private static function parseForwardedFor(string $header): ?string {
        $ips = array_map('trim', explode(',', $header));
        foreach ($ips as $ip) {
            if (self::isValidPublicIP($ip)) {
                return $ip;
            }
        }
        return null;
    }

    /**
     * Check if IP is valid and public (not private/local)
     *
     * @param string $ip IP address
     * @return bool True if valid public IP
     */
    private static function isValidPublicIP(string $ip): bool {
        if (!self::isValidIP($ip)) return false;

        foreach (self::PRIVATE_IP_PATTERNS as $pattern) {
            if (preg_match($pattern, $ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate IPv4 or IPv6 address format
     *
     * @param string $ip IP address
     * @return bool True if valid IP format
     */
    private static function isValidIP(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Get header from request (framework-agnostic)
     *
     * @param mixed $request Request object or $_SERVER array
     * @param string $name Header name
     * @return string|null Header value or null
     */
    private static function getHeader($request, string $name): ?string {
        // Array ($_SERVER)
        if (is_array($request)) {
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            return $request[$serverKey] ?? null;
        }

        // Laravel Request
        if (is_object($request) && method_exists($request, 'header')) {
            return $request->header($name);
        }

        // Symfony Request
        if (is_object($request) && method_exists($request, 'headers')) {
            return $request->headers->get($name);
        }

        return null;
    }

    /**
     * Get direct connection IP
     *
     * @param mixed $request Request object or $_SERVER array
     * @return string|null Direct IP or null
     */
    private static function getDirectIP($request): ?string {
        // Array ($_SERVER)
        if (is_array($request)) {
            return $request['REMOTE_ADDR'] ?? null;
        }

        // Laravel Request
        if (is_object($request) && method_exists($request, 'ip')) {
            return $request->ip();
        }

        // Symfony Request
        if (is_object($request) && method_exists($request, 'getClientIp')) {
            return $request->getClientIp();
        }

        return null;
    }
}
