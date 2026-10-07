<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Pembatasan akses berdasarkan peran (NFR2). Setiap kemampuan pada
        // matriks config/sipasti.php menjadi satu Gate.
        foreach (array_keys(config('sipasti.abilities')) as $ability) {
            Gate::define($ability, fn (User $user) => $user->hasAbility($ability));
        }
    }
}
