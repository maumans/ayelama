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
use PhpOffice\PhpWord\Settings as PhpWordSettings;

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

        $this->configurerRepertoireTemporairePhpWord();
    }

    /**
     * Par défaut, PhpWord passe sys_get_temp_dir() à tempnam() (TemplateProcessor::__construct).
     * Sur certains hébergements (ex. production wedrive.africa), ce répertoire n'est pas
     * utilisable tel quel : tempnam() retombe sur un autre chemin et émet un E_WARNING
     * ("file created in the system's temporary directory"), que Laravel convertit en
     * ErrorException fatale. On pointe donc explicitement vers un répertoire qu'on sait
     * exister et être inscriptible (storage/app/phpword-tmp), plutôt que de dépendre de la
     * configuration ambiante du serveur.
     */
    private function configurerRepertoireTemporairePhpWord(): void
    {
        $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'phpword-tmp');

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        PhpWordSettings::setTempDir($tempDir);
    }
}
