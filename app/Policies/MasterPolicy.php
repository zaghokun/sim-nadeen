<?php

namespace App\Policies;

use App\Enums\MasterSection;
use App\Models\User;

class MasterPolicy
{
    public function view(User $user, MasterSection $section): bool
    {
        return $user->aktif && ($user->isAdmin() || $section->isStaffVisible());
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
