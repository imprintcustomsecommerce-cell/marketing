<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The shop's Shopify store, as far as Imprint Hub needs it.
 *
 * A Dev Dashboard app has no long-lived token to store. It trades its client id
 * and secret for one that lasts a day, so the token is fetched on demand and
 * kept in the cache until shortly before it expires — asking for a new one on
 * every call would work, but it is a round trip nobody needs.
 */
class ShopifyStore
{
    private const TOKEN_CACHE_KEY = 'shopify.admin_token';

    /**
     * Renewed a few minutes early.
     *
     * A token that expires mid-sync fails halfway through, leaving the calendar
     * part-written, so the margin is worth more than the extra request.
     */
    private const RENEW_MARGIN_SECONDS = 300;

    public function isConfigured(): bool
    {
        return filled($this->setting('domain'))
            && filled($this->setting('client_id'))
            && filled($this->setting('client_secret'));
    }

    public function domain(): ?string
    {
        return $this->setting('domain');
    }

    public function metaobjectType(): string
    {
        return $this->setting('event_metaobject_type') ?: 'event';
    }

    /**
     * Run a GraphQL query against the Admin API.
     *
     * Shopify answers 200 with an errors array rather than an HTTP error, so a
     * failure has to be dug out of the body rather than trusted to the status.
     *
     * @param  array<string,mixed>  $variables
     * @return array<string,mixed>
     */
    public function graphql(string $query, array $variables = []): array
    {
        $response = $this->request()->post(
            sprintf('https://%s/admin/api/%s/graphql.json', $this->setting('domain'), $this->setting('api_version')),
            // An empty PHP array encodes as [] rather than {}, and Shopify
            // rejects that outright, so a query without variables sends none.
            $variables === [] ? ['query' => $query] : ['query' => $query, 'variables' => $variables],
        );

        if ($response->failed()) {
            throw new RuntimeException('Shopify refused the request: '.$response->status().' '.$response->body());
        }

        $body = $response->json();

        if (! empty($body['errors'])) {
            throw new RuntimeException('Shopify returned errors: '.json_encode($body['errors']));
        }

        return $body['data'] ?? [];
    }

    /** Forget the cached token, so the next call fetches a fresh one. */
    public function forgetToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders(['X-Shopify-Access-Token' => $this->token()])
            ->acceptJson()
            ->timeout(30)
            // Shopify throttles by cost, and a shop connection can simply drop.
            // Two retries turn a blip into a slower sync rather than a failed one.
            ->retry(2, 1500, throw: false);
    }

    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->timeout(20)->post(
            sprintf('https://%s/admin/oauth/access_token', $this->setting('domain')),
            [
                'grant_type' => 'client_credentials',
                'client_id' => $this->setting('client_id'),
                'client_secret' => $this->setting('client_secret'),
            ],
        );

        // Deliberately vague: the body of a failed token request can echo the
        // credentials back, and this ends up in the log.
        throw_unless(
            $response->successful() && filled($response->json('access_token')),
            RuntimeException::class,
            'Shopify would not issue an access token ('.$response->status().'). Check SHOPIFY_CLIENT_ID and SHOPIFY_CLIENT_SECRET, and that the app is installed on '.$this->setting('domain').'.',
        );

        $token = $response->json('access_token');
        $lifetime = max(60, (int) $response->json('expires_in', 86399) - self::RENEW_MARGIN_SECONDS);

        Cache::put(self::TOKEN_CACHE_KEY, $token, $lifetime);

        return $token;
    }

    private function setting(string $key): ?string
    {
        $value = config("services.shopify.$key");

        return is_string($value) ? trim($value) : null;
    }
}
