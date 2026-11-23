<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\MasterPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('view-master', [MasterPolicy::class, 'view']);
        Gate::define('manage-master', [MasterPolicy::class, 'manage']);
        Gate::define('manage-accounts', [UserPolicy::class, 'manage']);
        Gate::define('operate-admin', fn (User $user): bool => $user->isAdmin());
    }
}
