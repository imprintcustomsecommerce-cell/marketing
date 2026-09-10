<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The shop runs on two teams: three on marketing (one of whom is the
     * administrator) and five on multimedia. Seeded as placeholders so the
     * accounts exist on day one — rename them from the Team screen.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@imprintcustoms.ph')],
            [
                'name' => env('ADMIN_NAME', 'Joey'),
                'password' => env('ADMIN_PASSWORD', 'imprint123'),
                'role' => 'admin',
                'team' => User::TEAM_MARKETING,
                'is_active' => true,
                'must_change_password' => true,
            ],
        );
        // Role, team, and active status are re-asserted on every launch so the
        // shop can never end up with no way into the administrator account.
        //
        // The name is not, and must not be: ADMIN_NAME seeds the account when it
        // is first created, and renaming it afterwards is the administrator's
        // business. Setting it here meant a launch quietly undid the rename,
        // which is the same rule the placeholder accounts below already follow.
        $admin->update(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'is_active' => true]);

        $placeholders = [
            [User::TEAM_MARKETING, 'Marketing 2', 'marketing2@imprintcustoms.ph'],
            [User::TEAM_MARKETING, 'Marketing 3', 'marketing3@imprintcustoms.ph'],
            [User::TEAM_MULTIMEDIA, 'Multimedia 1', 'multimedia1@imprintcustoms.ph'],
            [User::TEAM_MULTIMEDIA, 'Multimedia 2', 'multimedia2@imprintcustoms.ph'],
            [User::TEAM_MULTIMEDIA, 'Multimedia 3', 'multimedia3@imprintcustoms.ph'],
            [User::TEAM_MULTIMEDIA, 'Multimedia 4', 'multimedia4@imprintcustoms.ph'],
            [User::TEAM_MULTIMEDIA, 'Multimedia 5', 'multimedia5@imprintcustoms.ph'],
        ];

        foreach ($placeholders as [$team, $name, $email]) {
            // firstOrCreate, not updateOrCreate: once these are renamed to real
            // people, re-running the seeder must not rename them back. The same
            // goes for must_change_password — it is set when the account is
            // created and never re-applied, so an administrator who lifts it for
            // somebody does not find it back on them at the next launch.
            $person = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => env('STAFF_PASSWORD', 'imprint123'),
                    'role' => 'staff',
                    'team' => $team,
                    'is_active' => true,
                    'must_change_password' => true,
                ],
            );
        }
    }
}
