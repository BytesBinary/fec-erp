<?php

use App\Services\Security\DeviceParser;

dataset('user agents', [
    'chrome windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'Chrome on Windows', 'desktop'],
    'edge windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0', 'Edge on Windows', 'desktop'],
    'firefox linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0', 'Firefox on Linux', 'desktop'],
    'safari mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', 'Safari on macOS', 'desktop'],
    'chrome android phone' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36', 'Chrome on Android', 'mobile'],
    'safari iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'Safari on iOS', 'mobile'],
    'safari ipad' => ['Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'Safari on iOS', 'tablet'],
    'headless chrome' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/126.0.0.0 Safari/537.36', 'Chrome on Linux', 'desktop'],
]);

it('derives a readable label and device type from a user agent', function (string $agent, string $label, string $type) {
    $parsed = (new DeviceParser)->parse($agent);

    expect($parsed['label'])->toBe($label)
        ->and($parsed['type'])->toBe($type);
})->with('user agents');

it('falls back to a neutral label for unknown or missing agents', function (?string $agent) {
    expect((new DeviceParser)->parse($agent)['label'])->toBe('Unknown device');
})->with([null, '', 'totally-custom-client']);

it('gives the same fingerprint to the same browser, OS and type only', function () {
    $parser = new DeviceParser;

    expect($parser->fingerprint('Chrome', 'Windows', 'desktop'))->toBe($parser->fingerprint('Chrome', 'Windows', 'desktop'))
        ->and($parser->fingerprint('Chrome', 'Windows', 'desktop'))->not->toBe($parser->fingerprint('Firefox', 'Windows', 'desktop'));
});
