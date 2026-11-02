<?php

namespace App\Console\Commands;

use App\Models\Dossier;
use App\Models\Partie;
use App\Models\Societe;
use App\Models\SocieteAssocie;
use App\Models\SocieteGerant;
use App\Services\EtatSocieteService;
use App\Services\SocieteMutationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Peuple `societe_associes` et `societe_gerants` depuis les dossiers de constitution.
 *
 * Sans cette reprise, les deux tables restent vides et la fiche société montre une composition
 * inconnue pour toutes les sociétés déjà au registre. Le repli de `Societe::associesConnus()`
 * masque le trou à l'écran, mais aucune cession ne pourrait s'appliquer : on retrancherait des
 * parts à des associés qui n'existent pas.
 *
 * **Deux sources, dans cet ordre** :
 *  1. le bloc répétable `associes` du questionnaire de constitution — il porte **les parts**,
 *     ce que les `parties` ne portent pas ;
 *  2. à défaut, les `parties` du dossier de constitution (rôles associé / associé unique), avec
 *     les parts déduites : tout à l'associé unique, sinon réparti à parts égales.
 *
 * ⚠️ **Dry-run par défaut**, comme `ayelema:societes-backfill` : une reprise de données se lit
 * avant de s'exécuter. `--force` écrit.
 *
 * ⚠️ **Idempotente** : une société qui a déjà des associés est ignorée. Relancer ne double rien.
 */
class BackfillAssocies extends Command
{
    protected $signature = 'ayelema:backfill-associes {--force : Écrit réellement (sans cette option, simulation)}';

    protected $description = 'Reprend les associés et gérants des sociétés depuis leur dossier de constitution';

    public function handle(EtatSocieteService $etats): int
    {
        $ecrit = (bool) $this->option('force');

        if (! $ecrit) {
            $this->warn('Simulation — aucune écriture. Ajoutez --force pour appliquer.');
        }

        $societes = Societe::with(['dossier.questionnaire', 'associes', 'gerants'])->get();
        $traitees = 0;
        $ignorees = 0;

        foreach ($societes as $societe) {
            if ($societe->associes->isNotEmpty() || $societe->gerants->isNotEmpty()) {
                $ignorees++;
                continue;
            }

            $associes = $this->associesDe($societe);
            $gerants  = $this->gerantsDe($societe);

            if ($associes === [] && $gerants === []) {
                $this->line(sprintf('  <fg=gray>%-34s aucune source exploitable</>', mb_substr($societe->denomination, 0, 34)));
                continue;
            }

            $this->line(sprintf(
                '  %-34s %d associé(s), %d gérant(s)',
                mb_substr($societe->denomination, 0, 34),
                count($associes),
                count($gerants),
            ));

            foreach ($associes as $a) {
                $this->line(sprintf('       <fg=gray>%-28s %s part(s)</>', mb_substr($a['nom'], 0, 28), $a['nb_parts']));
            }

            if ($ecrit) {
                DB::transaction(fn () => $this->ecrire($societe, $associes, $gerants, $etats));
            }

            $traitees++;
        }

        $this->newLine();
        $this->info(sprintf(
            '%d société(s) %s, %d déjà pourvue(s), %d sans source.',
            $traitees,
            $ecrit ? 'reprise(s)' : 'à reprendre',
            $ignorees,
            $societes->count() - $traitees - $ignorees,
        ));

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function associesDe(Societe $societe): array
    {
        $dossier = $societe->dossier;

        if (! $dossier) {
            return [];
        }

        $depuisQuestionnaire = $this->depuisBlocRepetable($dossier);

        if ($depuisQuestionnaire !== []) {
            return $depuisQuestionnaire;
        }

        return $this->depuisParties($societe, $dossier);
    }

    /**
     * Le bloc `associes` du questionnaire — la seule source qui porte les parts.
     *
     * @return array<int, array<string, mixed>>
     */
    private function depuisBlocRepetable(Dossier $dossier): array
    {
        $lignes = $dossier->questionnaire?->donnees['associes'] ?? null;

        if (! is_array($lignes)) {
            return [];
        }

        $associes = [];

        foreach ($lignes as $ligne) {
            if (! is_array($ligne)) {
                continue;
            }

            $nom = trim((string) ($ligne['nom'] ?? $ligne['prenom_nom'] ?? ''));
            $parts = (int) preg_replace('/\D/', '', (string) ($ligne['parts_chiffres'] ?? ''));

            if ($nom === '' || $parts <= 0) {
                continue;
            }

            $associes[] = ['nom' => $nom, 'client_id' => $ligne['client_id'] ?? null, 'nb_parts' => $parts];
        }

        return $associes;
    }

    /**
     * Les parties du dossier, avec les parts **déduites**.
     *
     * ⚠️ C'est une déduction, pas une donnée : un associé unique détient tout, et à plusieurs on
     * répartit à parts égales faute de mieux. L'étude corrigera depuis la fiche — mais une
     * répartition plausible vaut mieux qu'une table vide, qui empêcherait toute cession.
     * Le reliquat de la division va au premier, pour que le total tombe juste.
     *
     * @return array<int, array<string, mixed>>
     */
    private function depuisParties(Societe $societe, Dossier $dossier): array
    {
        $parties = Partie::where('dossier_id', $dossier->id)
            ->whereIn('role', ['associe', 'associe_unique'])
            ->get();

        if ($parties->isEmpty()) {
            return [];
        }

        $total = (int) ($societe->nombre_parts ?? 0);

        if ($total <= 0) {
            return [];
        }

        $base = intdiv($total, $parties->count());
        $reliquat = $total - $base * $parties->count();

        return $parties->values()->map(fn (Partie $p, int $i) => [
            'nom'       => $p->nom,
            'client_id' => $p->client_id,
            'nb_parts'  => $base + ($i === 0 ? $reliquat : 0),
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function gerantsDe(Societe $societe): array
    {
        $nom = $societe->gerantActuel();

        if (blank($nom)) {
            return [];
        }

        $partie = $societe->dossier
            ? Partie::where('dossier_id', $societe->dossier_id)->where('nom', $nom)->first()
            : null;

        return [[
            'nom'        => $nom,
            'client_id'  => $partie?->client_id,
            // Le gérant du dossier de constitution est nommé dans les statuts, par définition.
            'statutaire' => true,
        ]];
    }

    /**
     * @param array<int, array<string, mixed>> $associes
     * @param array<int, array<string, mixed>> $gerants
     */
    private function ecrire(Societe $societe, array $associes, array $gerants, EtatSocieteService $etats): void
    {
        // La date d'entrée est celle de la constitution quand on la connaît : poser `now()`
        // ferait croire que tous les associés sont entrés le jour de la reprise.
        $depuis = $societe->date_constitution?->toDateString()
            ?? $societe->date_acte?->toDateString()
            ?? $societe->created_at?->toDateString()
            ?? now()->toDateString();

        foreach ($associes as $a) {
            SocieteAssocie::create([
                'societe_id'        => $societe->id,
                'client_id'         => $a['client_id'],
                'nom'               => $a['nom'],
                'nb_parts'          => $a['nb_parts'],
                'date_entree'       => $depuis,
                'dossier_entree_id' => $societe->dossier_id,
            ]);
        }

        foreach ($gerants as $g) {
            SocieteGerant::create([
                'societe_id'            => $societe->id,
                'client_id'             => $g['client_id'],
                'nom'                   => $g['nom'],
                'statutaire'            => $g['statutaire'],
                'date_nomination'       => $depuis,
                'dossier_nomination_id' => $societe->dossier_id,
            ]);
        }

        // La première ligne d'historique — écrite par le service, pas ici. Une société externe
        // créée depuis l'assistant reçoit exactement la même
        // ({@see SocieteMutationService::poserEtatInitial()}) : deux copies de cette écriture
        // auraient dérivé, et une timeline qui commence différemment selon la porte d'entrée
        // n'est pas une timeline.
        app(SocieteMutationService::class)->poserEtatInitial($societe, $etats, $depuis);
    }
}
