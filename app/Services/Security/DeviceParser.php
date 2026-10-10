<?php

namespace App\Services\Security;

/**
 * Turns a user-agent string into a short device label such as
 * "Chrome on Windows" (spec §3A.1). Order matters: Edge and Opera also
 * announce themselves as Chrome, and Chrome announces itself as Safari.
 */
class DeviceParser
{
    /**
     * @return array{label: string, browser: string, os: string, type: string}
     */
    public function parse(?string $userAgent): array
    {
        $userAgent = (string) $userAgent;

        $browser = $this->browser($userAgent);
        $os = $this->os($userAgent);
        $type = $this->type($userAgent);

        $label = match (true) {
            $browser === 'Unknown browser' && $os === 'Unknown OS' => 'Unknown device',
            $os === 'Unknown OS' => $browser,
            default => "{$browser} on {$os}",
        };

        return ['label' => $label, 'browser' => $browser, 'os' => $os, 'type' => $type];
    }

    public function fingerprint(?string $browser, ?string $os, ?string $type): string
    {
        return hash('sha256', implode('|', [$browser, $os, $type]));
    }

    protected function browser(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/'), str_contains($userAgent, 'EdgA/'), str_contains($userAgent, 'EdgiOS/') => 'Edge',
            str_contains($userAgent, 'OPR/'), str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($userAgent, 'Firefox/'), str_contains($userAgent, 'FxiOS/') => 'Firefox',
            str_contains($userAgent, 'CriOS/'), str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') && str_contains($userAgent, 'Version/') => 'Safari',
            str_contains($userAgent, 'curl/') => 'curl',
            default => 'Unknown browser',
        };
    }

    protected function os(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad'), str_contains($userAgent, 'iPod') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'CrOS') => 'ChromeOS',
            str_contains($userAgent, 'Mac OS X'), str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux'), str_contains($userAgent, 'X11') => 'Linux',
            default => 'Unknown OS',
        };
    }

    protected function type(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'iPad'), str_contains($userAgent, 'Tablet') => 'tablet',
            str_contains($userAgent, 'Android') && ! str_contains($userAgent, 'Mobile') => 'tablet',
            str_contains($userAgent, 'Mobile'), str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'Android') => 'mobile',
            default => 'desktop',
        };
    }
}
