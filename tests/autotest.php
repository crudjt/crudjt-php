<?php

namespace CRUDJT\Tests;

use CRUDJT\CRUDJT;

require __DIR__ . '/../vendor/autoload.php';

\CRUDJT\Config::startMaster([
  'encrypted_key' => 'Cm7B68NWsMNNYjzMDREacmpe5sI1o0g40ZC9w1yQW3WOes7Gm59UsittLOHR2dciYiwmaYq98l3tG8h9yXVCxg=='
]);

echo "OS: " . PHP_OS . PHP_EOL;
echo "CPU: " . php_uname('m') . PHP_EOL;

// without metadata
echo "Checking without metadata..." . PHP_EOL;

$data = ['user_id' => 42, 'role' => 11];
$expected_data = ['data' => $data];

$ed_data = ['user_id' => 42, 'role' => 8];
$expected_ed_data = ['data' => $ed_data];

$token = CRUDJT::create($data);

var_dump(CRUDJT::read($token) == $expected_data);
var_dump(CRUDJT::update($token, $ed_data) === true);
var_dump(CRUDJT::read($token) == $expected_ed_data);
var_dump(CRUDJT::delete($token) === true);
var_dump(CRUDJT::read($token) === null);

// with ttl
echo "Checking ttl..." . PHP_EOL;

$data = ['user_id' => 42, 'role' => 11];
$ttl = 5;
$token_with_ttl = CRUDJT::create($data, $ttl);

$expected_ttl = $ttl;
for ($i = 0; $i < $ttl; $i++) {
    $expected = ['metadata' => ['ttl' => $expected_ttl], 'data' => $data];
    var_dump(CRUDJT::read($token_with_ttl) == json_decode(json_encode($expected), true));
    $expected_ttl--;
    sleep(1);
}
var_dump(CRUDJT::read($token_with_ttl) === null);

// when expired ttl
echo "When expired ttl..." . PHP_EOL;

$data = ['user_id' => 42, 'role' => 11];
$ttl = 1;
$token = CRUDJT::create($data, $ttl);

sleep($ttl);

var_dump(CRUDJT::read($token) === null);
var_dump(CRUDJT::update($token, $data) === false);
var_dump(CRUDJT::delete($token) === false);
var_dump(CRUDJT::update($token, $data) === false);
var_dump(CRUDJT::read($token) === null);

// with silence w
echo "Checking silence_read..." . PHP_EOL;

$data = ['user_id' => 42, 'role' => 11];
$silence_read = 6;
$token_with_silence_read = CRUDJT::create($data, -1, $silence_read);

$expected_silence_read = $silence_read - 1;
for ($i = 0; $i < $silence_read; $i++) {
    $expected = ['metadata' => ['silence_read' => $expected_silence_read], 'data' => $data];
    var_dump(CRUDJT::read($token_with_silence_read) == json_decode(json_encode($expected), true));
    $expected_silence_read--;
}
var_dump(CRUDJT::read($token_with_silence_read) === null);

// with ttl and silence w
echo "Checking ttl and silence_read..." . PHP_EOL;

$data = ['user_id' => 42, 'role' => 11];
$ttl = 5;
$silence_read = $ttl;
$token_with_ttl_and_silence_read = CRUDJT::create($data, $ttl, $silence_read);

$expected_ttl = $ttl;
$expected_silence_read = $silence_read - 1;

for ($i = 0; $i < $silence_read; $i++) {
    $expected = ['metadata' => ['ttl' => $expected_ttl, 'silence_read' => $expected_silence_read], 'data' => $data];
    var_dump(CRUDJT::read($token_with_ttl_and_silence_read) == json_decode(json_encode($expected), true));
    $expected_ttl--;
    $expected_silence_read--;
    sleep(1);
}
var_dump(CRUDJT::read($token_with_ttl_and_silence_read) === null);

// with scale load
define('REQUESTS', 40000);

for ($j = 0; $j < 10; $j++) {
    echo "Checking scale load..." . PHP_EOL;
    $tokens = [];
    $data = [
        'user_id' => 414243,
        'role' => 11,
        'devices' => [
            'ios_expired_at' => date('c'),
            'android_expired_at' => date('c'),
            'mobile_app_expired_at' => date('c'),
            'external_api_integration_expired_at' => date('c')
        ],
        'a' => 42
    ];
    $ed_data = ['user_id' => 42, 'role' => 11];

    echo "when creates 40k tokens with Turbo Queue" . PHP_EOL;
    $start = microtime(true);
    for ($i = 0; $i < REQUESTS; $i++) {
        $tokens[] = CRUDJT::create($data);
    }
    echo "Elapsed: " . (microtime(true) - $start) . " seconds" . PHP_EOL;

    echo "when reads 40k tokens" . PHP_EOL;
    $index = rand(0, REQUESTS - 1);
    $start = microtime(true);
    for ($i = 0; $i < REQUESTS; $i++) {
        CRUDJT::read($tokens[$index]);
    }
    echo "Elapsed: " . (microtime(true) - $start) . " seconds" . PHP_EOL;

    echo "when updates 40k tokens" . PHP_EOL;
    $start = microtime(true);
    for ($i = 0; $i < REQUESTS; $i++) {
        CRUDJT::update($tokens[$i], $ed_data);
    }
    echo "Elapsed: " . (microtime(true) - $start) . " seconds" . PHP_EOL;

    echo "when deletes 40k tokens" . PHP_EOL;
    $start = microtime(true);
    for ($i = 0; $i < REQUESTS; $i++) {
        CRUDJT::delete($tokens[$i]);
    }
    echo "Elapsed: " . (microtime(true) - $start) . " seconds" . PHP_EOL;
}

// when cache after read from file system
echo "when caches after read from file system" . PHP_EOL;

define('LIMIT_ON_wY_FOR_CACHE', 2);

$data = [
    'user_id' => 414243,
    'role' => 11,
    'devices' => [
        'ios_expired_at' => date('c'),
        'android_expired_at' => date('c'),
        'mobile_app_expired_at' => date('c'),
        'external_api_integration_expired_at' => date('c')
    ],
    'a' => 42
];
$previous_tokens = [];

for ($i = 0; $i < REQUESTS; $i++) {
    $previous_tokens[] = CRUDJT::create($data);
}
for ($i = 0; $i < REQUESTS; $i++) {
    CRUDJT::create($data);
}

for ($i = 0; $i < LIMIT_ON_wY_FOR_CACHE; $i++) {
    $start = microtime(true);
    for ($j = 0; $j < REQUESTS; $j++) {
        CRUDJT::read($previous_tokens[$j]);
    }
    echo "Elapsed: " . (microtime(true) - $start) . " seconds" . PHP_EOL;
}
