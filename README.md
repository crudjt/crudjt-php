<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="logos/crudjt_logo_white_on_dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="logos/crudjt_logo_dark_on_white.svg">
    <img alt="Shows a dark logo" src="logos/crudjt_logo_dark.png">
  </picture>
    </br>
    PHP SDK for the fast, file-backed, scalable JSON token engine
</p>

<p align="center">
  <a href="https://www.patreon.com/crudjt">
    <img src="logos/buy_me_a_coffee_orange.svg" alt="Buy Me a Coffee"/>
  </a>
</p>

> ⚠️ Version 1.0.0-beta — production testing phase   
> API is stable. Feedback is welcome before the final 1.0.0 release

Fast B-tree–backed token store for stateful user sessions  
Provides authentication and authorization across multiple processes  
Optimized for vertical scaling on a single server  

# Installation

Composer:

```sh
composer require crudjt/crudjt-php:^1.0@beta
```

## Start CRUDJT master (once)

`startMaster()` runs the master in a single PHP process

For multi-process or distributed setups, start the master in  
[another supported runtime](https://github.com/orgs/crudjt/repositories)  
and connect from PHP using `connectToMaster()`

## Start CRUDJT master

Start the CRUDJT master when your application boots  

Only **one process** can do this for a **single token storage**  

The master is responsible for session state
All functions can also be used directly from it

### Generate a new secret key (terminal)

```sh
export CRUDJT_SECRET_KEY=$(openssl rand -base64 48)
```

### Start master (php)
```php
use CRUDJT\CRUDJT;

\CRUDJT\Config::startMaster([
  'secret_key' => getenv('CRUDJT_SECRET_KEY'),
  'store_jt_path' => 'path/to/local/storage'
]);
```

*Important: Use the same `SecretKey` across all sessions. If the key changes, previously stored tokens cannot be decrypted and will return `nil` or `false`*  

## Connect to an existing CRUDJT master

Use this in all other processes  

Typical examples:
- multiple local processes
- background jobs
- forked processes

```php
use CRUDJT\CRUDJT;

CRUDJT.Config.connectToMaster([
  'grpc_host' => '127.0.0.1', // default
  'grpc_port' => 50051 // default
]);
```

### Process layout

App boot  
 ├─ Process A → start_master  
 ├─ Process B → connect_to_master  
 └─ Process C → connect_to_master  

# C

```php
$data = ['user_id' => 42, 'role' => 11]; // required
$ttl = 3600 * 24 * 30; // optional: token lifetime (seconds)

// Optional: read limit
// Each read decrements the counter
// When it reaches zero — the token is deleted
$silenceRead = 10;

$token = CRUDJT::create($data, $ttl, $silenceRead);
// $token == string(22) "HBmKFXoXgJ46mCqer1WXyQ"
```

```php
// To disable token expiration or read limits, pass `null`
$token = CRUDJT::create(
    ['user_id' => 42, 'role' => 11],
    null, // disable TTL
    null // disable read limit
);
```

# R

```php
$result = CRUDJT.read('HBmKFXoXgJ46mCqer1WXyQ');
// result == array(2) { ["metadata"]=> array(2) { ["ttl"]=> int(101001) ["silence_read"]=> int(9) } ["data"]=> array(2) { ["user_id"]=> int(42) ["role"]=> int(11) } }
```

```php
// When expired or not found token
$result = CRUDJT.read('HBmKFXoXgJ46mCqer1WXyQ');
// result == NULL
```

# U

```php
$data = ['user_id' => 42, 'role' => 8];
// `null` disables limits
$ttl = 600;
$silenceRead = 100;

$result = CRUDJT::update("HBmKFXoXgJ46mCqer1WXyQ", ['user_id' => 42, 'role' => 8]);
// $result == bool(true) # array(1) { ["data"]=> array(2) { ["user_id"]=> int(42) ["role"]=> int(8) } }
```

```php
// When expired or not found token
$result = CRUDJT::update("HBmKFXoXgJ46mCqer1WXyQ", $data, $ttl, $silenceRead);
// $result == bool(false)
```

# D
```php
$result = CRUDJT::delete("HBmKFXoXgJ46mCqer1WXyQ");
// $result == bool(true)
```

```php
// when expired or not found token
$result = CRUDJT::delete("HBmKFXoXgJ46mCqer1WXyQ");
// $result == NULL
```

# Performance
> Metrics will be published after 1.0.0-beta GitHub Actions builds

# Storage (File-backed)  

## Disk footprint  
> Metrics will be published after 1.0.0-beta GitHub Actions builds

## Path Lookup Order
Stored tokens are placed in the **file system** according to the following order

1. Explicitly set via `\CRUDJT\Config::startMaster(['store_jt_path' => 'custom/path/to/file_system_db']);`
2. Default system location
   - **Linux**: `/var/lib/store_jt`
   - **macOS**: `/usr/local/var/store_jt`
   - **Windows**: `C:\Program Files\store_jt`
3. Project root directory (fallback)

## Storage Characteristics
* CRUDJT **automatically removing expired tokens** after start and every 24 hours without blocking the main thread   
* **Storage automatically fsyncs every 500ms**, meanwhile tokens ​​are available from cache

# Multi-process Coordination
For multi-process scenarios, CRUDJT uses gRPC over an insecure local port for same-host communication only. It is not intended for inter-machine or internet-facing usage

# Limits
The library has the following limits and requirements

- **PHP version:** tested with 8.2.30
- **Supported platforms:** Linux, macOS, Windows (x86_64 / arm64)
- **Maximum json size per token:** 256 bytes
- **`secret_key` format:** must be Base64
- **`secret_key` size:** must be 32, 48, or 64 bytes

# Contact & Support
<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="logos/crudjt_favicon_160x160_white_on_dark.svg" width=160 height=160>
    <source media="(prefers-color-scheme: light)" srcset="logos/crudjt_favicon_160x160_dark_on_white.svg" width=160 height=160>
    <img alt="Shows a dark favicon in light color mode and a white one in dark color mode" src="logos/crudjt_favicon_160x160_white.png" width=160 height=160>
  </picture>
</p>

- **Custom integrations / new features / collaboration**: support@crudjt.com  
- **Library support & bug reports:** [open an issue](https://github.com/crudjt/crudjt-php/issues)

# Lincense
CRUDJT is released under the [MIT License](LICENSE.txt)

<p align="center">
  💘 Shoot your g . ? Love me out via <a href="https://www.patreon.com/crudjt">Patreon Sponsors</a>!
</p>
