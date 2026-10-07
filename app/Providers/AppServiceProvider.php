<?php

namespace App\Providers;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Policies\SessionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Configure RBAC authorization gates.
     */
    protected function configureAuthorization(): void
    {
        Gate::policy(AttendanceSession::class, SessionPolicy::class);

        // Superadmin bypass for all gates
        Gate::before(function ($user, string $ability): ?bool {
            if ($user instanceof User && $user->isSuperAdmin()) {
                return true;
            }

            return null;
        });

        // Only superadmin can manage/grant roles
        Gate::define('manage-roles', function (User $user): bool {
            return $user->isSuperAdmin();
        });

        // Superadmin and admin can manage users
        Gate::define('manage-users', function (User $user): bool {
            return $user->hasAnyRole(['superadmin', 'admin']) || $user->hasPermission('manage-users');
        });

        // Superadmin and admin can register new users (as anggota)
        Gate::define('create-users', function (User $user): bool {
            return $user->hasAnyRole(['superadmin', 'admin']) || $user->hasPermission('create-users');
        });

        // Superadmin and admin can manage attendance sessions
        Gate::define('manage-sessions', function (User $user): bool {
            return $user->hasAnyRole(['superadmin', 'admin']) || $user->hasPermission('manage-sessions');
        });

        // Only superadmin can manage sports and courts master data
        Gate::define('manage-sports', function (User $user): bool {
            return $user->isSuperAdmin();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
