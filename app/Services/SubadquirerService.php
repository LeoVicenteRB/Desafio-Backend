<?php

namespace App\Services;

use App\Adapters\Contracts\SubadquirerInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SubadquirerService
{
    /**
     * Get the subadquirer adapter for a user.
     */
    public function getAdapterForUser(User $user): ?SubadquirerInterface
    {
        $subadquirerName = $user->getActiveSubadquirer();

        if (!$subadquirerName) {
            Log::warning('User has no subadquirer configured', ['user_id' => $user->id]);
            return null;
        }

        return $this->resolveAdapter($subadquirerName);
    }

    /**
     * Resolve adapter by name.
     */
    public function resolveAdapter(string $name): ?SubadquirerInterface
    {
        return app("subadquirer.{$name}");
    }
}

