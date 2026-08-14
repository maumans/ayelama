<?php

namespace App\Services;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\RubriqueCloture;
use App\Models\Client;
use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\JournalActivite;
use App\Models\ModeleCourrier;
use App\Models\Revision;
use App\Models\User;
use App\Notifications\CertificationValideeNotification;
use App\Notifications\DossierRenvoyeNotification;
use App\Notifications\FormalitesAFaireNotification;
use App\Notifications\RevisionEnAttenteNotification;
use App\Notifications\SignatureClientEnAttenteNotification;
use App\Services\ActesGeneratorService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DossierStepService
{
    public function __construct(
        private NotificationService $notifications,
        private InventaireClotureService $inventaire,
        private ActesGeneratorService $actes,
        private ReglesSocieteService $reglesSociete,
        private SocieteMutationService $societeMutation,
    ) {}

    public function avancer(Dossier $dossier, User $user): Dossier
    {
        $this->verifierPrerequis($dossier);

        $etapeSuivante = $dossier->etape->suivante();

        if (!$etapeSuivante) {
            throw ValidationException::withMessages([
                'etape' => ['Ce dossier est déjà à l\'étape finale.'],
            ]);
        }

        $ancienneEtape = $dossier->etape;
        $dossier->etape = $etapeSuivante;
        $dossier->save();

        // Les actes sont produits en entrant en Édition, et non à la création : ils
        // reflètent ainsi un questionnaire que le client a validé par son accord signé.
        // genererActesDepuisModeles() n'écrase jamais un acte déjà présent — un renvoi en
        // correction repasse ici, et les corrections manuelles doivent survivre.
        if ($etapeSuivante === EtapeDossier::Edition) {
            $crees = $this->actes->genererActesDepuisModeles($dossier);
            if ($crees > 0) {
                JournalActivite::enregistrer(
                    $dossier,
                    "{$crees} acte(s) généré(s) depuis les modèles du type d'acte",
                    'generation',
                    [],
                    $user,
                );
            }
        }

        // Créer la révision automatiquement si on arrive en révision
        if ($etapeSuivante === EtapeDossier::Revision) {
            $revision = Revision::firstOrCreate(
                ['dossier_id' => $dossier->id],
                [
                    'reviseur_id' => $dossier->reviseur_id,
                    'statut'      => \App\Enums\StatutRevision::EnAttente,
                ]
            );
            // Nouveau round (re-entry après renvoi en édition) : table rase des verdicts précédents
            if (!$revision->wasRecentlyCreated) {
                $revision->resetPoints();
            }

            // Le certificateur seul est concerné — l'envoi partait auparavant aux
            // 4 ayants droit, ce qui noyait le formaliste et le notaire sous des
            // notifications sur lesquelles ils n'ont aucune action à mener.
            $this->notifications->notifierRoles(
                $dossier,
                RoleUtilisateur::Reviseur,
                new RevisionEnAttenteNotification($dossier),
            );
        }

        if ($etapeSuivante === EtapeDossier::Signature) {
            // Le notaire doit signer ; le rédacteur apprend que son dossier a
            // franchi l'étape bloquante.
            $this->notifications->notifierRoles(
                $dossier,
                [RoleUtilisateur::Notaire, RoleUtilisateur::Clerc],
                new CertificationValideeNotification($dossier),
            );

            // Le repli est indispensable ici : sans notaire assigné, ce passage
            // ne notifiait strictement personne.
            $this->notifications->notifierRoles(
                $dossier,
                RoleUtilisateur::Notaire,
                new SignatureClientEnAttenteNotification($dossier),
            );
        }

        if ($etapeSuivante === EtapeDossier::Formalites) {
            $this->notifications->notifierRoles(
                $dossier,
                RoleUtilisateur::Formaliste,
                new FormalitesAFaireNotification($dossier),
            );
        }

        // Générer automatiquement les lettres de transmission applicables en
        // arrivant en Expédition — toutes les données du dossier sont déjà
        // définitives à ce stade, aucune raison d'attendre un clic manuel.
        if ($etapeSuivante === EtapeDossier::Expedition) {
            $this->genererLettresTransmission($dossier, $user);

            // Les formalités sont revenues : la modification statutaire est enregistrée au
            // RCCM, donc opposable. C'est le moment de porter le nouveau siège, le nouveau
            // capital, le nouvel objet ou le nouveau gérant à la fiche du registre — sans
            // quoi le prochain dossier de modification se préremplirait avec l'état périmé.
            $this->societeMutation->appliquer($dossier, $user);
        }

        // Un client ajouté pendant la création du dossier n'est qu'un prospect tant que le
        // dossier n'a pas abouti — il devient client confirmé une fois le dossier clôturé.
        if ($etapeSuivante === EtapeDossier::Cloture) {
            // pluck()->unique() plutôt que ->distinct() en SQL : Dossier::parties() applique
            // un orderBy('role') par défaut, incompatible avec DISTINCT en SQL strict (MySQL).
            $clientIds = $dossier->parties()->whereNotNull('client_id')->pluck('client_id')->unique();
            Client::whereIn('id', $clientIds)->where('statut', 'prospect')->update(['statut' => 'client']);
        }

        JournalActivite::enregistrer(
            $dossier,
            "Dossier avancé de « {$ancienneEtape->label()} » à « {$etapeSuivante->label()} »",
            'etape',
            ['de' => $ancienneEtape->value, 'vers' => $etapeSuivante->value],
            $user
        );

        return $dossier->fresh();
    }

    /**
     * Génère une fois chaque lettre de transmission applicable (celles pas
     * déjà générées, identifiées par leur nom) — même logique que
     * CourrierController::genererDepuisModele(), déclenchée automatiquement
     * plutôt que sur clic.
     */
    private function genererLettresTransmission(Dossier $dossier, User $user): void
    {
        $dossier->loadMissing('typeActe', 'questionnaire', 'courriers');

        $modeles = ModeleCourrier::with('typesActes')
            ->actif()
            ->get()
            ->filter(fn (ModeleCourrier $m) => $m->applicablePour($dossier->typeActe));

        if ($modeles->isEmpty()) {
            return;
        }

        $dejaGenerees = $dossier->courriers->where('type', 'transmission')->pluck('objet')->all();
        $generatorService = app(ActesGeneratorService::class);

        foreach ($modeles as $modele) {
            if (in_array($modele->nom, $dejaGenerees, true)) {
                continue;
            }

            $chemin = $generatorService->genererDocument($dossier, $modele->chemin_fichier, Str::slug($modele->nom));

            $annee     = now()->year;
            $count     = Courrier::whereYear('created_at', $annee)->count() + 1;
            $reference = 'COU-' . $annee . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            Courrier::create([
                'reference'      => $reference,
                'dossier_id'     => $dossier->id,
                'redacteur_id'   => $user->id,
                'destinataire'   => '',
                'objet'          => $modele->nom,
                'type'           => 'transmission',
                'statut'         => 'brouillon',
                'chemin_fichier' => $chemin,
            ]);
        }

        JournalActivite::enregistrer($dossier, 'Lettres de transmission générées automatiquement', 'expedition', [], $user);
    }

    public function reculer(Dossier $dossier, User $user, ?string $motif = null): Dossier
    {
        $etapePrecedente = $dossier->etape->precedente();

        if (!$etapePrecedente) {
            throw ValidationException::withMessages([
                'etape' => ['Ce dossier est déjà à l\'étape initiale.'],
            ]);
        }

        $ancienneEtape = $dossier->etape;
        $dossier->etape = $etapePrecedente;
        $dossier->save();

        $action = "Dossier renvoyé de « {$ancienneEtape->label()} » à « {$etapePrecedente->label()} »";
        if ($motif) {
            $action .= " — Motif : {$motif}";
        }

        JournalActivite::enregistrer($dossier, $action, 'renvoye', ['motif' => $motif], $user);

        // Le rédacteur est le seul à devoir agir sur un renvoi — il n'était averti
        // par rien jusqu'ici et devait constater le retour en rouvrant le dossier.
        // $user exclu : celui qui renvoie n'a pas besoin d'être notifié de son
        // propre geste (cas d'un notaire à la fois certificateur et rédacteur).
        $this->notifications->notifierRoles(
            $dossier,
            RoleUtilisateur::Clerc,
            new DossierRenvoyeNotification($dossier, $ancienneEtape->label(), $etapePrecedente->label(), $motif),
            sauf: $user,
        );

        return $dossier->fresh();
    }

    /**
     * Prérequis à remplir pour quitter l'étape courante.
     *
     * `match` **exhaustif**, sans branche `default` : ajouter une étape à
     * EtapeDossier provoque une erreur PHP tant qu'on n'a pas décidé ce qu'elle
     * exige. Un `default => null` laisserait au contraire la nouvelle étape
     * franchissable sans aucun contrôle, en silence — même classe de piège que la
     * comparaison `!== 'cloture'` qui avait bloqué l'étape Formalités.
     */
    private function verifierPrerequis(Dossier $dossier): void
    {
        match ($dossier->etape) {
            EtapeDossier::Initialisation => $this->verifierInitialisation($dossier),
            EtapeDossier::Edition    => $this->verifierEdition($dossier),
            EtapeDossier::Revision   => $this->verifierRevisionValidee($dossier),
            EtapeDossier::Signature  => $this->verifierSignature($dossier),
            EtapeDossier::Formalites => $this->verifierFormalites($dossier),
            EtapeDossier::Expedition => $this->verifierExpedition($dossier),
            // Étape terminale : avancer() a déjà refusé plus haut (suivante() est
            // nulle), on n'arrive jamais ici. Listée explicitement pour l'exhaustivité.
            EtapeDossier::Cloture    => null,
        };
    }

    /**
     * Prérequis pour quitter l'Initialisation : le dossier doit être constitué.
     *
     * Ces cinq contrôles vivaient dans verifierEdition(), qui mélangeait « constituer le
     * dossier » et « produire les actes ». Ils gardent l'Initialisation, dont ils sont le
     * métier.
     */
    private function verifierInitialisation(Dossier $dossier): void
    {
        $errors = $this->erreursDeConstitution($dossier);

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Prérequis pour quitter l'Édition : au moins un acte produit.
     *
     * Les contrôles de constitution sont **volontairement rejoués ici**, alors qu'ils
     * appartiennent à l'Initialisation. Raison : les dossiers créés avant le 2026-08-04
     * sont déjà en Édition ou au-delà et n'ont jamais franchi d'Initialisation ; sans ce
     * double contrôle, l'un d'eux pourrait filer en Certification sans accord client ni
     * pièces d'identité. À retirer quand plus aucun dossier antérieur ne sera en cours.
     */
    private function verifierEdition(Dossier $dossier): void
    {
        $errors = $this->erreursDeConstitution($dossier);

        $dossier->loadMissing('documents');
        if ($dossier->documents->where('categorie', '!=', 'accord_client')->isEmpty()) {
            $errors['documents'] = ['Au moins un acte doit avoir été produit avant de passer à la certification.'];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Ce qui manque à la constitution du dossier — partagé par verifierInitialisation()
     * et verifierEdition() pour que les deux ne puissent pas diverger.
     *
     * @return array<string, string[]>
     */
    private function erreursDeConstitution(Dossier $dossier): array
    {
        $dossier->loadMissing('documents', 'parties.pieces.versionActuelle');
        $errors = [];

        if (empty(trim($dossier->objet ?? ''))) {
            $errors['objet'] = ["L'objet du dossier doit être renseigné."];
        }
        if (!$dossier->notaire_id) {
            $errors['notaire'] = ['Un notaire doit être assigné au dossier.'];
        }
        if (!$dossier->reviseur_id) {
            $errors['reviseur'] = ['Un certificateur doit être assigné au dossier.'];
        }

        $piecesManquantes = $dossier->parties->contains(
            fn ($p) => collect($p->piecesChecklist())->contains(fn ($item) => !$item['est_fourni'])
        );
        if ($piecesManquantes) {
            $errors['pieces'] = ["Toutes les pièces justificatives des personnes au dossier doivent être fournies."];
        }

        // Le libellé vient du dossier : une modification de statuts attend la décision des
        // associés, pas une fiche de recueil signée. Message identique à celui de l'écran — un
        // appel direct à l'API doit dire la même chose que l'interface.
        $accordAttendu = $dossier->pieceAccordAttendue();
        $accordClient  = $dossier->documents->firstWhere('categorie', $accordAttendu['categorie']);
        if (!$accordClient?->est_signe_cachete) {
            $errors['accord_client'] = [sprintf('« %s » doit être téléversé.', $accordAttendu['nom'])];
        }

        // Règles légales de constitution (capital minimum, associé unique, commissaire aux
        // comptes, capacité juridique) — CR de juillet 2026. Bloquantes au même titre que
        // l'accord client : produire les statuts d'une SA sous-capitalisée expose l'office.
        // Ne renvoie rien hors dossiers de société.
        $errors = array_merge($errors, $this->reglesSociete->anomalies($dossier));

        return $errors;
    }

    private function verifierSignature(Dossier $dossier): void
    {
        $errors = [];
        if (!$dossier->date_signature_client) {
            $errors['date_signature_client'] = ["La date de signature du client doit être renseignée avant de passer aux formalités."];
        }
        if (!$dossier->date_signature_notaire) {
            $errors['date_signature_notaire'] = ["La date de signature du notaire doit être renseignée avant de passer aux formalités."];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function verifierRevisionValidee(Dossier $dossier): void
    {
        if (!$dossier->revisionValidee()) {
            $statut = $dossier->revision?->statut?->value;
            $msg = match ($statut) {
                'renvoye'    => 'La certification a été renvoyée en correction. Corrigez les points signalés puis soumettez à nouveau.',
                'en_attente' => 'La certification est en attente. Elle doit être évaluée et validée par le certificateur.',
                'en_cours'   => 'La certification est en cours. Elle doit être validée avant de continuer.',
                default      => 'La certification doit être validée avant de passer aux formalités.',
            };
            throw ValidationException::withMessages(['revision' => [$msg]]);
        }
    }

    private function verifierFormalites(Dossier $dossier): void
    {
        $dossier->loadMissing('formalites');

        if ($dossier->formalites->isEmpty()) {
            return;
        }

        // « Terminée » = retour reçu de l'organisme, depuis la suppression du statut
        // `Cloture` (l'étape de clôture par formalité a été retirée). La condition
        // était restée sur `!== 'cloture'` : plus aucune formalité ne pouvant porter
        // ce statut, le passage à l'Expédition était définitivement bloqué. La règle
        // vit maintenant dans StatutFormalite::estTerminee(), dont le `match`
        // exhaustif force à classer tout nouveau statut.
        $enCours = $dossier->formalites->reject(fn ($f) => $f->statut?->estTerminee());

        if ($enCours->isEmpty()) {
            return;
        }

        // Un rejet et une attente de retour bloquent tous deux, mais la première
        // demande une action du formaliste et la seconde ne dépend que de
        // l'organisme : les confondre laisserait l'utilisateur sans savoir quoi faire.
        $aCorriger = $enCours->filter(fn ($f) => $f->statut?->exigeCorrection());
        $enAttente = $enCours->reject(fn ($f) => $f->statut?->exigeCorrection());

        $messages = [];
        if ($aCorriger->isNotEmpty()) {
            $messages[] = 'À corriger et redéposer : ' . $aCorriger->pluck('organisme')->unique()->join(', ') . '.';
        }
        if ($enAttente->isNotEmpty()) {
            $messages[] = 'En attente de retour : ' . $enAttente->pluck('organisme')->unique()->join(', ') . '.';
        }

        throw ValidationException::withMessages([
            'formalites' => [
                'Toutes les formalités doivent avoir reçu leur retour avant de passer à l\'expédition. '
                . implode(' ', $messages),
            ],
        ]);
    }

    /**
     * Clôture : toutes les pièces de l'inventaire doivent avoir été vérifiées.
     *
     * Remplace la règle « tout document configuré obligatoire doit avoir sa version
     * signée/cachetée » (`est_requis` alimenté depuis `ModeleActe.obligatoire_cloture`).
     * Cette configuration déclarait à l'avance ce qui devrait exister, alors que le
     * workflow produit lui-même toutes les pièces — et pouvait la contredire : un modèle
     * coché obligatoire mais jamais généré bloquait la clôture sans recours.
     *
     * Désormais un contrôle humain explicite sur l'inventaire réel : des pièces
     * d'origines hétérogènes (actes, CNI, retours d'organismes, courriers, reçus)
     * qu'aucune règle automatique ne peut déclarer complètes.
     */
    private function verifierExpedition(Dossier $dossier): void
    {
        $erreurs = [];

        // Facture soldée : un dossier clos est un dossier payé. Aucun contrôle n'existait
        // — un dossier avait été clôturé avec 5 195 000 GNF impayés.
        if (!$dossier->estSolde()) {
            $erreurs['facturation'] = [sprintf(
                'Facture non soldée : %s GNF restent à encaisser. Un dossier ne peut pas être clôturé avec un solde impayé.',
                number_format($dossier->soldeRestant(), 0, ',', ' '),
            )];
        }

        if ($erreurs) {
            throw ValidationException::withMessages($erreurs);
        }
    }
}
