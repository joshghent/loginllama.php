# LoginLlama PHP Client

This PHP package provides an interface to interact with the LoginLlama API, which offers login status checks for users based on various parameters.

## Installation

To install the LoginLlama PHP client, use Composer:

```bash
composer require joshghent/loginllama
```

## Usage

First, include the necessary classes:

```php
require_once 'vendor/autoload.php';
```

### Initialization

To initialize the `LoginLlama` class, provide your API token:

```php
$loginLlama = new LoginLlama("YOUR_API_TOKEN");
```

Alternatively set the `LOGINLLAMA_API_KEY` environment variable and initialize the class without any parameters:

```php
$loginLlama = new LoginLlama();
```

### Checking Login Status

The primary function provided by this package is `check_login`, which checks the login status of a user based on various parameters.

#### Parameters:

- `ip_address` (optional): The IP address of the user.
- `user_agent` (optional): The user agent string of the user.
- `identity_key`: The unique identity key for the user. This is a required parameter.

#### Return Value:

The function returns an array containing the result of the login check, including the status, a message, and any applicable codes indicating the reason for the status.

#### Examples:

```php
$result = $loginLlama->check_login([
    'ip_address' => '192.168.1.1',
    'user_agent' => 'Mozilla/5.0',
    'identity_key' => 'validUser'
]);
```

## Error Handling

The `check_login` function will throw exceptions for various error scenarios, such as missing required parameters or API errors.

## Contributing

If you find any issues or have suggestions for improvements, please open an issue or submit a pull request. Your contributions are welcome!

## License

This package is licensed under the [GNU GPL v3](LICENCE).
