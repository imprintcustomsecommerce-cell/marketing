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

        return $online ? self::SUCCESS : self::FAILURE;
    }
}
