<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create user with SubadqA
        $userA = User::create([
            'name' => 'User SubadqA',
            'email' => 'userA@example.com',
            'password' => Hash::make('password'),
            'subadquirer' => 'SubadqA',
        ]);

        // Create API token for user A
        $tokenA = $userA->createToken('api-token', ['*'])->plainTextToken;

        // Create user with SubadqB
        $userB = User::create([
            'name' => 'User SubadqB',
            'email' => 'userB@example.com',
            'password' => Hash::make('password'),
            'subadquirer' => 'SubadqB',
        ]);

        // Create API token for user B
        $tokenB = $userB->createToken('api-token', ['*'])->plainTextToken;

        // Create user with relation-based subadquirer
        $userC = User::create([
            'name' => 'User Relation',
            'email' => 'userC@example.com',
            'password' => Hash::make('password'),
        ]);

        $userC->subadquirers()->create([
            'subadquirer' => 'SubadqA',
            'is_active' => true,
            'config' => [],
        ]);

        // Create API token for user C
        $tokenC = $userC->createToken('api-token', ['*'])->plainTextToken;

        $this->command->info('Users created successfully!');
        $this->command->info('Use these tokens for API authentication.');
    }
}

