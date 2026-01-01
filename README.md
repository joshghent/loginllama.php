# LoginLlama PHP Client

Official PHP SDK for [LoginLlama](https://loginllama.app) - AI-powered login security and fraud detection.

## Features

- **Automatic Context Detection**: Auto-detects IP address and User-Agent from Laravel, Symfony, WordPress, and vanilla PHP
- **Multi-Source IP Extraction**: Supports X-Forwarded-For, CF-Connecting-IP, X-Real-IP, True-Client-IP with private IP filtering
- **Framework Support**: Works with Laravel, Symfony, WordPress, and vanilla PHP
- **Middleware Integration**: Drop-in middleware for Laravel and Symfony
- **Webhook Verification**: Built-in HMAC signature verification

## Installation

```bash
composer require joshghent/loginllama:^2.0
```

Requires PHP 8.3 or higher.

## Quick Start

### Vanilla PHP

The simplest way to use LoginLlama in vanilla PHP - IP and User-Agent are automatically detected from `$_SERVER`:

```php
<?php
require_once 'vendor/autoload.php';

use LoginLlama\LoginLlama;

$loginllama = new LoginLlama('your-api-key');

// IP and User-Agent are automatically detected from $_SERVER!
$result = $loginllama->check($_POST['email']);

if ($result['status'] === 'error' || $result['risk_score'] > 5) {
    error_log('Suspicious login blocked: ' . implode(', ', $result['codes']));
    http_response_code(403);
    echo json_encode(['error' => 'Login blocked']);
    exit;
}

// Continue with login...
echo json_encode(['success' => true]);
```

### With Request Object

If you have a framework request object, you can pass it explicitly:

```php
$result = $loginllama->check(
    $_POST['email'],
    ['request' => $request]
);
```

### Manual Override

Or provide IP and User-Agent manually:

```php
$result = $loginllama->check('user@example.com', [
    'ipAddress' => '203.0.113.42',
    'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)...'
]);
```

## Framework Examples

### Laravel

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use LoginLlama\LoginLlama;

class AuthController extends Controller
{
    private LoginLlama $loginllama;

    public function __construct()
    {
        $this->loginllama = new LoginLlama(env('LOGINLLAMA_API_KEY'));
    }

    public function login(Request $request)
    {
        $email = $request->input('email');

        // Pass Laravel request explicitly for auto-detection
        $result = $this->loginllama->check($email, [
            'request' => $request,
            'geoCountry' => 'US',
            'geoCity' => 'San Francisco'
        ]);

        if ($result['risk_score'] > 5) {
            return response()->json([
                'error' => 'Suspicious login'
            ], 403);
        }

        return response()->json(['success' => true]);
    }
}
```

### Symfony

```php
<?php

namespace App\Controller;

use LoginLlama\LoginLlama;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    private LoginLlama $loginllama;

    public function __construct()
    {
        $this->loginllama = new LoginLlama($_ENV['LOGINLLAMA_API_KEY']);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $email = $request->request->get('email');

        // Pass Symfony request explicitly for auto-detection
        $result = $this->loginllama->check($email, [
            'request' => $request
        ]);

        if ($result['risk_score'] > 5) {
            return $this->json([
                'error' => 'Suspicious login'
            ], 403);
        }

        return $this->json(['success' => true]);
    }
}
```

### WordPress

```php
<?php
// In your theme's functions.php or a custom plugin

add_action('wp_login', function($user_login, $user) {
    require_once ABSPATH . 'vendor/autoload.php';

    $loginllama = new \LoginLlama\LoginLlama(get_option('loginllama_api_key'));

    try {
        // IP and User-Agent are auto-detected from $_SERVER
        $result = $loginllama->check($user_login);

        if ($result['risk_score'] > 5) {
            // Log suspicious login
            error_log("Suspicious login for user: {$user_login}, risk: {$result['risk_score']}");

            // Optionally block the login
            wp_logout();
            wp_die('Login blocked due to suspicious activity');
        }
    } catch (Exception $e) {
        error_log('LoginLlama error: ' . $e->getMessage());
        // Fail open - allow login to proceed
    }
}, 10, 2);
```

## API Reference

### `new LoginLlama($apiKey, $baseUrl = null)`

Create a new LoginLlama client.

**Parameters:**
- `$apiKey` (required): Your API key from LoginLlama dashboard
- `$baseUrl` (optional): Custom API endpoint for testing

```php
use LoginLlama\LoginLlama;

$loginllama = new LoginLlama('your-api-key');
```

### `$loginllama->check($identityKey, $options = [])`

Check a login attempt for suspicious activity.

**Parameters:**
- `$identityKey` (required): User identifier (email, username, user ID, etc.)
- `$options` (optional array):
  - `'ipAddress'`: Override auto-detected IP address
  - `'userAgent'`: Override auto-detected User-Agent
  - `'request'`: Explicit request object (Laravel, Symfony)
  - `'emailAddress'`: User's email address for additional verification
  - `'geoCountry'`: ISO country code (e.g., 'US', 'GB')
  - `'geoCity'`: City name for additional context
  - `'userTimeOfDay'`: Time of login attempt

**Returns:** Associative array

```php
[
    'status' => 'success' | 'error',
    'message' => 'string',
    'codes' => ['VALID'] | ['IP_ADDRESS_SUSPICIOUS', ...],
    'risk_score' => 0-10, // integer
    'environment' => 'production' | 'staging',
    'meta' => [...] // optional
]
```

**Detection Priority:**
1. Explicit `'ipAddress'` and `'userAgent'` in options array
2. Extract from `'request'` object if provided
3. Use stored context from middleware (if used)
4. Fallback to `$_SERVER` superglobal

**Examples:**

```php
// Auto-detect from $_SERVER
$result = $loginllama->check('user@example.com');

// Pass request explicitly (Laravel/Symfony)
$result = $loginllama->check('user@example.com', [
    'request' => $request
]);

// Manual override
$result = $loginllama->check('user@example.com', [
    'ipAddress' => '203.0.113.42',
    'userAgent' => 'Mozilla/5.0...'
]);

// With additional context
$result = $loginllama->check('user@example.com', [
    'emailAddress' => 'user@example.com',
    'geoCountry' => 'US',
    'geoCity' => 'San Francisco'
]);
```

### `$loginllama->middleware()`

Returns middleware callable for Laravel/Symfony that automatically captures request context.

**Laravel Middleware:**
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LoginLlama\LoginLlama;

class LoginLlamaMiddleware
{
    private LoginLlama $loginllama;

    public function __construct()
    {
        $this->loginllama = new LoginLlama(env('LOGINLLAMA_API_KEY'));
    }

    public function handle(Request $request, Closure $next)
    {
        $middleware = $this->loginllama->middleware();
        $middleware($request);
        return $next($request);
    }
}
```

**Symfony Event Subscriber:**
```php
<?php

namespace App\EventSubscriber;

use LoginLlama\LoginLlama;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LoginLlamaSubscriber implements EventSubscriberInterface
{
    private LoginLlama $loginllama;

    public function __construct()
    {
        $this->loginllama = new LoginLlama($_ENV['LOGINLLAMA_API_KEY']);
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $middleware = $this->loginllama->middleware();
        $middleware($event->getRequest());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
```

### `LoginLlama::verifyWebhookSignature($payload, $signature, $secret)`

Verify webhook signature using constant-time HMAC comparison.

**Parameters:**
- `$payload`: Raw webhook body (string)
- `$signature`: Value from `X-LoginLlama-Signature` header
- `$secret`: Webhook secret from LoginLlama dashboard

**Returns:** `bool`

```php
use LoginLlama\LoginLlama;

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_LOGINLLAMA_SIGNATURE'] ?? '';
$secret = getenv('WEBHOOK_SECRET');

if (!LoginLlama::verifyWebhookSignature($payload, $signature, $secret)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
// Handle event...
http_response_code(200);
```

## Login Status Codes

The SDK provides constants for all possible status codes:

```php
use LoginLlama\LoginCheckStatus;

// Example status codes:
LoginCheckStatus::VALID
LoginCheckStatus::IP_ADDRESS_SUSPICIOUS
LoginCheckStatus::KNOWN_BOT
LoginCheckStatus::GEO_IMPOSSIBLE_TRAVEL
LoginCheckStatus::USER_AGENT_SUSPICIOUS
// ... and more
```

## Error Handling

The SDK will throw `\Exception` if required parameters are missing:

```php
use LoginLlama\LoginLlama;

$loginllama = new LoginLlama('your-api-key');

try {
    $result = $loginllama->check('user@example.com');
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'IP address could not be detected')) {
        // No IP available - pass ipAddress or request explicitly
    }
    error_log('LoginLlama error: ' . $e->getMessage());
}
```

**Best Practice:** Fail open on errors to avoid blocking legitimate users during API outages:

```php
try {
    $result = $loginllama->check($email);

    if ($result['risk_score'] > 5) {
        // Block suspicious login
        http_response_code(403);
        echo json_encode(['error' => 'Login blocked']);
        exit;
    }
} catch (\Exception $e) {
    error_log('LoginLlama error: ' . $e->getMessage());
    // Fail open - allow login to proceed
}
```

## IP Detection

The SDK automatically detects IP addresses from multiple sources with priority fallback:

1. **HTTP_X_FORWARDED_FOR** - Parses chain, takes first public IP (filters private IPs)
2. **HTTP_CF_CONNECTING_IP** - Cloudflare real client IP
3. **HTTP_X_REAL_IP** - nginx proxy header
4. **HTTP_TRUE_CLIENT_IP** - Akamai/Cloudflare header
5. **REMOTE_ADDR** - Direct connection IP

**Private IP Filtering:** Automatically filters `10.x.x.x`, `172.16-31.x.x`, `192.168.x.x`, `127.x.x.x`, `::1`, `fc00::/7`, `fe80::/10`

## Environment Variables

You can set your API key as an environment variable:

```bash
export LOGINLLAMA_API_KEY=your-api-key
```

Then initialize without arguments:

```php
$loginllama = new LoginLlama(getenv('LOGINLLAMA_API_KEY'));
```

## Contributing

Contributions are welcome! Please open an issue or submit a pull request on [GitHub](https://github.com/joshghent/loginllama.php).

## License

GNU GPL v3

## Support

- Documentation: [loginllama.app/docs](https://loginllama.app/docs)
- Dashboard: [loginllama.app/dashboard](https://loginllama.app/dashboard)
- Issues: [GitHub Issues](https://github.com/joshghent/loginllama.php/issues)
