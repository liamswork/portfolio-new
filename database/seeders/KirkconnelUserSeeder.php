<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/*
 * TEST USERS FOR KIRKCONNEL GAME
 * ─────────────────────────────────────────────────────
 * Email                    | Password
 * ─────────────────────────────────────────────────────
 * player1@kirkconnel.test  | password1
 * player2@kirkconnel.test  | password2
 * player3@kirkconnel.test  | password3
 * player4@kirkconnel.test  | password4
 * player5@kirkconnel.test  | password5
 * ─────────────────────────────────────────────────────
 */
class KirkconnelUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Player One',   'email' => 'player1@kirkconnel.test', 'password' => 'password1'],
            ['name' => 'Player Two',   'email' => 'player2@kirkconnel.test', 'password' => 'password2'],
            ['name' => 'Player Three', 'email' => 'player3@kirkconnel.test', 'password' => 'password3'],
            ['name' => 'Player Four',  'email' => 'player4@kirkconnel.test', 'password' => 'password4'],
            ['name' => 'Player Five',  'email' => 'player5@kirkconnel.test', 'password' => 'password5'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name'              => $u['name'],
                    'password'          => Hash::make($u['password']),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
