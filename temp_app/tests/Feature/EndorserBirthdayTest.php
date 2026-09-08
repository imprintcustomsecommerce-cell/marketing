<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndorserBirthdayTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function endorser(?string $birthday, array $overrides = []): Endorser
    {
        return Endorser::create(array_merge([
            'name' => 'Rico Santos', 'type' => 'racer', 'status' => 'active', 'birthday' => $birthday,
        ], $overrides));
    }

    public function test_a_birthday_is_saved_from_the_form(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/endorsers', [
            'name' => 'Rico Santos', 'type' => 'racer', 'status' => 'active',
            'birthday' => '1996-03-14',
        ])->assertRedirect('/admin/endorsers');

        $this->assertSame('1996-03-14', Endorser::sole()->birthday->format('Y-m-d'));
    }

    public function test_a_birthday_in_the_future_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/endorsers', [
            'name' => 'Rico Santos', 'type' => 'racer', 'status' => 'active',
            'birthday' => today()->addYear()->toDateString(),
        ])->assertSessionHasErrors('birthday');
    }

    public function test_the_next_birthday_counts_from_today_and_rolls_into_next_year(): void
    {
        $today = $this->endorser(today()->subYears(30)->toDateString());
        $this->assertSame(0, $today->daysUntilBirthday(), 'today counts as due, not missed');
        $this->assertSame(30, $today->turningAge());

        $soon = $this->endorser(today()->addDays(9)->subYears(25)->toDateString());
        $this->assertSame(9, $soon->daysUntilBirthday());

        // A date already past this year points at next year, never a negative.
        $passed = $this->endorser(today()->subDays(5)->subYears(40)->toDateString());
        $this->assertGreaterThan(300, $passed->daysUntilBirthday());
        $this->assertSame(today()->year + 1, $passed->nextBirthday()->year);
    }

    public function test_a_leap_day_birthday_does_not_slip_into_march(): void
    {
        $leap = $this->endorser('2000-02-29');

        $this->assertSame(2, $leap->nextBirthday()->month, 'must stay in February');
        $this->assertContains($leap->nextBirthday()->day, [28, 29]);
    }

    public function test_greetings_due_lists_the_soonest_first_and_skips_inactive_people(): void
    {
        $this->endorser(today()->addDays(10)->subYears(20)->toDateString(), ['name' => 'Later']);
        $this->endorser(today()->addDays(2)->subYears(20)->toDateString(), ['name' => 'Sooner']);
        $this->endorser(today()->addDays(3)->subYears(20)->toDateString(), ['name' => 'Retired', 'status' => 'inactive']);
        $this->endorser(null, ['name' => 'Unknown birthday']);
        $this->endorser(today()->addDays(60)->subYears(20)->toDateString(), ['name' => 'Far off']);

        $due = Endorser::greetingsDue(14);

        $this->assertSame(['Sooner', 'Later'], $due->pluck('name')->all());
    }

    public function test_the_dashboard_names_who_to_greet(): void
    {
        $this->endorser(today()->subYears(28)->toDateString(), ['name' => 'Birthday Today']);
        $this->endorser(today()->addDays(90)->subYears(28)->toDateString(), ['name' => 'Months Away']);

        $this->actingAs($this->admin())->get('/admin')
            ->assertOk()
            ->assertSee('Birthdays to greet')
            ->assertSee('Birthday Today')
            ->assertDontSee('Months Away');
    }

    public function test_the_endorser_list_flags_a_birthday_that_is_close(): void
    {
        $this->endorser(today()->subYears(28)->toDateString(), ['name' => 'Rico Santos']);

        $this->actingAs($this->admin())->get('/admin/endorsers')
            ->assertOk()
            ->assertSee('Birthday today');
    }
}
