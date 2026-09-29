<?php

namespace App\Services;

use App\Enums\TypeModificationStatutaire;
use App\Enums\DonneeAuRetour;
use App\Models\Dossier;
use App\Models\DocumentFichier;
use App\Models\JournalActivite;
use App\Models\DocumentAttendu;
use App\Models\ModeleActe;
use App\Models\RevisionPoint;
use App\Models\TypeActe;
use App\Support\VariantesTypeActe;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class ActesGeneratorService
{
    /**
     * Régénère un document déjà généré depuis son modèle actif, avec les données
     * (questionnaire) à jour. Retourne false sans effet si aucun modèle actif ne
     * correspond — appelant libre d'ignorer silencieusement ce cas (ex. régénération
     * en masse après édition du questionnaire) ou de le signaler (régénération manuelle).
     */
    public function regenererDocument(DocumentFichier $document): bool
    {
        $dossier = $document->documentable;

        // `pourTypeActe` et non un `where` sur la colonne : un modèle peut servir plusieurs types
        // depuis le 2026-08-11 (les statuts d'une SARLU servent aussi à sa modification).
        $modele = ModeleActe::pourTypeActe($dossier->type_acte_id)
            ->where('nom', $document->nom)
            ->where('est_actif', true)
            ->first();

        // `gabaritPour()` et non le modèle seul : sans ce partage, « Régénérer » repartirait du
        // gabarit de l'étude et écraserait des statuts reconstruits depuis ceux d'une société qu'on
        // n'a pas constituée. C'est aussi ce qui permet de régénérer alors qu'**aucun** modèle actif
        // n'existe — le cas des statuts hérités, dont le modèle `statuts_maj` reste inactif faute de
        // gabarit fourni à l'étude.
        $gabarit = $this->gabaritPour($dossier, $document->categorie, $modele);

        if (! $gabarit) {
            return false;
        }

        // Le contenu change : la vérification déjà enregistrée pour ce document n'est
        // plus fiable, on la marque périmée plutôt que de la supprimer — le verdict et
        // le commentaire du certificateur restent visibles (contexte pour le rédacteur)
        // jusqu'au prochain envoi en certification, qui les purgera (Revision::resetPoints()).
        RevisionPoint::where('point_id', (string) $document->id)
            ->whereHas('revision', fn ($q) => $q->where('dossier_id', $dossier->id))
            ->update(['perime' => true]);

        $referenceActe = $document->reference_acte;
        if (! $referenceActe) {
            $numeroActe = DocumentFichier::where('documentable_type', Dossier::class)
                ->where('documentable_id', $dossier->id)
                ->max('numero_acte') + 1;
                
            $notaireInitials = $dossier->notaire?->initiales ?? 'XX';
            $redacteurInitials = $dossier->redacteur?->initiales ?? 'XX';
            $referenceActe = sprintf('%s/%s/%s/%02d', $notaireInitials, $redacteurInitials, $dossier->reference, $numeroActe);
            
            $document->numero_acte = $numeroActe;
            $document->reference_acte = $referenceActe;
            $document->save();
        }

        $chemin = $this->genererDepuisGabarit($dossier, $gabarit, Str::slug($document->nom), $referenceActe);

        $document->nouvelleVersion($chemin, 'documents/' . $dossier->reference, ['source' => 'genere']);
        $document->update(['statut' => 'a_editer']);

        return true;
    }

    /**
     * Génère les actes du dossier depuis les modèles actifs de son type d'acte.
     *
     * Appelée au passage Initialisation → Édition (`DossierStepService::avancer()`), et
     * non plus à la création : les actes sont ainsi produits sur un questionnaire que le
     * client a validé par son accord signé.
     *
     * **Ne régénère jamais un acte déjà présent** — correspondance par `nom`, même
     * convention que `DocumentController::regenerer()`. Sans ce garde-fou, un renvoi en
     * correction (qui ramène le dossier en Édition, donc repasse ici) écraserait les
     * actes corrigés à la main. Pour régénérer volontairement, il y a le bouton dédié.
     *
     * @return int Nombre d'actes effectivement créés.
     */
    public function genererActesDepuisModeles(Dossier $dossier): int
    {
        return $this->produireActes($dossier)['crees'];
    }

    /**
     * Produit les actes et **dit ce qui s'est passé** — `crees` et `attendus`.
     *
     * Nécessaire pour distinguer trois situations que le bouton « Générer » confondait en un seul
     * message trompeur : aucun gabarit configuré pour ce processus, tout est déjà présent au dossier,
     * ou *n* actes produits. Le premier cas est un défaut de configuration, les deux autres non.
     *
     * @return array{crees: int, attendus: int}
     */
    public function produireActes(Dossier $dossier): array
    {
        $dossier->loadMissing('documents', 'questionnaire', 'typeActe');
        $dejaPresents = $dossier->documents->pluck('nom')->all();

        $aProduire = $this->actesAProduire($dossier);
        $crees     = 0;

        foreach ($aProduire as $acte) {
            if (in_array($acte['nom'], $dejaPresents, true)) {
                continue;
            }

            $numeroActe = DocumentFichier::where('documentable_type', Dossier::class)
                ->where('documentable_id', $dossier->id)
                ->max('numero_acte') + 1;
                
            $notaireInitials = $dossier->notaire?->initiales ?? 'XX';
            $redacteurInitials = $dossier->redacteur?->initiales ?? 'XX';
            $referenceActe = sprintf('%s/%s/%s/%02d', $notaireInitials, $redacteurInitials, $dossier->reference, $numeroActe);

            $chemin = $this->genererDepuisGabarit($dossier, $acte['gabarit'], Str::slug($acte['nom']), $referenceActe);

            $document = $dossier->documents()->create([
                'nom'       => $acte['nom'],
                'categorie' => $acte['type_document'],
                'statut'    => 'a_editer',
                'numero_acte' => $numeroActe,
                'reference_acte' => $referenceActe,
            ]);
            $document->nouvelleVersion($chemin, 'documents/' . $dossier->reference, ['source' => 'genere']);
            $crees++;
        }

        return ['crees' => $crees, 'attendus' => count($aProduire)];
    }

    /**
     * Actes à produire pour ce dossier : leur nom, leur `type_document` et le gabarit à employer.
     *
     * Deux sources, dans cet ordre :
     *
     *   1. les **modèles actifs** du type d'acte, filtrés par les modifications décidées
     *      (`typesDocumentsRetenus()`) ;
     *   2. les types dont le gabarit peut être **hérité de la société** (§TYPES_A_GABARIT_HERITABLE).
     *      Cette seconde passe est indispensable : le modèle `statuts_maj` est seedé **inactif**,
     *      faute de gabarit fourni à l'étude. Sans elle, « Statuts mis à jour » n'apparaîtrait
     *      jamais au dossier alors que les statuts déposés permettent parfaitement de le produire.
     *
     * @return array<int, array{nom: string, type_document: string, gabarit: array}>
     */
    private function actesAProduire(Dossier $dossier): array
    {
        $typeActe  = $dossier->typeActe;
        $variantes = $this->variantesDuDossier($dossier);

        $aProduire = [];

        foreach ($this->actesPrevus($typeActe, $variantes) as $prevu) {
            // `gabaritPour()` a besoin du dossier — il peut hériter des statuts de la société, ce
            // que `actesPrevus()` ne sait pas. Un acte annoncé « attendu sans gabarit » peut donc
            // devenir productible ici, et c'est voulu.
            $gabarit = $this->gabaritPour($dossier, $prevu['type_document'], $prevu['modele']);

            if (! $gabarit) {
                continue;
            }

            $aProduire[] = [
                'nom'           => $prevu['nom'],
                'type_document' => $prevu['type_document'],
                'gabarit'       => $gabarit,
            ];
        }

        return $aProduire;
    }

    /**
     * Ce qu'une procédure produira, **sans dossier** : son type d'acte et ses variantes suffisent.
     *
     * Extrait de `actesAProduire()` pour que l'assistant puisse annoncer les actes **avant** que le
     * dossier existe. La règle — rôle attendu × variante × `applicable_tous` — n'est écrite qu'ici :
     * la réécrire en JavaScript pour l'aperçu garantirait la divergence entre ce qui est annoncé et
     * ce qui est produit, exactement le défaut que la carte « Actes à produire » présentait.
     *
     * Les lignes **sans gabarit** sont retournées elles aussi, marquées : c'est précisément
     * l'information utile avant création. `actesAProduire()` les écarte ensuite.
     *
     * @param  array<int, string> $variantes valeurs techniques
     * @return array<int, array{nom: string, type_document: string, modele: ?ModeleActe, a_gabarit: bool}>
     */
    public function actesPrevus(?TypeActe $typeActe, array $variantes = []): array
    {
        if (! $typeActe) {
            return [];
        }

        $retenus   = $this->typesDocumentsRetenusPour($typeActe, $variantes);
        $estRetenu = fn (?string $type) => $retenus === null || in_array($type, $retenus, true);

        // Tous les modèles, actifs ou non : les inactifs servent à retrouver le **nom** attendu d'un
        // acte dont le gabarit est hérité ou manquant, plutôt que d'en inventer un.
        // Les rôles ne sont pas une relation Eloquent : `rolesRemplis()` les lit en mémoïsant
        // (voir ModeleActe), il n'y a donc rien à charger d'avance pour eux.
        $modeles = ModeleActe::pourTypeActe($typeActe->id)
            ->with('rattachements')
            ->orderBy('type_document')
            ->get();

        $prevus        = [];
        $typesCouverts = [];

        foreach ($modeles->where('est_actif', true) as $modele) {
            // Un gabarit peut remplir plusieurs rôles : celui qu'on retient est le rôle attendu par
            // cette procédure, pas nécessairement son rôle principal. C'est ce qui permet à un
            // gabarit de statuts de servir `acte_principal` en création et `statuts_maj` ici.
            $role = $this->rolePourProcedure($modele, $retenus);
            if ($role === null || ! $estRetenu($role)) {
                continue;
            }

            // Un acte de cession ne doit pas être produit sur un dossier qui ne décide aucune
            // cession, même si son gabarit est actif.
            if (! $this->sertUneVariante($modele, $typeActe, $variantes)) {
                continue;
            }

            $prevus[]        = ['nom' => $modele->nom, 'type_document' => $role, 'modele' => $modele, 'a_gabarit' => true];
            $typesCouverts[] = $role;
        }

        // Rôles attendus par la procédure que **aucun** gabarit actif ne remplit. Sans eux, un
        // manque de configuration resterait invisible jusqu'à la génération.
        foreach ($retenus ?? [] as $role) {
            if (in_array($role, $typesCouverts, true) || in_array($role, self::TYPES_DOCUMENTS_INCONDITIONNELS, true)) {
                continue;
            }

            $prevus[] = [
                'nom'           => $modeles->firstWhere('type_document', $role)?->nom
                    ?? self::TYPES_A_GABARIT_HERITABLE[$role]
                    ?? ModeleActe::TYPES_DOCUMENT[$role]
                    ?? $role,
                'type_document' => $role,
                'modele'        => null,
                'a_gabarit'     => false,
            ];
        }

        // Type **décliné** mais sans règle écrite : la boucle ci-dessus ne tourne pas (`retenus`
        // est nul), si bien qu'un dossier de dissolution n'affichait strictement rien — ni acte,
        // ni manque. Ses deux gabarits sont des marque-places (`est_actif = false`, fichier
        // absent), et un marque-place inactif était donc indiscernable d'une procédure achevée.
        //
        // **Portée volontairement restreinte** aux types déclinés sans référence. Mesuré le
        // 2026-09-28 : 54 des 69 modèles sont inactifs, répartis sur 22 des 24 types. Étendre ce
        // signalement à tous les types aurait rendu bavardes les créations, ventes et baux d'un
        // coup — SOC-SA afficherait sept actes manquants. Le bruit aurait noyé le signal. La
        // restriction n'est pas arbitraire : un type sans variante n'a **pas** de liste attendue,
        // ses actes sont par définition ceux de ses gabarits actifs (voir `processus()`).
        if ($retenus === null && VariantesTypeActe::existePour($typeActe->code)) {
            foreach ($modeles->where('est_actif', false) as $modele) {
                if (! $this->sertUneVariante($modele, $typeActe, $variantes)) {
                    continue;
                }

                $role = $modele->type_document;

                if ($role === null || in_array($role, $typesCouverts, true)) {
                    continue;
                }

                $prevus[]        = [
                    'nom'           => $modele->nom,
                    'type_document' => $role,
                    'modele'        => null,
                    'a_gabarit'     => false,
                ];
                $typesCouverts[] = $role;
            }
        }

        return $prevus;
    }

    /**
     * Types de document dont le gabarit peut venir de la société plutôt que de la bibliothèque de
     * modèles — `type_document` ⇒ nom par défaut si aucun modèle ne le porte.
     *
     * Les statuts seulement, et c'est délibéré : ce sont les seuls dont le contenu appartient à la
     * société et non à l'étude. Un procès-verbal ou une déclaration RCCM se rédigent selon les
     * usages de l'office, pas selon ceux du confrère qui a constitué la société.
     */
    private const TYPES_A_GABARIT_HERITABLE = ['statuts_maj' => 'Statuts mis à jour'];

    /**
     * Gabarit à employer pour un type de document — la décision, en **un seul endroit**.
     *
     * Consommée par `genererActesDepuisModeles()` **et** par `regenererDocument()` : sans ce
     * partage, le bouton « Régénérer » repartirait du modèle de l'étude et écraserait des statuts
     * reconstruits depuis ceux de la société. Même raison qui a fait extraire
     * `Bareme::estApplicableA()` plutôt que de dupliquer le prédicat entre facturation et
     * formalités.
     *
     * @return array{absolu?: string, relatif?: string, herite: bool}|null
     */
    private function gabaritPour(Dossier $dossier, ?string $typeDocument, ?ModeleActe $modele): ?array
    {
        if ($typeDocument === 'statuts_maj' && $dossier->typeActe?->code === 'SOC-MOD') {
            // Trois sources possibles, du plus récent au plus ancien : statuts déposés au registre,
            // statuts mis à jour de la dernière modification effective, statuts du dossier de
            // constitution. Toujours préférable à un gabarit générique, dont ni les articles ni la
            // numérotation ne correspondraient à cette société.
            $enVigueur = $dossier->societe?->gabaritStatutsEnVigueur();

            if ($enVigueur) {
                return [
                    'absolu'  => $enVigueur['absolu'],
                    'herite'  => true,
                    'origine' => $enVigueur['origine'],
                ];
            }
        }

        return $modele ? ['relatif' => $modele->chemin_fichier, 'herite' => false] : null;
    }

    /**
     * `type_document` des modèles à retenir pour ce dossier, ou `null` quand tous
     * s'appliquent — le cas de tous les types d'acte sauf `SOC-MOD`.
     *
     * Une modification de statuts ne produit pas les mêmes actes selon ce qui a été décidé :
     * une cession de parts donne un acte de cession, un transfert de siège n'en donne pas, et
     * une diminution de capital ne donne **aucune** DNSV, contrairement à une augmentation.
     * Sans ce filtre, le dossier recevait tous les modèles du type d'acte : un transfert de
     * siège arrivait en Édition avec un acte de cession vide et une DNSV sans objet, à
     * supprimer à la main.
     *
     * Aucune colonne n'a été ajoutée pour cela : `modeles_actes.type_document` **est** déjà le
     * vocabulaire qui porte la distinction (`dnsv`, `rccm`, `acte_principal`…), et les clés de
     * `TypeModificationStatutaire::documentsRequis()` sont posées dans ce même vocabulaire.
     *
     * @return array<int, string>|null
     */
    /**
     * Variantes décidées par ce dossier — `[]` si son type d'acte ne se décline pas.
     *
     * @return array<int, string> valeurs techniques
     */
    private function variantesDuDossier(Dossier $dossier): array
    {
        // Délègue au registre depuis le 2026-09-28 : cette méthode nommait `modif.types` en dur
        // et ne pouvait donc rendre que des modifications statutaires, alors que sa garde
        // `existePour()` laissait déjà entendre qu'elle traitait tous les types déclinés. Un
        // dossier de dissolution y recevait [] et voyait passer *tous* les gabarits du type.
        return VariantesTypeActe::valeursDuDossier($dossier);
    }

    /**
     * Ce gabarit sert-il au moins une des variantes décidées par le dossier ?
     *
     * Un type d'acte sans variante laisse passer : c'est le cas de toutes les créations, ventes,
     * baux et hypothèques, dont le comportement reste celui d'avant.
     *
     * @param array<int, string> $variantes
     */
    private function sertUneVariante(ModeleActe $modele, TypeActe $typeActe, array $variantes): bool
    {
        if (! VariantesTypeActe::existePour($typeActe->code)) {
            return $modele->applicablePour($typeActe);
        }

        // Aucune variante décidée : seuls les gabarits non restreints passent. Un dossier dont le
        // type de modification n'est pas encore précisé ne doit pas se remplir d'actes hors sujet.
        if ($variantes === []) {
            return $modele->applicablePour($typeActe, null);
        }

        foreach ($variantes as $variante) {
            if ($modele->applicablePour($typeActe, $variante)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rôle sous lequel ce gabarit est retenu pour cette procédure.
     *
     * Un gabarit remplissant plusieurs rôles (statuts de constitution *et* statuts mis à jour) doit
     * être retenu sous celui que la procédure attend, pas sous son rôle principal — sinon un gabarit
     * de statuts partagé serait rejeté par le filtre alors qu'il couvre exactement le besoin.
     *
     * @param array<int, string>|null $retenus
     */
    /**
     * Rôles qu'un type d'acte attend, toutes variantes confondues — `null` s'il n'en déclare aucun.
     *
     * `null` n'est pas « rien » mais « tout » : une constitution, une vente ou un bail acceptent
     * n'importe quel rôle, faute de liste de documents attendus. La modale des modèles s'en sert
     * pour distinguer les rôles pertinents des autres, et ne scinde donc pas sa liste dans ce cas.
     *
     * Motivé par une configuration restée sans effet : un gabarit portait le rôle « Déclaration de
     * modification RCCM » — qui n'existe que dans la procédure de modification — tout en n'étant
     * rattaché qu'à des constitutions. Rien ne le signalait, et l'acte n'était produit nulle part.
     *
     * @return array<int, string>|null
     */
    public function rolesAttendusPour(TypeActe $typeActe): ?array
    {
        $variantes = array_column(VariantesTypeActe::options($typeActe->code), 'valeur');

        // Sans variante, la procédure est unique : ses attendus se lisent d'un coup. Avec, on prend
        // l'union — un gabarit sert le type d'acte, pas une résolution en particulier.
        $cibles = $variantes === [] ? [[]] : array_map(fn (string $v) => [$v], $variantes);

        $roles = [];
        foreach ($cibles as $cible) {
            $retenus = $this->typesDocumentsRetenusPour($typeActe, $cible);

            if ($retenus === null) {
                return null;
            }

            $roles = [...$roles, ...$retenus];
        }

        return array_values(array_unique($roles));
    }

    private function rolePourProcedure(ModeleActe $modele, ?array $retenus): ?string
    {
        $roles = $modele->rolesRemplis();

        if ($retenus === null) {
            // Aucune attente de la procédure — le cas de tous les types d'actes sauf `SOC-MOD`.
            // Le **rôle principal** tranche alors, et non le premier rôle rencontré : `type_document`
            // est la déclaration explicite de l'administrateur, l'ordre d'insertion en table n'en est
            // pas une. Sans cela, un gabarit « RCCM » déclarant aussi `declaration_rccm` produisait
            // un document typé `declaration_rccm` sur une constitution, au seul motif que ce rôle
            // avait été enregistré en premier.
            return in_array($modele->type_document, $roles, true)
                ? $modele->type_document
                : ($roles[0] ?? $modele->type_document);
        }

        foreach ($retenus as $attendu) {
            if (in_array($attendu, $roles, true)) {
                return $attendu;
            }
        }

        return null;
    }

    /**
     * Documents attendus **configurés** pour ce dossier — `null` si l'étude n'a rien configuré.
     *
     * Union sur les variantes décidées : une assemblée qui décide une cession et un transfert de
     * siège attend les documents des deux, sans doublon.
     *
     * @return array<int, string>|null
     */
    private function documentsAttendusConfiguresPour(TypeActe $typeActe, array $variantes): ?array
    {
        if (DocumentAttendu::where('type_acte_id', $typeActe->id)->doesntExist()) {
            return null;
        }

        // Aucune variante décidée : seules les lignes non restreintes s'appliquent.
        $cibles = $variantes === [] ? [null] : $variantes;

        $slugs = [];
        foreach ($cibles as $variante) {
            $slugs = [
                ...$slugs,
                ...DocumentAttendu::pourProcedure($typeActe->id, $variante)
                    ->pluck('type_document')
                    ->all(),
            ];
        }

        return array_values(array_unique([...$slugs, ...self::TYPES_DOCUMENTS_INCONDITIONNELS]));
    }

    private function typesDocumentsRetenusPour(TypeActe $typeActe, array $variantes): ?array
    {
        // Configuration de l'étude d'abord : `documents_attendus` prime dès qu'une ligne existe pour
        // ce type d'acte. Vide, on replie sur la règle écrite — garantie que le déploiement ne
        // change rien tant que personne n'a pris la main.
        $configures = $this->documentsAttendusConfiguresPour($typeActe, $variantes);
        if ($configures !== null) {
            return $configures;
        }

        // `!== 'SOC-MOD'` codé en dur jusqu'au 2026-09-28. La référence écrite est désormais
        // portée par la variante elle-même (`documentsReference()`), ce qui distingue deux cas
        // que le code en dur confondait :
        //
        //   - le type ne se décline pas (vente, bail, hypothèque) → `null`, tous ses gabarits ;
        //   - le type se décline mais **aucune règle écrite ne dit quels documents produire**
        //     → `null` également, et c'est le cas de la dissolution. Le compte rendu de
        //     juillet 2026 n'en parle pas ; inventer une liste la ferait afficher comme
        //     faisant foi par l'écran Processus.
        $reference = $this->documentsReferencePourVariantes($typeActe, $variantes);

        if ($reference === null) {
            return null;
        }

        if ($reference === []) {
            // Le type a une référence écrite, mais aucune variante reconnue n'a été décidée :
            // ne rien produire plutôt que tout produire. L'avancement en Édition est de toute
            // façon refusé par ReglesSocieteService tant que la variante n'est pas précisée —
            // ce cas ne devrait pas se présenter, mais s'il se présente, un dossier vide est
            // plus lisible qu'un dossier rempli d'actes hors sujet.
            return self::TYPES_DOCUMENTS_INCONDITIONNELS;
        }

        return [
            ...array_keys($reference),
            ...self::TYPES_DOCUMENTS_INCONDITIONNELS,
        ];
    }

    /**
     * Documents imposés par une référence écrite, pour des variantes déjà résolues en valeurs.
     *
     * Distingue trois retours, et la distinction porte tout le sens :
     *
     *   - `null` — aucune référence écrite pour ce type d'acte (il ne se décline pas, ou ses
     *     variantes n'en déclarent aucune). L'appelant retombe sur les gabarits rattachés.
     *   - `[]`   — une référence existe, mais aucune variante reconnue n'est décidée.
     *   - une liste — l'union des exigences des variantes décidées.
     *
     * Pendant de {@see VariantesTypeActe::documentsReferencePour()}, qui part d'un dossier ;
     * celle-ci part de valeurs techniques, forme dont dispose la génération d'actes.
     *
     * @param  array<int, string> $variantes
     * @return array<string, string>|null
     */
    private function documentsReferencePourVariantes(TypeActe $typeActe, array $variantes): ?array
    {
        if (! VariantesTypeActe::existePour($typeActe->code)) {
            return null;
        }

        $resolues = array_filter(array_map(
            fn (string $v) => VariantesTypeActe::resoudre($typeActe->code, $v),
            $variantes,
        ));

        // Aucune variante décidée : on ne peut pas conclure depuis les seules variantes, donc on
        // interroge le type d'acte — déclare-t-il une référence, quelle que soit la variante ?
        if ($resolues === []) {
            foreach (VariantesTypeActe::pour($typeActe->code) as $variante) {
                if ($variante->documentsReference() !== null) {
                    return [];
                }
            }

            return null;
        }

        $documents = null;

        foreach ($resolues as $variante) {
            $reference = $variante->documentsReference();

            if ($reference === null) {
                continue;
            }

            $documents = [...($documents ?? []), ...$reference];
        }

        // Une union non vide doit rester triée comme la règle écrite l'ordonne, pas comme le
        // clerc a coché les cases — même raison que `documentsRequisPour()`.
        if ($documents !== null && $resolues !== []) {
            $modifications = array_filter(
                $resolues,
                fn ($v) => $v instanceof TypeModificationStatutaire,
            );

            if (count($modifications) === count($resolues)) {
                $documents = TypeModificationStatutaire::documentsRequisPour(array_values($modifications));
            }
        }

        return $documents;
    }

    /**
     * Types de document produits quelle que soit la modification décidée : la page de garde
     * habille le dossier, elle ne dépend d'aucune résolution.
     */
    private const TYPES_DOCUMENTS_INCONDITIONNELS = ['page_garde'];

    /**
     * Mentions propres à une société dissoute : l'état accolé à la dénomination, et la durée
     * réduite de l'article 5 des statuts.
     *
     * **Pourquoi dériver plutôt que saisir.** Les deux actes de référence de l'étude (dossier
     * L'OR D'AFRIQUE, décembre 2023) divergent sur la mention — « (EN COURS DE LIQUIDATION) »
     * au procès-verbal, « (EN LIQUIDATION) » à l'insertion, pour la même société au même
     * moment. Et le calcul de durée du procès-verbal est faux : constitution le 24/08/2021,
     * durée « réduite à deux ans quatre (04) mois » et expirant « le 04 Décembre 2023 » — or
     * 24/08/2021 + 2 ans 4 mois donne le 24/12/2023, et l'écart réel jusqu'au 04/12/2023 est de
     * 2 ans 3 mois et 10 jours. Vingt jours d'écart, dans un acte authentique.
     *
     * Une valeur calculée depuis deux dates ne peut pas se tromper de vingt jours.
     *
     * @param array<string, mixed> $donnees
     */
    private function remplirMentionsDissolution(TemplateProcessor $tp, Dossier $dossier, array $donnees): void
    {
        // Mention d'état, depuis le **statut de la fiche** et non depuis le questionnaire : elle
        // change quand la société change d'état, pas quand un clerc la retape.
        if (!isset($donnees['soc.mention_liquidation'])) {
            $dossier->loadMissing('societe');
            $tp->setValue('soc.mention_liquidation', $dossier->societe?->statut->mentionActe() ?? '');
        }

        // Durée statutaire initiale en toutes lettres : l'acte écrit « quatre-vingt-dix-neuf
        // (99) années ». `_lettres` n'est dérivé d'office que pour les dates et les suffixes
        // `_chiffres` ; `soc.duree` n'est ni l'un ni l'autre.
        // ⚠️ En **minuscules**, contrairement aux autres `_lettres` du moteur, que
        // `NombreEnLettres` rend en capitales. Ce n'est pas une incohérence : les capitales sont
        // la convention des **montants** (« LA SOMME DE CINQ MILLIONS »), ces deux mentions-ci
        // sont de la prose courante — l'acte écrit « fixée à quatre-vingt-dix-neuf (99) années »
        // et « réduite à deux ans quatre mois ». Une balise en capitales au milieu d'une phrase
        // se verrait, et le rédacteur du modèle n'a aucun moyen de la rabaisser.
        $duree = $donnees['soc.duree'] ?? null;
        if (filled($duree) && !isset($donnees['soc.duree_lettres'])) {
            $tp->setValue('soc.duree_lettres', mb_strtolower(NombreEnLettres::convertir((int) $duree, ''), 'UTF-8'));
        }

        // Durée écoulée entre la constitution et la prise d'effet — la phrase de l'article 5.
        // La date d'effet peut être différée ; à défaut, c'est celle de l'assemblée.
        $constitution = $this->dateFrancaise($donnees['soc.date_constitution'] ?? null);
        $effet        = $this->dateFrancaise($donnees['dissolution.date_effet'] ?? null)
            ?? $this->dateFrancaise($donnees['dissolution.date_assemblee'] ?? null);

        if ($constitution && $effet && $effet > $constitution && !isset($donnees['dissolution.duree_reduite'])) {
            $tp->setValue('dissolution.duree_reduite', $this->dureeEnLettres($constitution->diff($effet)));
            $tp->setValue('dissolution.date_expiration', $effet->format('d/m/Y'));
        }
    }

    /**
     * Balises `${retour.*}` — ce que les organismes ont effectivement délivré.
     *
     * **Espace de noms distinct de `soc.*`**, et la distinction porte du sens : `${soc.rccm}`
     * est ce que le **questionnaire** dit de la société, `${retour.rccm_numero}` ce que le
     * greffe a **délivré sur ce dossier**. Un acte peut légitimement citer l'un ou l'autre. Et
     * ni l'un ni l'autre n'est `${pm.rccm}`, qui est le RCCM d'une **partie** personne morale.
     *
     * ⚠️ **Ces balises sont vides dans les actes déjà produits, et c'est structurel.** Les
     * actes sont générés à l'entrée en **Édition** ; la formalité revient à l'étape
     * **Formalités**, deux étapes plus tard. Une insertion au journal produite en Édition ne
     * peut donc pas porter un numéro RCCM qui n'existait pas encore. Elles servent à deux
     * choses : un acte **régénéré** après le retour, et les dossiers suivants de la même
     * société — qui, eux, lisent `${soc.*}` depuis la fiche désormais renseignée.
     *
     * `EnregistrementRetourFormalite` signale au retour les actes à régénérer ; il ne les
     * régénère pas — `regenererDocument()` écraserait une correction faite à la main.
     *
     * La précédence habituelle s'applique : une valeur déjà présente dans `donnees` l'emporte,
     * ce qui laisse la porte ouverte à une saisie manuelle exceptionnelle.
     *
     * @param array<string, mixed> $donnees
     */
    private function remplirDonneesAuRetour(TemplateProcessor $tp, Dossier $dossier, array $donnees): void
    {
        $dossier->loadMissing('formalites');

        // La valeur la plus récemment revenue l'emporte : deux formalités peuvent rapporter la
        // même donnée (une déclaration modificative après une immatriculation), et c'est la
        // dernière qui décrit l'état courant.
        $formalites = $dossier->formalites
            ->filter(fn ($f) => filled($f->donnees_recues))
            ->sortBy(fn ($f) => $f->retour_at?->timestamp ?? 0);

        $valeurs = [];

        foreach ($formalites as $formalite) {
            foreach ($formalite->donnees_recues as $cle => $valeur) {
                if (filled($valeur)) {
                    $valeurs[$cle] = $valeur;
                }
            }
        }

        foreach (DonneeAuRetour::depuis(array_keys($valeurs)) as $donnee) {
            $cle = $donnee->baliseCle();

            if (isset($donnees[$cle])) {
                continue;
            }

            $valeur = $valeurs[$donnee->value];

            // `donnees_recues` stocke les dates en **ISO** (c'est du stockage structuré) ; un
            // acte les veut en JJ/MM/AAAA. La conversion se fait ici, à l'unique endroit où la
            // valeur sort — c'est le contrat de `resources/js/lib/dates.js`.
            if ($donnee->type() === 'date') {
                $date = $this->dateISO($valeur);

                if (! $date) {
                    continue;
                }

                $tp->setValue($cle, $date->format('d/m/Y'));
                $tp->setValue($cle . '_jma', $date->format('d/m/Y'));
                $tp->setValue($cle . '_lettres', $this->dateJourMoisEnLettres($date) . ' ' . NombreEnLettres::convertir((float) $date->year, ''));

                continue;
            }

            $tp->setValue($cle, htmlspecialchars((string) $valeur, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        }
    }

    /** Lit une date **ISO**, telle que `donnees_recues` la stocke. */
    private function dateISO(mixed $valeur): ?\Illuminate\Support\Carbon
    {
        if (blank($valeur) || !is_string($valeur)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m-d', substr(trim($valeur), 0, 10))->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Lit une date de `donnees`, qui les porte en **JJ/MM/AAAA**.
     *
     * ⚠️ `createFromFormat('d/m/Y')` explicitement, jamais `Carbon::parse` : c'est lui qui a
     * inversé sept dates au jour et au mois (décision #40).
     */
    private function dateFrancaise(mixed $valeur): ?\Illuminate\Support\Carbon
    {
        if (blank($valeur) || !is_string($valeur)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('d/m/Y', trim($valeur))->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * « deux ans, trois mois et dix jours » — les composantes nulles sont omises.
     *
     * Omettre les zéros n'est pas une coquetterie : « deux ans, zéro mois et zéro jour » dans un
     * acte authentique se remarque.
     */
    private function dureeEnLettres(\DateInterval $ecart): string
    {
        $morceaux = [];

        foreach ([['y', 'an', 'ans'], ['m', 'mois', 'mois'], ['d', 'jour', 'jours']] as [$cle, $singulier, $pluriel]) {
            $n = (int) $ecart->{$cle};

            if ($n === 0) {
                continue;
            }

            // Minuscules : voir la note de `remplirMentionsDissolution()` — cette durée s'insère
            // dans une phrase, pas dans une mention de montant.
            $morceaux[] = mb_strtolower(NombreEnLettres::convertir($n, ''), 'UTF-8') . ' ' . ($n > 1 ? $pluriel : $singulier);
        }

        if ($morceaux === []) {
            return 'zéro jour';
        }

        // « a, b et c » : la dernière composante se sépare par « et », pas par une virgule.
        $derniere = array_pop($morceaux);

        return $morceaux === [] ? $derniere : implode(', ', $morceaux) . ' et ' . $derniere;
    }

    public function genererDocument(Dossier $dossier, string $templatePath, string $outputName): string
    {
        return $this->genererDepuisGabarit($dossier, ['relatif' => $templatePath, 'herite' => false], $outputName);
    }

    /**
     * Produit un document depuis un gabarit, qu'il vienne de la bibliothèque de modèles
     * (`relatif`, disque `local`) ou d'un fichier déposé au registre (`absolu`).
     *
     * @param array{absolu?: string, relatif?: string, herite: bool} $gabarit
     */
    private function genererDepuisGabarit(Dossier $dossier, array $gabarit, string $outputName, ?string $referenceActe = null): string
    {
        // Convention unifiée avec les documents téléversés manuellement (voir DocumentFichier /
        // plan GED) — auparavant ce service écrivait dans 'dossiers/{id}/', un dossier distinct
        // jamais nettoyé et jamais relié aux enregistrements en base (fichiers orphelins).
        $outputAbsDir = storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $dossier->reference);
        if (!is_dir($outputAbsDir) && !mkdir($outputAbsDir, 0755, true) && !is_dir($outputAbsDir)) {
            throw new \RuntimeException("Impossible de créer le répertoire : {$outputAbsDir}");
        }

        $ts                 = time();
        $filename           = $outputName . '_' . $ts . '.docx';
        $outputPath         = 'documents/' . $dossier->reference . '/' . $filename;
        $outputAbsolutePath = $outputAbsDir . DIRECTORY_SEPARATOR . $filename;

        if (isset($gabarit['absolu'])) {
            $templateAbsPath = $gabarit['absolu'];
        } else {
            // Le disque 'local' (racine storage/app/private) est la source des modèles uploadés.
            // On normalise le chemin : si chemin_fichier = 'statuts.docx' → 'modeles/statuts.docx'
            //                          si chemin_fichier = 'modeles/soc/x.docx' → déjà préfixé
            $relatif         = $gabarit['relatif'] ?? '';
            $storagePath     = str_starts_with($relatif, 'modeles/') ? $relatif : 'modeles/' . $relatif;
            $templateAbsPath = Storage::disk('local')->path($storagePath);
        }

        if (!file_exists($templateAbsPath)) {
            $this->creerDocumentPlaceholder($dossier, $outputAbsolutePath, $outputName);

            return $outputPath;
        }

        try {
            $nbBalises = $this->genererDepuisModele($dossier, $templateAbsPath, $outputAbsolutePath, $outputName, $referenceActe);
        } catch (\Throwable $e) {
            // Un gabarit hérité vient de l'extérieur : fichier corrompu, .docx protégé, structure
            // OOXML inattendue. Il ne doit jamais interrompre l'entrée en Édition, qui produit
            // aussi tous les autres actes du dossier.
            JournalActivite::enregistrer(
                $dossier,
                "Document « {$outputName} » : gabarit illisible, document vierge produit à la place ({$e->getMessage()})",
                'generation',
                ['gabarit_herite' => $gabarit['herite']],
            );

            $this->creerDocumentPlaceholder($dossier, $outputAbsolutePath, $outputName);

            return $outputPath;
        }

        if ($gabarit['herite']) {
            $this->consignerGabaritHerite($dossier, $outputName, $nbBalises, $gabarit['origine'] ?? null);
        }

        return $outputPath;
    }

    /**
     * Trace qu'un acte a été produit depuis un document déposé au registre plutôt que depuis un
     * modèle de l'étude.
     *
     * Le cas sans aucune balise n'est **pas** une anomalie : les statuts d'un confrère ne portent
     * évidemment pas nos `${...}`, et le document produit est alors une copie fidèle des statuts
     * d'origine — bien supérieure à un gabarit Ayelema dont la numérotation d'articles ne
     * correspondrait à rien. Mais le rédacteur doit le savoir, sans quoi il croirait les
     * modifications déjà reportées.
     */
    private function consignerGabaritHerite(Dossier $dossier, string $outputName, int $nbBalises, ?string $origine = null): void
    {
        $source = $origine ?? 'les statuts en vigueur de la société';

        JournalActivite::enregistrer(
            $dossier,
            $nbBalises === 0
                ? "Document « {$outputName} » produit depuis {$source} — aucune balise détectée : les articles modifiés sont à reprendre à la main."
                : "Document « {$outputName} » produit depuis {$source}.",
            'generation',
            ['gabarit_herite' => true, 'balises_restantes' => $nbBalises, 'origine' => $source],
        );
    }

    // ── Génération réelle ────────────────────────────────────────────────────

    /** @return int Nombre de balises restées sans valeur — 0 pour un gabarit sans aucune balise. */
    private function genererDepuisModele(Dossier $dossier, string $templateAbsPath, string $outputAbsPath, string $outputName, ?string $referenceActe = null): int
    {
        $tp = new TemplateProcessor($templateAbsPath);

        $this->remplirConstantesOffice($tp);
        $this->remplirInfosDossier($tp, $dossier, $referenceActe);
        $this->remplirQuestionnaire($tp, $dossier);
        $nbBalises = $this->consignerChampsManquants($tp, $dossier, $outputName);
        $this->effacerMacrosResiduelles($tp);

        $tp->saveAs($outputAbsPath);

        return $nbBalises;
    }

    /**
     * Un champ du modèle sans valeur correspondante n'est PAS bloquant (le document est
     * quand même généré, avec ce passage laissé vide par `effacerMacrosResiduelles`) — mais
     * ça ne doit plus rester silencieux : on le consigne dans l'historique du dossier pour
     * que le rédacteur/notaire s'en aperçoive avant signature plutôt qu'à la relecture papier.
     */
    private function consignerChampsManquants(TemplateProcessor $tp, Dossier $dossier, string $outputName): int
    {
        $manquants = array_values(array_unique($tp->getVariables()));
        if (empty($manquants)) {
            return 0;
        }

        // La liste complète part dans `meta` (JSON, non contraint) ; le résumé texte doit lui
        // rester sous la limite de la colonne `action` (varchar 255) — un bloc répétable avec
        // plusieurs items peut facilement produire des dizaines de champs manquants.
        $resume = implode(', ', $manquants);
        if (mb_strlen($resume) > 150) {
            $resume = mb_substr($resume, 0, 150) . '…';
        }

        JournalActivite::enregistrer(
            $dossier,
            "Document « {$outputName} » généré avec " . count($manquants) . " champ(s) resté(s) vide(s) faute de donnée : {$resume}",
            'creation',
            ['document' => $outputName, 'champs_manquants' => $manquants]
        );

        return count($manquants);
    }

    private function remplirConstantesOffice(TemplateProcessor $tp): void
    {
        $tp->setValue('office.notaire',    'Maître Ayelama BAH');
        $tp->setValue('office.titre',      'Notaire');
        $tp->setValue('office.charge',     'n°21');
        $tp->setValue('office.residence',  'Ratoma');
        $tp->setValue('office.adresse',    'Nongo, 3ᵉ étage, Immeuble VISTA BANK');
        $tp->setValue('office.bp',         'BP 2668/2868');
        $tp->setValue('office.commune',    'Commune de Ratoma/Lambanyi');
        $tp->setValue('office.ville',      'Conakry');
        $tp->setValue('office.telephones', '622 49 69 44 / 664 20 96 07 / 655 61 38 38');
        $tp->setValue('office.email',      'ayelama.bah@notaire-guinee.com');
    }

    private function remplirInfosDossier(TemplateProcessor $tp, Dossier $dossier, ?string $referenceActe = null): void
    {
        $now = now();

        $tp->setValue('dossier.reference',     $dossier->reference);
        $tp->setValue('acte.numero',           $referenceActe ?? '');
        $tp->setValue('acte.reference',        $referenceActe ?? '');
        $tp->setValue('dossier.objet',         $dossier->objet ?? '');
        $tp->setValue('date_acte_jma',              $now->format('d/m/Y'));
        $tp->setValue('annee_lettres',               NombreEnLettres::convertir((float) $now->year, ''));
        $tp->setValue('date_acte_lettres',           $this->dateJourMoisEnLettres($now));
        $tp->setValue('date_acte_lettres_complete',  $this->dateJourMoisEnLettres($now) . ' ' . NombreEnLettres::convertir((float) $now->year, ''));
        // Le nombre de pages ne peut être déterminé qu'après génération : laisser vide
        $tp->setValue('acte.nb_pages',         '');
        $tp->setValue('acte.nb_pages_lettres', '');
    }

    // Jour + mois en lettres, sans l'année — utilisé dans les templates qui affichent
    // déjà l'année séparément via ${annee_lettres} (ex. « L'AN ... ; LE ... ; ») pour
    // éviter de la répéter deux fois dans le même acte.
    //
    // Le corps a été remonté dans NombreEnLettres le 2026-09-24 : MentionComparutionService
    // vise lui aussi des dates en lettres (« une procuration en date du … »), et deux
    // implémentations auraient fini par diverger sur le premier du mois.
    private function dateJourMoisEnLettres(\Illuminate\Support\Carbon $date): string
    {
        return NombreEnLettres::dateJourMois($date);
    }

    private function remplirQuestionnaire(TemplateProcessor $tp, Dossier $dossier): void
    {
        $donnees = $dossier->questionnaire?->donnees ?? [];

        $this->deriverPersonnesParDefaut($donnees, $dossier->typeActe?->code);

        foreach ($donnees as $cle => $valeur) {
            if (is_array($valeur) && array_is_list($valeur)) {
                // Liste de valeurs scalaires (choix multiple, ex. `modif.types`) : ce n'est pas
                // un bloc répétable — il n'y a pas de sous-champs à cloner, seulement une
                // énumération à écrire. Sans ce cas, la balise restait littérale dans l'acte,
                // `remplirBlocRepetable()` ignorant les items non-tableaux.
                if ($valeur !== [] && !is_array($valeur[0])) {
                    $tp->setValue($cle, htmlspecialchars(
                        implode(' · ', array_filter($valeur, 'is_scalar')),
                        ENT_XML1 | ENT_COMPAT,
                        'UTF-8',
                    ));
                    continue;
                }

                // Bloc répétable (associés, gérants, administrateurs…)
                $this->remplirBlocRepetable($tp, $cle, $valeur, $donnees);
                continue;
            }

            if (is_array($valeur)) {
                continue;
            }

            // Les dates du questionnaire sont stockées en JJ/MM/AAAA. Le dictionnaire de
            // balises annonce depuis l'origine que « toute date a la variante `_jma` », sans
            // qu'aucun code ne la produise : un modèle normalisé selon la documentation
            // laissait donc `${ag.date_jma}` littéral dans l'acte. Dérivée ici pour tous les
            // champs de date, avec la variante en toutes lettres que les actes emploient
            // (« LE VINGT-CINQ JUILLET »).
            if (is_string($valeur) && preg_match('#^\d{2}/\d{2}/\d{4}$#', $valeur)) {
                foreach (["{$cle}_jma" => $valeur] as $alias => $v) {
                    if (!isset($donnees[$alias])) {
                        $tp->setValue($alias, $v);
                    }
                }

                $cleLettres = "{$cle}_lettres";
                if (!isset($donnees[$cleLettres])) {
                    try {
                        $date = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $valeur);
                        $tp->setValue($cleLettres, $this->dateJourMoisEnLettres($date)
                            . ' ' . NombreEnLettres::convertir((float) $date->year, ''));
                    } catch (\Exception) {
                        // Date non interprétable : la balise `_lettres` reste vide plutôt que
                        // de faire échouer la génération de tout l'acte pour une saisie douteuse.
                    }
                }
            }

            if (str_ends_with($cle, '_chiffres') && is_numeric($valeur)) {
                // _lettres : sans devise (le template apporte lui-même "FRANCS GUINÉENS")
                $cleLettre = str_replace('_chiffres', '_lettres', $cle);
                if (!isset($donnees[$cleLettre])) {
                    $tp->setValue($cleLettre, NombreEnLettres::convertir((float) $valeur, ''));
                }
                // _formate : 30000000 → "30 000 000" (espace fine insécable)
                $cleFormate = str_replace('_chiffres', '_formate', $cle);
                if (!isset($donnees[$cleFormate])) {
                    $tp->setValue($cleFormate, number_format((float) $valeur, 0, ',', "\u{202F}"));
                }
            }

            $tp->setValue($cle, htmlspecialchars((string) $valeur, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        }

        // Dériver pp.adresse / ger.adresse / … à partir des champs séparés si le questionnaire
        // utilise la forme décomposée (quartier + commune + demeurant_ville + pays).
        foreach (['pp', 'ger', 'acq', 'loc', 'liquidateur'] as $pfx) {
            $q = $donnees["{$pfx}.quartier"]        ?? null;
            $c = $donnees["{$pfx}.commune"]         ?? null;
            $v = $donnees["{$pfx}.demeurant_ville"] ?? null;

            if (($q !== null || $c !== null || $v !== null) && !isset($donnees["{$pfx}.adresse"])) {
                $adresse = implode(', ', array_filter([$q, $c, $v]));
                $tp->setValue("{$pfx}.adresse", htmlspecialchars($adresse, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }
        }

        // ── Dissolution : mentions et durées que l'acte écrit mais que nul ne saisit ──
        //
        // Ajouté le 2026-09-28 après lecture de deux actes réels de l'étude. Ces trois valeurs
        // sont **dérivées** et non demandées : elles se déduisent de la fiche société et de la
        // date d'effet, et les faire saisir revenait à demander un calcul — que l'acte étudié
        // a d'ailleurs raté.
        $this->remplirMentionsDissolution($tp, $dossier, $donnees);

        // Ce que les organismes ont délivré — numéro RCCM, NIF, quittance… Voir
        // `remplirDonneesAuRetour()` pour la raison d'un espace de noms distinct.
        $this->remplirDonneesAuRetour($tp, $dossier, $donnees);

        // Terme du bail (baux habitation/commercial/construction) : les modèles écrivent
        // « … commence à courir le [date_prise_effet] pour se terminer le [date_fin] », mais
        // le questionnaire ne demande que la durée en années — la date de fin ne serait donc
        // jamais fournie. On la déduit ici plutôt que de l'ajouter comme un champ de plus à saisir.
        $dateEffet = $donnees['bail.date_prise_effet'] ?? null;
        $duree     = $donnees['bail.duree_chiffres'] ?? null;
        if ($dateEffet && $duree && !isset($donnees['bail.date_fin'])) {
            try {
                $dateFin = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $dateEffet)
                    ->addYears((int) $duree)
                    ->format('d/m/Y');
                $tp->setValue('bail.date_fin', $dateFin);
            } catch (\Exception) {
                // Date saisie dans un format inattendu — laisser le placeholder vide plutôt que planter.
            }
        }
    }

    /**
     * SARLU (`creation_sarlu`) et SASU (`creation_sasu`) proposent une case à cocher
     * « le gérant/président est une personne différente de l'associé unique », décochée
     * par défaut car c'est le cas le plus fréquent — l'associé unique se nomme lui-même
     * gérant/président. Une case à cocher jamais touchée par l'utilisateur n'existe même
     * pas dans `donnees` (React ne l'ajoute qu'au premier clic) : on ne peut donc PAS se
     * fier à sa présence pour savoir si la dérivation s'applique, seulement au code du
     * type d'acte. Tant qu'elle reste décochée (ou absente), les champs ger.xxx /
     * soc.president_xxx ne sont jamais collectés : sans cette dérivation, les paragraphes
     * du modèle qui les utilisent (ex. statuts-sarlu.docx : "Dès à présent,
     * ${ger.civilite} ${ger.prenom_nom}…") seraient générés vides.
     */
    private function deriverPersonnesParDefaut(array &$donnees, ?string $typeActeCode): void
    {
        if ($typeActeCode === 'SOC-SARLU' && empty($donnees['ger.est_different'])) {
            foreach ($donnees as $cle => $valeur) {
                if (!str_starts_with($cle, 'pp.')) {
                    continue;
                }
                $cleGer = 'ger.' . substr($cle, 3);
                if (empty($donnees[$cleGer])) {
                    $donnees[$cleGer] = $valeur;
                }
            }
        }

        if ($typeActeCode === 'SOC-SASU' && empty($donnees['soc.president_est_different'])) {
            $map = [
                'soc.president_civilite'     => 'pp.civilite',
                'soc.president_nom'          => 'pp.prenom_nom',
                'soc.president_piece_numero' => 'pp.piece_numero',
            ];
            foreach ($map as $cleDestination => $cleSource) {
                if (empty($donnees[$cleDestination]) && !empty($donnees[$cleSource])) {
                    $donnees[$cleDestination] = $donnees[$cleSource];
                }
            }
            if (empty($donnees['soc.president_adresse'])) {
                $adresse = implode(', ', array_filter([
                    $donnees['pp.quartier'] ?? null,
                    $donnees['pp.commune'] ?? null,
                    $donnees['pp.demeurant_ville'] ?? null,
                ]));
                if ($adresse !== '') {
                    $donnees['soc.president_adresse'] = $adresse;
                }
            }
        }
    }

    /**
     * Remplit un bloc répétable dans le template .docx.
     *
     * Dans le modèle Word, le bloc doit être délimité par les balises :
     *   ${associes}  …  ${/associes}
     * Et les champs internes : ${associes.nom}, ${associes.parts_chiffres}, etc.
     *
     * PhpWord clonera le bloc autant de fois qu'il y a d'items. Avec `cloneBlock(...,
     * $indexVariables: true)`, chaque variable ${associes.nom} du bloc N est renommée en
     * ${associes.nom#N} — l'index est ajouté à LA FIN du nom de variable complet, pas
     * inséré entre le nom du bloc et le champ (voir TemplateProcessor::indexClonedVariables
     * dans PhpWord, qui fait `preg_replace('/\$\{([^:]*?)(:.*?)?\}/', '${\1#N\2}', ...)`).
     * D'où la clé "{$bloc}.{$champ}#{$index}" ci-dessous, et surtout pas "{$bloc}#{$index}.{$champ}".
     */
    private function remplirBlocRepetable(TemplateProcessor $tp, string $bloc, array $items, array $allDonnees): void
    {
        if (empty($items)) {
            return;
        }

        try {
            $tp->cloneBlock($bloc, count($items), true, true);
        } catch (\Exception) {
            // Le bloc n'existe pas dans ce template — on ignore silencieusement.
            return;
        }

        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $index = $i + 1; // PhpWord utilise #1, #2, …

            // Même dérivation que remplirQuestionnaire() pour les champs préfixés :
            // reconstitue "adresse" à partir de quartier/commune/demeurant_ville si le
            // schéma de ce bloc répétable utilise la forme décomposée (voir GERANT_SCHEMA/
            // ASSOCIE_SCHEMA côté frontend) plutôt qu'un champ adresse en texte libre.
            if (!isset($item['adresse'])) {
                $adresse = implode(', ', array_filter([
                    $item['quartier'] ?? null,
                    $item['commune'] ?? null,
                    $item['demeurant_ville'] ?? null,
                ]));
                if ($adresse !== '') {
                    $item['adresse'] = $adresse;
                }
            }

            foreach ($item as $champ => $valeur) {
                // Un item de bloc répétable peut porter des valeurs non scalaires : la fiche client
                // complète sous la clé `client` (déposée par le sélecteur de client, et utilisée par
                // `buildPartiesPayload`), ou un `checkbox_group`. Les caster produisait un
                // « Array to string conversion » à chaque génération.
                if (is_array($valeur)) {
                    // Liste de libellés → énumération lisible ; objet (la fiche client) → rien à
                    // écrire, ses champs sont déjà projetés individuellement.
                    if (!array_is_list($valeur)) {
                        continue;
                    }
                    $valeur = implode(', ', array_filter($valeur, 'is_scalar'));
                }

                $valeur = (string) ($valeur ?? '');

                // Auto-conversion montants en lettres
                if (str_ends_with($champ, '_chiffres') && is_numeric($valeur)) {
                    $champLettres = str_replace('_chiffres', '_lettres', $champ);
                    if (!isset($item[$champLettres])) {
                        $tp->setValue("{$bloc}.{$champLettres}#{$index}", NombreEnLettres::convertir((float) $valeur));
                    }
                }

                $tp->setValue(
                    "{$bloc}.{$champ}#{$index}",
                    htmlspecialchars($valeur, ENT_XML1 | ENT_COMPAT, 'UTF-8')
                );
            }
        }
    }

    // Remplace toutes les balises ${...} non résolues par une chaîne vide.
    // Appelé en dernier, après tous les setValue, pour ne jamais laisser de
    // marqueur brut dans le document final.
    private function effacerMacrosResiduelles(TemplateProcessor $tp): void
    {
        foreach ($tp->getVariables() as $var) {
            try {
                $tp->setValue($var, '');
            } catch (\Exception) {
                // Variable dans un bloc répétable déjà cloné — on ignore.
            }
        }
    }

    // ── Placeholder (aucun modèle uploadé) ──────────────────────────────────

    private function creerDocumentPlaceholder(Dossier $dossier, string $outputAbsPath, string $outputName): void
    {
        $phpWord  = new PhpWord();
        $section  = $phpWord->addSection();
        $donnees  = $dossier->questionnaire?->donnees ?? [];

        $section->addText(
            'DOSSIER : ' . $dossier->reference,
            ['bold' => true, 'size' => 14]
        );
        $section->addText('Objet : ' . ($dossier->objet ?? ''));
        $section->addText('Document : ' . $outputName);
        $section->addTextBreak();
        $section->addText('⚠ Aucun modèle .docx associé — document généré automatiquement.', ['italic' => true]);
        $section->addTextBreak();

        foreach ($donnees as $cle => $valeur) {
            if (!is_array($valeur) && $valeur !== null && $valeur !== '') {
                $section->addText($cle . ' : ' . $valeur);
            }
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputAbsPath);
    }
}
