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

    /**
     * The administrator account used to be renamed back on every launch, because
     * the seeder re-applied ADMIN_NAME to it each time. Renaming is the
     * administrator's business; seeding is not allowed to undo it.
     */
    public function test_renaming_the_administrator_survives_seeding(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@imprintcustoms.ph')->sole();

        $admin->update(['name' => 'Joey Santos']);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Joey Santos', $admin->fresh()->name);
    }

    public function test_renaming_survives_even_when_admin_name_is_configured(): void
    {
        // The environment variable seeds a brand new account and nothing more.
        // Left in .env — as it was on the shop machine — it must not keep
        // reasserting itself over a rename.
        config(['app.name' => config('app.name')]);
        putenv('ADMIN_NAME=Joey');

        try {
            $this->seed(DatabaseSeeder::class);
            $admin = User::where('email', 'admin@imprintcustoms.ph')->sole();

            $admin->update(['name' => 'Joey Santos']);
            $this->seed(DatabaseSeeder::class);

            $this->assertSame('Joey Santos', $admin->fresh()->name);
        } finally {
            putenv('ADMIN_NAME');
        }
    }

    public function test_the_administrator_cannot_be_locked_out_by_seeding(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@imprintcustoms.ph')->sole();

        // Demoted or deactivated by accident, a launch puts it right.
        $admin->update(['role' => 'staff', 'team' => User::TEAM_MULTIMEDIA, 'is_active' => false]);
        $this->seed(DatabaseSeeder::class);

        $admin->refresh();
        $this->assertSame('admin', $admin->role);
        $this->assertSame(User::TEAM_MARKETING, $admin->team);
        $this->assertTrue($admin->is_active);
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
