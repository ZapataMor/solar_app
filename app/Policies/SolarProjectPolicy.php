<?php

namespace App\Policies;

use App\Models\SolarProject;
use App\Models\User;

/**
 * Who may see and change a project: its owner, or an admin (they see every project).
 */
class SolarProjectPolicy
{
    public function manage(User $user, SolarProject $solarProject): bool
    {
        return $user->isAdmin() || $solarProject->user_id === $user->id;
    }
}
