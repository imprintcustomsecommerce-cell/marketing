<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class PublicPortal
{
    public function url(): string
    {
        $runtimeUrl = $this->runtimeUrl();

        return rtrim($runtimeUrl ?: (string) (config('imprint.public_url') ?: url('/')), '/');
    }

    public function host(): ?string
    {
        $runtimeUrl = $this->runtimeUrl();
        if ($runtimeUrl) {
            return parse_url($runtimeUrl, PHP_URL_HOST) ?: null;
        }

        return config('imprint.public_host') ?: null;
    }

    public function tunnelIsActive(): bool
    {
        return $this->runtimeUrl() !== null || str_contains((string) config('imprint.public_url'), 'trycloudflare.com');
    }

    public function startedAt(): ?CarbonImmutable
    {
        $path = storage_path('app/public-tunnel-url.txt');

        return is_file($path) ? CarbonImmutable::createFromTimestamp(filemtime($path)) : null;
    }

    private function runtimeUrl(): ?string
    {
        if (app()->runningUnitTests()) {
            return null;
        }

        $path = storage_path('app/public-tunnel-url.txt');
        if (! is_file($path)) {
            return null;
        }

        $url = trim((string) file_get_contents($path));

        return filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://') ? $url : null;
    }
}
