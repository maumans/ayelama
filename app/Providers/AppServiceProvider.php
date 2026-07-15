<?php

namespace App\Providers;

use App\Models\Courrier;
use App\Models\Demande;
use App\Models\Dossier;
use App\Models\Revision;
use App\Policies\CourrierPolicy;
use App\Policies\DemandePolicy;
use App\Policies\DossierPolicy;
use App\Policies\RevisionPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Carbon::setLocale('fr');

        Vite::prefetch(concurrency: 3);

        Gate::policy(Dossier::class, DossierPolicy::class);
        Gate::policy(Revision::class, RevisionPolicy::class);
        Gate::policy(Courrier::class, CourrierPolicy::class);
        Gate::policy(Demande::class, DemandePolicy::class);

        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());
    }
}
