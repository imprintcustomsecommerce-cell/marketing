<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\ShopifyStore;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Push the published events to the website's calendar.
 *
 * The storefront reads Shopify, not this machine, so the calendar keeps working
 * when the shop PC is off — it simply stops receiving updates until the next
 * run. That is the whole reason for pushing rather than letting the website call
 * in over the tunnel.
 *
 * Entries are keyed by the event's own id, so a second run updates what is
 * already there instead of piling up duplicates.
 */
class SyncShopifyCalendar extends Command
{
    protected $signature = 'imprint:shopify-calendar
        {--dry-run : Show what would change without touching Shopify}';

    protected $description = 'Send events marked for the website to the Shopify calendar';

    /** The fields written to each entry, in the order the definition lists them. */
    private const FIELDS = ['title', 'date', 'end_date', 'start_time', 'end_time', 'venue', 'organization', 'type_label', 'summary'];

    public function handle(ShopifyStore $shopify): int
    {
        if (! $shopify->isConfigured()) {
            $this->components->error('Shopify is not set up. Add SHOPIFY_STORE_DOMAIN, SHOPIFY_CLIENT_ID, and SHOPIFY_CLIENT_SECRET to .env.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $type = $shopify->metaobjectType();

        $wanted = Event::publiclyListed()
            ->orderBy('event_date')
            ->get()
            ->keyBy(fn (Event $event) => $this->handleFor($event));

        try {
            $existing = $this->existingEntries($shopify, $type);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('  %d published %s here, %d on the website.',
            $wanted->count(), str('event')->plural($wanted->count()), count($existing)));

        $written = 0;
        $removed = 0;

        foreach ($wanted as $handle => $event) {
            $fields = $this->fieldsFor($event);

            // A draft left over from before the status was set has to be
            // rewritten even when every field already matches, or it stays
            // invisible to the storefront for good.
            if (isset($existing[$handle])
                && $existing[$handle]['status'] === 'ACTIVE'
                && $this->matches($existing[$handle]['fields'], $fields)) {
                continue;
            }

            $verb = isset($existing[$handle]) ? 'update' : 'add';
            $this->line("  {$verb}: {$event->name}");

            if (! $dryRun) {
                $this->upsert($shopify, $type, $handle, $fields, $existing[$handle]['id'] ?? null);
            }

            $written++;
        }

        // Anything on the website that is no longer published here — unticked,
        // cancelled, archived, or deleted outright — has to come down, or the
        // storefront advertises an event that is not happening.
        foreach ($existing as $handle => $entry) {
            if ($wanted->has($handle)) {
                continue;
            }

            $this->line("  remove: {$handle}");

            if (! $dryRun) {
                $shopify->graphql(
                    'mutation($id: ID!) { metaobjectDelete(id: $id) { deletedId userErrors { message } } }',
                    ['id' => $entry['id']],
                );
            }

            $removed++;
        }

        if ($written === 0 && $removed === 0) {
            $this->components->info('The website calendar is already up to date.');

            return self::SUCCESS;
        }

        $summary = sprintf('%d written, %d removed.', $written, $removed);
        $dryRun
            ? $this->components->warn("Dry run — nothing was sent. {$summary}")
            : $this->components->info("Website calendar updated. {$summary}");

        return self::SUCCESS;
    }

    /**
     * One entry per event, addressed by a handle built from the event's id.
     *
     * The name would read better but changes; the id does not, and a handle that
     * moves would orphan the entry it used to point at.
     */
    private function handleFor(Event $event): string
    {
        return 'imprint-event-'.$event->id;
    }

    /** @return array<string,string> */
    private function fieldsFor(Event $event): array
    {
        $entry = $event->toPublicCalendarEntry();

        return collect(self::FIELDS)
            // Shopify rejects null for a field value, so an unset one is sent as
            // an empty string and reads as blank on the website.
            ->mapWithKeys(fn (string $key) => [$key => (string) ($entry[$key] ?? '')])
            ->all();
    }

    /**
     * What the website already holds, keyed by handle.
     *
     * @return array<string,array{id:string,status:string,fields:array<string,string>}>
     */
    private function existingEntries(ShopifyStore $shopify, string $type): array
    {
        $entries = [];
        $cursor = null;

        do {
            $data = $shopify->graphql(
                'query($type: String!, $after: String) {
                    metaobjects(type: $type, first: 100, after: $after) {
                        nodes { id handle capabilities { publishable { status } } fields { key value } }
                        pageInfo { hasNextPage endCursor }
                    }
                }',
                ['type' => $type, 'after' => $cursor],
            );

            $page = $data['metaobjects'] ?? ['nodes' => [], 'pageInfo' => []];

            foreach ($page['nodes'] as $node) {
                // Only entries this sync created. Anything else in the same
                // definition was put there by hand and is left alone.
                if (! str_starts_with($node['handle'], 'imprint-event-')) {
                    continue;
                }

                $entries[$node['handle']] = [
                    'id' => $node['id'],
                    // A definition without active-draft status reports none, and
                    // everything under it is live — so that reads as ACTIVE.
                    'status' => $node['capabilities']['publishable']['status'] ?? 'ACTIVE',
                    'fields' => collect($node['fields'])->mapWithKeys(fn ($field) => [$field['key'] => (string) ($field['value'] ?? '')])->all(),
                ];
            }

            $cursor = $page['pageInfo']['endCursor'] ?? null;
        } while (($page['pageInfo']['hasNextPage'] ?? false) && $cursor);

        return $entries;
    }

    /**
     * @param  array<string,string>  $current
     * @param  array<string,string>  $wanted
     */
    private function matches(array $current, array $wanted): bool
    {
        foreach ($wanted as $key => $value) {
            if (($current[$key] ?? '') !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Written active, not draft.
     *
     * A definition with active-draft status turned on gives an entry created
     * over the API the draft status by default, and Liquid skips drafts — so
     * the sync reported success while the storefront stayed empty. Saying it
     * outright on every write is what stops that being silent.
     *
     * @param  array<string,string>  $fields
     */
    private function upsert(ShopifyStore $shopify, string $type, string $handle, array $fields, ?string $id): void
    {
        $payload = collect($fields)->map(fn ($value, $key) => ['key' => $key, 'value' => $value])->values()->all();
        $capabilities = ['publishable' => ['status' => 'ACTIVE']];

        $data = $id
            ? $shopify->graphql(
                'mutation($id: ID!, $metaobject: MetaobjectUpdateInput!) {
                    metaobjectUpdate(id: $id, metaobject: $metaobject) { userErrors { field message } }
                }',
                ['id' => $id, 'metaobject' => ['fields' => $payload, 'capabilities' => $capabilities]],
            )
            : $shopify->graphql(
                'mutation($metaobject: MetaobjectCreateInput!) {
                    metaobjectCreate(metaobject: $metaobject) { userErrors { field message } }
                }',
                ['metaobject' => ['type' => $type, 'handle' => $handle, 'fields' => $payload, 'capabilities' => $capabilities]],
            );

        $errors = $data['metaobjectUpdate']['userErrors'] ?? $data['metaobjectCreate']['userErrors'] ?? [];

        if ($errors) {
            // Named rather than swallowed: a mistyped field key fails here, and
            // "it did not sync" is a far worse thing to read in a log.
            throw new RuntimeException(
                "Shopify rejected \"{$handle}\": ".collect($errors)->map(fn ($error) => ($error['field'][1] ?? $error['field'][0] ?? '?').' — '.$error['message'])->implode('; ')
            );
        }
    }
}
