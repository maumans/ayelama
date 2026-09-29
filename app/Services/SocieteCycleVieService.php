<?php

namespace App\Services;

use App\Contracts\EffetSurLaFicheSociete;
use App\Enums\StatutSociete;
use App\Enums\VarianteDissolution;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\User;
use App\Support\VariantesTypeActe;
use Illuminate\Support\Carbon;

/**
 * Porte au registre le changement d'état qu'un dossier de dissolution a décidé.
 *
 * Pendant exact de {@see SocieteMutationService} pour le cycle de vie : même moment (entrée en
 * Expédition), même journalisation, même exigence d'idempotence. Ce qu'il écrit n'est pas une
 * donnée statutaire mais l'**état** de la personne morale — active, en liquidation, liquidation
 * clôturée — plus la date du jalon et le liquidateur nommé.
 *
 * ⚠️ `Radiee` n'est **jamais** posé ici. La radiation n'est prouvée que par la pièce du greffe,
 * et aucune étape de dossier ne la constate : le dossier de clôture est terminé bien avant que
 * le greffe ne réponde. La rattacher à une formalité supposerait de déclarer *laquelle* vaut
 * radiation — donc d'inventer. Elle reste une action humaine explicite sur la fiche
 * (`PATCH /societes/{societe}/radiation`).
 */
class SocieteCycleVieService implements EffetSurLaFicheSociete
{
    public function nom(): string
    {
        return 'cycle de vie de la société';
    }

    public function concerne(Dossier $dossier): bool
    {
        $dossier->loadMissing('typeActe', 'questionnaire', 'societe');

        return $dossier->typeActe?->code === 'SOC-DIS'
            && $dossier->societe !== null
            && $this->phase($dossier) !== null;
    }

    /**
     * @return array<string, array{avant: mixed, apres: mixed}>
     */
    public function appliquer(Dossier $dossier, ?User $user = null): array
    {
        if (! $this->concerne($dossier)) {
            return [];
        }

        $phase   = $this->phase($dossier);
        $societe = $dossier->societe;

        $changements = [];

        if ($societe->statut !== $phase->statutApres()) {
            $changements['statut'] = [
                'avant' => $societe->statut->value,
                'apres' => $phase->statutApres()->value,
            ];
        }

        // La date du jalon vient de l'assemblée, pas de `now()` : c'est elle qui fait courir
        // les échéances de liquidation, et un dossier peut entrer en Expédition des mois après
        // la décision.
        $colonne = $phase->colonneDate();
        $date    = $this->dateAssemblee($dossier, $phase);

        if ($date && (string) $societe->{$colonne}?->format('Y-m-d') !== $date->format('Y-m-d')) {
            $changements[$colonne] = [
                'avant' => $societe->{$colonne}?->format('d/m/Y'),
                'apres' => $date->format('d/m/Y'),
            ];
        }

        // Le liquidateur va dans le JSON `direction`, au même endroit que le gérant : c'est un
        // dirigeant de plus, pas une nature d'information nouvelle. Zéro colonne ajoutée.
        $direction = $this->directionMiseAJour($dossier, $phase, $societe->direction ?? []);

        if ($direction !== ($societe->direction ?? [])) {
            $changements['direction'] = [
                'avant' => $societe->direction['liquidateur'] ?? null,
                'apres' => $direction['liquidateur'] ?? null,
            ];
        }

        if ($changements === []) {
            // Idempotence : le dossier repasse en Expédition après un renvoi en correction, et
            // tout est déjà en place. Rien à écrire, rien à journaliser.
            return [];
        }

        $societe->update([
            'statut'    => $phase->statutApres(),
            ...($date ? [$colonne => $date] : []),
            'direction' => $direction,
        ]);

        JournalActivite::enregistrer(
            $dossier,
            sprintf(
                'Fiche société « %s » : statut porté à « %s »',
                $societe->denomination,
                $phase->statutApres()->label(),
            ),
            'societe',
            $changements,
            $user,
        );

        return $changements;
    }

    /**
     * Ramène la fiche à l'état d'avant, si elle porte encore celui que ce dossier a posé.
     *
     * Le revert est sûr ici, contrairement à celui d'une modification statutaire : l'état
     * précédent se **déduit** de la phase (`statutRequis()`), il n'est pas reconstitué depuis
     * un questionnaire dont les valeurs ont pu être corrigées entre-temps.
     *
     * La garde est indispensable : si la fiche n'est plus dans l'état que ce dossier avait posé,
     * quelqu'un d'autre est passé après lui, et le revert écraserait son travail.
     *
     * @return array<string, array{avant: mixed, apres: mixed}>
     */
    public function annuler(Dossier $dossier, ?User $user = null): array
    {
        if (! $this->concerne($dossier)) {
            return [];
        }

        $phase   = $this->phase($dossier);
        $societe = $dossier->societe;

        if ($societe->statut !== $phase->statutApres()) {
            // Un autre dossier, ou une correction manuelle, est passé depuis. Ne rien défaire,
            // mais le dire : un retour arrière silencieusement sans effet est indiscernable
            // d'un retour arrière réussi.
            JournalActivite::enregistrer(
                $dossier,
                sprintf(
                    'Retour arrière sans effet sur la fiche « %s » : son statut (« %s ») n\'est plus celui que ce dossier avait posé (« %s »). Vérifiez le registre.',
                    $societe->denomination,
                    $societe->statut->label(),
                    $phase->statutApres()->label(),
                ),
                'societe',
                [],
                $user,
            );

            return [];
        }

        $changements = ['statut' => [
            'avant' => $societe->statut->value,
            'apres' => $phase->statutRequis()->value,
        ]];

        $societe->update([
            'statut'               => $phase->statutRequis(),
            $phase->colonneDate()  => null,
        ]);

        JournalActivite::enregistrer(
            $dossier,
            sprintf(
                'Fiche société « %s » : statut ramené à « %s » (dossier renvoyé en correction)',
                $societe->denomination,
                $phase->statutRequis()->label(),
            ),
            'societe',
            $changements,
            $user,
        );

        return $changements;
    }

    private function phase(Dossier $dossier): ?VarianteDissolution
    {
        foreach (VariantesTypeActe::duDossier($dossier) as $variante) {
            if ($variante instanceof VarianteDissolution) {
                return $variante;
            }
        }

        return null;
    }

    /**
     * Date de l'assemblée de cette phase, convertie depuis `JJ/MM/AAAA`.
     *
     * ⚠️ `createFromFormat('d/m/Y')` **explicitement**, jamais `Carbon::parse` : c'est lui qui a
     * inversé sept dates au jour et au mois (décision #40). `donnees` porte du français, la
     * colonne est castée en ISO — le contrat est en tête de `resources/js/lib/dates.js`.
     */
    private function dateAssemblee(Dossier $dossier, VarianteDissolution $phase): ?Carbon
    {
        $valeur = $dossier->questionnaire?->donnees[$phase->cleDateAssemblee()] ?? null;

        if (blank($valeur) || ! is_string($valeur)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', trim($valeur))->startOfDay();
        } catch (\Exception) {
            // Une date illisible ne doit pas empêcher le statut d'être porté : le jalon restera
            // vide, ce qui est visible, plutôt que de bloquer l'avancement du dossier.
            return null;
        }
    }

    /**
     * @param  array<string, mixed> $direction
     * @return array<string, mixed>
     */
    private function directionMiseAJour(Dossier $dossier, VarianteDissolution $phase, array $direction): array
    {
        // Le liquidateur n'est nommé qu'à la dissolution. À la clôture, il est en fonction
        // depuis des mois et déjà au registre : le réécrire depuis un questionnaire qui ne le
        // demande plus l'effacerait.
        if ($phase !== VarianteDissolution::Dissolution) {
            return $direction;
        }

        $donnees = $dossier->questionnaire?->donnees ?? [];
        $nom     = $donnees['liquidateur.prenom_nom'] ?? null;

        if (blank($nom)) {
            return $direction;
        }

        return [
            ...$direction,
            'liquidateur'         => trim((string) $nom),
            'liquidateur_qualite' => filled($donnees['liquidateur.qualite'] ?? null)
                ? trim((string) $donnees['liquidateur.qualite'])
                : null,
        ];
    }
}
