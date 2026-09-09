<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederPasswordFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_is_asked_to_change_the_default_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::where('email', 'marketing2@imprintcustoms.ph')->sole()->must_change_password);
    }

    public function test_lifting_the_requirement_survives_the_next_launch(): void
    {
        // The launcher seeds on every start, and it used to re-apply the flag to
        // anyone still on the default password — so an administrator lifting it
        // for somebody found it back on them the next morning.
        $this->seed(DatabaseSeeder::class);

        $person = User::where('email', 'marketing2@imprintcustoms.ph')->sole();
        $person->update(['must_change_password' => false]);

        $this->seed(DatabaseSeeder::class);

        $this->assertFalse($person->fresh()->must_change_password);
    }

    public function test_seeding_again_does_not_disturb_a_renamed_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        User::where('email', 'multimedia1@imprintcustoms.ph')->sole()->update(['name' => 'Mike']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Mike', User::where('email', 'multimedia1@imprintcustoms.ph')->sole()->name);
        $this->assertSame(8, User::count(), 'seeding twice must not duplicate the roster');
    }
}
