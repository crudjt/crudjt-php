<p align="center">
  <img src="logos/crud_jt_logo_black.png#gh-light-mode-only" alt="Logo Light" />
  <img src="logos/crud_jt_logo.png#gh-dark-mode-only" alt="Logo Dark" />
</p>

<p align="center">
  Fast, file-backed JSON token for REST APIs with multi-process support
</p>

<p align="center">
  <a href="https://www.patreon.com/crudjt">
    <img src="logos/buy_me_a_coffee_orange.svg" alt="Buy Me a Coffee"/>
  </a>
</p>

## Why?  
[Escape the JWT trap: predictable login, safe logout](https://medium.com/@CoffeeMainer/jwt-trap-login-logout-under-control-7f4495d6024d)

CRUDJT runs a small local coordinator inside your app.
One process acts as a leader, all others talk to it

## In short

CRUDJT gives you stateful sessions without JWT pain and without distributed complexity

# Installation

Install via Composer

```sh
composer require crudjt/crudjt-php
```

## How it works (PHP)

`startMaster()` runs the master in a single PHP process

For multi-process or distributed setups, start the master in  
[another supported runtime](https://github.com/orgs/Cm7B68NWsMNNYjzMDREacmpe5sI1o0g40ZC9w1y/repositories)  
and connect from PHP using `connectToMaster()`

## Start CRUDJT master (PHP)

Start the CRUDJT master when your application boots  

Only **one process** should do this  
The master is responsible for session state

### Generate an encrypted key

```sh
export CRUDJT_ENCRYPTED_KEY=$(openssl rand -base64 48)
```

```php
use CRUDJT\CRUDJT;

\CRUDJT\Config::startMaster([
  'encrypted_key' => getenv('CRUDJT_ENCRYPTED_KEY'),
  'store_jt_path' => 'path/to/local/storage'
]);
```

The encrypted key must be the same for all processes

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
$data = ['user_id' => 42, 'role' => 11];

// To disable token expiration or read limits, pass `-1`
$token = CRUDJT::create(
    $data,
    -1, // disable TTL
    -1 // disable read limit
);
```

# R

```php
$result = CRUDJT::read("HBmKFXoXgJ46mCqer1WXyQ");
// $result == array(1) { ["data"]=> array(2) { ["user_id"]=> int(42) ["role"]=> int(11) } }
```

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

// -1 disables limits
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
**40k** requests of **256 bytes** — median over 10 runs  
ARM64 (Apple M1+), macOS 15.6.1  
PHP 8.4.12

Measured in the master process (in-process execution)  
No gRPC, network, or serialization overhead is included

| Function | CRUDJT (PHP) | JWT (PHP) | redis-session-store (Ruby, Rails 8.0.4) |
|----------|-------|------|------|
| C        | 0.356 second | 0.294 second ⭐ | 4.057 seconds |
| R        | `0.016 second` ![Logo Favicon Light](logos/crud_jt_logo_favicon_white.png#gh-light-mode-only) ![Logo Favicon Dark](logos/crud_jt_logo_favicon_black.png#gh-dark-mode-only) | 0.344 second | 7.011 seconds |
| U        | `0.468 second` ![Logo Favicon Light](logos/crud_jt_logo_favicon_white.png#gh-light-mode-only) ![Logo Favicon Dark](logos/crud_jt_logo_favicon_black.png#gh-dark-mode-only) | X | 3.49 seconds |
| D        | `0.198 second` ![Logo Favicon Light](logos/crud_jt_logo_favicon_white.png#gh-light-mode-only) ![Logo Favicon Dark](logos/crud_jt_logo_favicon_black.png#gh-dark-mode-only) | X | 6.589 seconds |

[Full benchmark results](https://github.com/exwarvlad/benchmarks)

# Storage (File-backed)  
Backed by a disk-based B-tree for predictable reads, writes, and deletes

## Disk footprint  
**40k** tokens of **256 bytes** each — median over 10 creates  
darwin23, APFS  

`48 MB`  

[Full disk footprint results](https://github.com/Cm7B68NWsMNNYjzMDREacmpe5sI1o0g40ZC9w1y/disk_footprint)

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

# Limits
The library has the following limits and requirements

- **PHP version:** tested with 8.2.30
- **Supported platforms:** Linux, macOS, Windows (x86_64 / arm64)
- **Maximum json size per token:** 256 bytes
- **`encrypted_key` format:** must be Base64
- **`encrypted_key` size:** must be 32, 48, or 64 bytes

# Contact & Support
<p align="center">
  <img src="logos/crud_jt_logo_favicon_black_160.png#gh-light-mode-only" alt="Visit Light" />
  <img src="logos/crud_jt_logo_favicon_white_160.png#gh-dark-mode-only" alt="Visit Dark" />
</p>

- **Custom integrations / new features / collaboration**: support@crudjt.com  
- **Library support & bug reports:** [open an issue](https://github.com/crudjt/crudjt-php/issues)


# Lincense
CRUDJT is released under the [MIT License](LICENSE.txt)

<p align="center">
  💘 Shoot your g . ? Love me out via <a href="https://www.patreon.com/crudjt">Patreon Sponsors</a>!
</p>
