<?php

namespace Database\Seeders;

use App\Models\Coverage;
use App\Models\Endorser;
use App\Models\Event;
use App\Models\Obligation;
use App\Models\PrKit;
use App\Models\PublicSubmission;
use App\Models\Task;
use App\Models\User;
use App\Support\CoverageDesk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A shop's worth of believable sample data, so the screens can be judged with
 * something in them. It never touches the staff accounts.
 *
 *   php artisan db:seed --class=DemoSeeder     fill the app
 *   php artisan imprint:demo-clear             empty it again
 *
 * Dates are relative to today, so the calendar and the "upcoming" filters stay
 * useful whenever this is run.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->wipe();

        $joey = User::where('role', 'admin')->firstOrFail();
        $shooters = User::multimedia()->orderBy('id')->get();
        $today = today();

        $events = $this->events($joey, $today);
        $this->coverage($events, $shooters, $joey, $today);
        $endorsers = $this->endorsers($joey);
        $this->kits($endorsers, $events, $joey, $today);
        $this->tasks($joey, $shooters, $today);
        $this->inquiries($today);
    }

    /** Demo rows only — the eight staff accounts are left alone. */
    private function wipe(): void
    {
        foreach ([Obligation::class, Coverage::class, PrKit::class, Task::class, PublicSubmission::class, Event::class, Endorser::class] as $model) {
            DB::table((new $model)->getTable())->delete();
        }
    }

    /** @return array<string,Event> */
    private function events(User $joey, Carbon $today): array
    {
        $rows = [
            'sunday' => ['Sunday Ride-Out: Tanay Loop', 'tambike', 'Imprint Customs', 6, '06:00', '13:00', 'Imprint Customs, Marikina', 90, 'confirmed'],
            'expo' => ['Makina Moto Expo Cebu', 'external_sponsorship', 'Makina Events Inc.', 19, '10:00', '20:00', 'IEC Convention Center, Cebu City', 400, 'confirmed'],
            'debut' => ['Delos Reyes Debut', 'function_hall', 'Delos Reyes Family', 12, '17:00', '23:00', 'Imprint Function Hall', 120, 'pending'],
            'nightride' => ['Night Ride: Antipolo Skyline', 'tambike', 'Revolt Garage Co.', 2, '19:00', '23:30', 'Revolt Garage Co.', 60, 'confirmed'],
            'billiards' => ['Sidepocket Open Cup', 'external_sponsorship', 'Sidepocket Billiard Studio', -9, '13:00', '22:00', 'Sidepocket Billiard Studio', 150, 'completed'],
            'anniv' => ['Imprint Cafe 3rd Anniversary', 'tambike', 'Imprint Cafe', -22, '15:00', '22:00', 'Imprint Cafe, Marikina', 200, 'completed'],
            'dealers' => ['Yamaha Dealers Assembly', 'function_hall', 'Yamaha Motor PH', 33, '09:00', '16:00', 'Imprint Function Hall', 80, 'new'],
        ];

        $events = [];

        foreach ($rows as $key => [$name, $category, $organization, $offset, $start, $end, $venue, $pax, $status]) {
            $events[$key] = Event::create([
                'name' => $name,
                'category' => $category,
                'organization' => $organization,
                'event_date' => $today->copy()->addDays($offset)->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'venue' => $venue,
                'group_chat_url' => 'https://m.me/j/'.strtoupper(substr(md5($name), 0, 10)),
                'estimated_pax' => $pax,
                'status' => $status,
                'notes' => 'Sample record for design review.',
                'created_by' => $joey->id,
            ]);
        }

        return $events;
    }

    /**
     * Coverage mirrors the team's spreadsheet, including the case they care
     * about: an event with nobody assigned to shoot it yet.
     *
     * @param  array<string,Event>  $events
     * @param  Collection<int,User>  $shooters
     */
    private function coverage(array $events, Collection $shooters, User $joey, Carbon $today): void
    {
        $who = fn (int $index) => $shooters->get($index)?->id;

        Coverage::create([
            'event_id' => $events['billiards']->id,
            'stage' => CoverageDesk::ACCEPTED, 'accepted_at' => now()->subDays(12),
            'shooter_id' => $who(0), 'photo_editor_id' => $who(1), 'video_editor_id' => $who(2),
            'photo_status' => 'posted', 'photo_posted_on' => $today->copy()->subDays(6)->toDateString(),
            'video_status' => 'posted', 'video_posted_on' => $today->copy()->subDays(4)->toDateString(),
            'remarks' => 'Full set delivered. Client asked for a vertical cut.',
            'created_by' => $joey->id,
        ]);

        Coverage::create([
            'event_id' => $events['anniv']->id,
            'stage' => CoverageDesk::ACCEPTED, 'accepted_at' => now()->subDays(25),
            'shooter_id' => $who(1), 'photo_editor_id' => $who(3), 'video_editor_id' => $who(4),
            'photo_status' => 'posted', 'photo_posted_on' => $today->copy()->subDays(19)->toDateString(),
            'video_status' => 'editing',
            'remarks' => 'Recap reel still with the editor.',
            'created_by' => $joey->id,
        ]);

        Coverage::create([
            'event_id' => $events['sunday']->id,
            'stage' => CoverageDesk::ACCEPTED, 'accepted_at' => now()->subDays(2),
            'shooter_id' => $who(2),
            'photo_status' => 'not_started', 'video_status' => 'not_started',
            'remarks' => 'Drone shots requested for the convoy.',
            'created_by' => $joey->id,
        ]);

        // Taken on, but nobody named to shoot it yet — the gap the team watches
        // for, and the reason a job can be accepted without an assignment.
        Coverage::create([
            'event_id' => $events['expo']->id,
            'stage' => CoverageDesk::ACCEPTED, 'accepted_at' => now()->subDay(),
            'photo_status' => 'not_started', 'video_status' => 'not_started',
            'remarks' => 'Booth build starts a day early; needs two shooters.',
            'created_by' => $joey->id,
        ]);

        // Freshly sent over by marketing and not yet picked up: what the crew
        // find waiting in their queue.
        foreach (['debut', 'nightride'] as $key) {
            Coverage::create([
                'event_id' => $events[$key]->id,
                'stage' => CoverageDesk::REQUESTED,
                'requested_at' => now()->subHours(random_int(2, 30)),
                'requested_by' => $joey->id,
                'created_by' => $joey->id,
            ]);
        }
    }

    /** @return array<string,Endorser> */
    private function endorsers(User $joey): array
    {
        $rows = [
            'kiko' => ['Kiko Ramirez', 'racer', 'Team Redline', 'active', 'Underbone racer, 18k followers. Posts weekly.'],
            'redline' => ['Team Redline', 'team', 'Team Redline', 'active', 'Six riders. Kits go to the team captain.'],
            'mia' => ['Mia Santillan', 'influencer', null, 'active', 'Lifestyle rider, strong reels engagement.'],
            'jomar' => ['Jomar Dela Cruz', 'individual', 'Marikina Riders Club', 'pending', 'Waiting on the signed agreement.'],
            'mrc' => ['Marikina Riders Club', 'organization', null, 'active', 'Long-running partner club.'],
            'bantay' => ['Bantay Kalsada PH', 'organization', null, 'inactive', 'Paused for the season.'],
        ];

        $endorsers = [];

        foreach ($rows as $key => [$name, $type, $group, $status, $profile]) {
            $slug = str($name)->slug()->value();

            $endorsers[$key] = Endorser::create([
                'name' => $name,
                'type' => $type,
                'contact_number' => '+63 917 '.random_int(1000000, 9999999),
                'email' => $slug.'@example.ph',
                'team_or_group' => $group,
                'social_media_url' => 'https://www.instagram.com/'.str_replace('-', '', $slug),
                'group_chat_url' => 'https://m.me/j/'.strtoupper(substr(md5($name), 0, 10)),
                'status' => $status,
                'profile' => $profile,
                'created_by' => $joey->id,
            ]);
        }

        return $endorsers;
    }

    /**
     * One kit per endorser per month, each owing two videos — the shop's rule —
     * plus giveaway stock, which owes nothing back.
     *
     * @param  array<string,Endorser>  $endorsers
     * @param  array<string,Event>  $events
     */
    private function kits(array $endorsers, array $events, User $joey, Carbon $today): void
    {
        $month = $today->copy()->startOfMonth();

        $kits = [
            ['IC-KIT-0101', $endorsers['kiko'], 'delivered', $month->copy()->addDays(2), 'JRS Express', 'Tee, decals, cap'],
            ['IC-KIT-0102', $endorsers['redline'], 'delivered', $month->copy()->addDays(3), 'Lalamove', 'Team pack: 6 tees, banner'],
            ['IC-KIT-0103', $endorsers['mia'], 'awaiting_pickup', $month->copy()->addDays(6), null, 'Tee, hoodie, sticker set'],
            ['IC-KIT-0104', $endorsers['mrc'], 'in_transit', $today->copy()->addDay(), 'J&T Express', 'Club banner, 10 decals'],
            ['IC-KIT-0105', $endorsers['jomar'], 'packed', $today->copy()->addDays(4), null, 'Tee, cap'],
        ];

        foreach ($kits as [$reference, $endorser, $status, $date, $courier, $contents]) {
            $kit = PrKit::create([
                'reference' => $reference,
                'recipient' => $endorser->name,
                'purpose' => PrKit::PURPOSE_ENDORSER,
                'quantity' => 1,
                'endorser_id' => $endorser->id,
                'contents' => $contents,
                'courier' => $courier,
                'tracking_number' => $courier ? strtoupper(substr(md5($reference), 0, 12)) : null,
                'address' => $courier ? 'Marikina City, Metro Manila' : null,
                'delivery_date' => $date->toDateString(),
                'status' => $status,
                'content_quota' => 2,
                'created_by' => $joey->id,
            ]);

            $kit->syncContentObligations();
        }

        // Giveaway stock is tied to an event and owes no content back.
        $giveaways = [
            ['IC-GIV-0031', $events['expo'], 40, 'Booth giveaway: tees and stickers'],
            ['IC-GIV-0032', $events['sunday'], 15, 'Ride-out raffle prizes'],
        ];

        foreach ($giveaways as [$reference, $event, $quantity, $contents]) {
            PrKit::create([
                'reference' => $reference,
                'recipient' => $event->name,
                'purpose' => PrKit::PURPOSE_GIVEAWAY,
                'quantity' => $quantity,
                'event_id' => $event->id,
                'contents' => $contents,
                'delivery_date' => $event->event_date->toDateString(),
                'status' => 'scheduled',
                'content_quota' => 0,
                'created_by' => $joey->id,
            ]);
        }

        $this->settleSomeObligations($today);
    }

    /**
     * Left untouched every obligation would be pending, and the overdue and
     * submitted styling would never be seen.
     */
    private function settleSomeObligations(Carbon $today): void
    {
        $obligations = Obligation::orderBy('id')->get();

        $obligations->get(0)?->update([
            'status' => 'submitted',
            'completed_on' => $today->copy()->subDays(3)->toDateString(),
            'proof_url' => 'https://www.instagram.com/reel/DemoReel01/',
        ]);

        // Due before today and still pending, so it reads as overdue.
        $obligations->get(1)?->update(['due_date' => $today->copy()->subDays(5)->toDateString()]);

        $obligations->get(2)?->update([
            'status' => 'submitted',
            'completed_on' => $today->copy()->subDay()->toDateString(),
            'proof_url' => 'https://www.tiktok.com/@teamredline/video/demo',
        ]);

        $obligations->get(4)?->update([
            'status' => 'waived',
            'notes' => 'Kit arrived damaged; waived for this month.',
        ]);
    }

    /** @param  Collection<int,User>  $shooters */
    private function tasks(User $joey, Collection $shooters, Carbon $today): void
    {
        $mine = [
            ['Draft captions for the Tanay ride-out', 'doing', 0],
            ['Confirm the booth layout with Makina', 'todo', 0],
            ['Chase Mia for the second reel', 'todo', 0],
            ['Post the Sidepocket recap', 'done', -1],
        ];

        foreach ($mine as [$title, $status, $offset]) {
            Task::create([
                'user_id' => $joey->id,
                'title' => $title,
                'task_date' => $today->copy()->addDays($offset)->toDateString(),
                'status' => $status,
                'completed_at' => $status === 'done' ? $today->copy()->subDay() : null,
                'created_by' => $joey->id,
            ]);
        }

        $editing = ['Cull the anniversary set', 'Colour grade the recap reel', 'Export the vertical cuts', 'Back up the Cebu cards', 'Storyboard the expo teaser'];

        foreach ($shooters as $index => $member) {
            Task::create([
                'user_id' => $member->id,
                'title' => $editing[$index] ?? 'Editing queue',
                'task_date' => $today->toDateString(),
                'status' => $index === 0 ? 'doing' : 'todo',
                'created_by' => $member->id,
            ]);
        }

        // Raised by marketing for the crew and not yet taken: what the team
        // find waiting above their board.
        foreach ([
            ['Shoot the new tank badge for the shop feed', null],
            ['Cut a 15s teaser for the Cebu expo', 'Vertical, for reels.'],
        ] as [$title, $details]) {
            Task::create([
                'user_id' => null,
                'for_team' => User::TEAM_MULTIMEDIA,
                'title' => $title,
                'details' => $details,
                'task_date' => $today->toDateString(),
                'status' => 'todo',
                'created_by' => $joey->id,
            ]);
        }
    }

    private function inquiries(Carbon $today): void
    {
        $rows = [
            ['tambike_inquiry', 'new', 1, [
                'name_or_group' => 'Cavite Underbone Society',
                'contact_person' => 'Rey Alonzo',
                'contact_number' => '+63 918 5550194',
                'email' => 'rey@example.ph',
                'preferred_date' => $today->copy()->addDays(26)->toDateString(),
                'estimated_pax' => '75',
                'message' => 'Planning a club ride-out that ends at your shop. Can we book the parking area?',
            ]],
            ['function-hall_inquiry', 'new', 2, [
                'event_name' => 'Villamor Wedding Reception',
                'contact_person' => 'Anna Villamor',
                'contact_number' => '+63 917 3300221',
                'email' => 'anna@example.ph',
                'preferred_date' => $today->copy()->addDays(58)->toDateString(),
                'estimated_pax' => '140',
                'message' => 'Is catering included, and may we view the hall this weekend?',
            ]],
            ['sponsorship_inquiry', 'reviewing', 4, [
                'racer_team_name' => 'Team Apex Racing',
                'contact_person' => 'Dennis Bautista',
                'contact_number' => '+63 921 7784412',
                'email' => 'apex@example.ph',
                'message' => 'Requesting decals and race apparel support for the regional series.',
            ]],
            ['external-event_inquiry', 'confirmed', 9, [
                'organizer' => 'Makina Events Inc.',
                'event_name' => 'Makina Moto Expo Cebu',
                'contact_person' => 'Grace Uy',
                'email' => 'grace@example.ph',
                'preferred_date' => $today->copy()->addDays(19)->toDateString(),
                'message' => 'Confirming your booth for the Cebu leg.',
            ]],
        ];

        foreach ($rows as [$type, $status, $daysAgo, $data]) {
            PublicSubmission::create([
                'type' => $type,
                'status' => $status,
                'data' => $data,
                'uploads' => [],
                'submitted_at' => $today->copy()->subDays($daysAgo)->setTime(random_int(9, 18), random_int(0, 59)),
            ]);
        }
    }
}
