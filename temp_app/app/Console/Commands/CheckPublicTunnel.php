<?php

namespace App\Console\Commands;

use App\Support\PublicPortal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CheckPublicTunnel extends Command
{
    protected $signature = 'imprint:tunnel-check';

    protected $description = 'Check the optional public tunnel without affecting internal operations';

    public function handle(PublicPortal $portal): int
    {
        $url = $portal->url();
        $online = false;

        if ($url) {
            try {
                $online = Http::timeout(5)->get(rtrim($url, '/').'/up')->successful();
            } catch (\Throwable) {
                $online = false;
            }
        }

        Cache::forever('public_tunnel_status', ['online' => $online, 'checked_at' => now()->toIso8601String()]);
        $this->line($online ? 'PUBLIC TUNNEL ONLINE' : 'PUBLIC TUNNEL OFFLINE');

        // Reporting a tunnel as down is this command working, not failing. It
        // used to exit non-zero, so the scheduler recorded a stack trace every
        // five minutes the tunnel was off — 315 of them in one day, which
        // buries anything that has actually gone wrong. The answer lives in the
        // cache, where the dashboard reads it.
        return self::SUCCESS;
    }
}
