<?php

namespace Tests\Feature;

use App\Models\Bareme;
use App\Models\ModeleActe;
use App\Models\ModeleActeRattachement;
use App\Models\TypeActe;
use App\Services\ActesGeneratorService;
use Database\Seeders\ReglesGestionDocumentsSeeder;
use Database\Seeders\ReglesGestionHonorairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ce qu'un dossier de société produit, et ce qu'il facture — par forme et par variante.
 *
 * ⚠️ **Tous ces tests échouaient le 2026-10-30.** Ils ne viennent pas d'une revue de code mais
 * d'une mesure : on a demandé à `ActesGeneratorService::actesPrevus()` ce qu'il produirait pour
 * chaque type et chaque variante, et confronté le résultat aux documents de l'étude.
 *
 *  - une modification de SA sortait un `RCCM SARLU` et, sur une augmentation, un `DNSV SARLU` ;
 *  - une **clôture de liquidation** produisait l'acte **et** l'insertion de **dissolution**,
 *    parce que deux gabarits téléversés à la main étaient rattachés à « toutes » les variantes
 *    et tous deux typés `acte_principal` ;
 *  - la grille de frais était la même sur les sept formes, alors que les notes de l'étude
 *    donnent 4 500 000 GNF en SARL et 8 500 000 en SAS.
 *
 * Chacun est écrit pour échouer si le défaut revient — c'est la règle 2 du dépôt : un garde-fou
 * exécutable plutôt qu'une convention.
 */
class NormalisationActesSocieteTest extends TestCase
{
    use RefreshDatabase;

    /** Les formes dont le nom ne doit apparaître que sur leurs propres gabarits. */
    private const FORMES = ['SARLU', 'SARL', 'SASU', 'SAS', 'SA', 'SNC', 'GIE'];

    /**
     * Le référentiel des types d'acte, et lui seul.
     *
     * ⚠️ **Les gabarits ne sont pas semés ici.** `ModeleActeSeeder` copie des .docx depuis un
     * dossier de sources qui n'est pas versionné : sous intégration continue, il produirait un
     * jeu différent de celui du poste de travail. Les tests qui portent sur les gabarits
     * sèment eux-mêmes ce dont ils ont besoin et vérifient que l'ensemble n'est pas vide —
     * un test qui passe sur un ensemble vide ne garde rien.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TypeActeSeeder::class);
    }

    public function test_aucun_gabarit_ne_sert_une_forme_qui_nest_pas_la_sienne(): void
    {
        $this->seed(\Database\Seeders\ModeleActeSeeder::class);

        $rattachements = ModeleActeRattachement::with(['modeleActe', 'typeActe'])->get();

        // Sans cette garde, le test passerait sur une base sans aucun rattachement.
        $this->assertNotEmpty($rattachements, 'Aucun rattachement : le test ne garderait rien.');

        $fautifs = [];

        foreach ($rattachements as $r) {
            $code = $r->typeActe?->code;

            if (! $code || ! str_starts_with($code, 'SOC-')) {
                continue;
            }

            $formeDuType = str_replace('SOC-', '', $code);
            $formeDuType = in_array($formeDuType, self::FORMES, true) ? $formeDuType : null;
            $nom         = mb_strtoupper($r->modeleActe?->nom ?? '');

            foreach (self::FORMES as $forme) {
                if (preg_match('/\b' . $forme . '\b/u', $nom) && $forme !== $formeDuType) {
                    $fautifs[] = "{$code} ← « {$r->modeleActe->nom} »";
                    break;
                }
            }
        }

        $this->assertSame(
            [],
            $fautifs,
            "Un gabarit nommé pour une forme est rattaché à une autre. C'est ainsi qu'une "
            . "modification de SA sortait un RCCM de SARLU : le gabarit avait été recyclé faute "
            . "d'équivalent, et rien ne le signalait. Mieux vaut l'acte manquant que le mauvais.",
        );
    }

    public function test_la_cloture_de_liquidation_ne_produit_pas_un_acte_de_dissolution(): void
    {
        $this->seed(ReglesGestionDocumentsSeeder::class);

        $socDis = TypeActe::where('code', 'SOC-DIS')->firstOrFail();
        $prevus = app(ActesGeneratorService::class)->actesPrevus($socDis, ['cloture_liquidation']);

        $noms = array_column($prevus, 'nom');

        foreach ($noms as $nom) {
            $this->assertStringNotContainsStringIgnoringCase(
                'dissolution',
                $nom,
                "La clôture de liquidation annonce « {$nom} ». La ligne 17 du tableau signé le "
                . "2026-10-28 décrit une assemblée qui statue sur les comptes, pas une dissolution "
                . '— celle-ci a eu lieu jusqu\'à trois ans plus tôt, dans un autre dossier.',
            );
        }

        // Et un seul acte principal : deux gabarits téléversés portaient le même rôle.
        $principaux = array_filter($prevus, fn ($p) => $p['type_document'] === 'acte_principal');
        $this->assertCount(1, $principaux, 'Une procédure ne produit qu\'un acte principal.');
    }

    public function test_la_dissolution_produit_ses_trois_actes_et_la_cloture_nen_publie_aucun(): void
    {
        $this->seed(ReglesGestionDocumentsSeeder::class);

        $socDis = TypeActe::where('code', 'SOC-DIS')->firstOrFail();
        $service = app(ActesGeneratorService::class);

        // Ligne 15 du tableau signé : PV, publication au JAL, dépôt au greffe → RCCM.
        $dissolution = array_column($service->actesPrevus($socDis, ['dissolution']), 'type_document');
        sort($dissolution);
        $this->assertSame(['acte_principal', 'declaration_rccm', 'insertion'], $dissolution);

        // Ligne 17 : quatre étapes, aucune publication. L'absence est une réponse.
        $cloture = array_column($service->actesPrevus($socDis, ['cloture_liquidation']), 'type_document');
        sort($cloture);
        $this->assertSame(['acte_principal', 'declaration_rccm'], $cloture);
        $this->assertNotContains('insertion', $cloture);
    }

    public function test_chaque_variante_de_modification_a_ses_documents_attendus(): void
    {
        $this->seed(ReglesGestionDocumentsSeeder::class);

        $socMod  = TypeActe::where('code', 'SOC-MOD')->firstOrFail();
        $service = app(ActesGeneratorService::class);

        // `decision_gestion` n'en avait aucun : la variante avait été ajoutée à l'enum sans
        // relancer le semis, et un dossier qui la cochait ne produisait rien du tout.
        foreach (\App\Enums\TypeModificationStatutaire::cases() as $variante) {
            $prevus = $service->actesPrevus($socMod, [$variante->value]);

            $this->assertNotEmpty(
                $prevus,
                "La variante « {$variante->label()} » ne produit aucun acte. Une case cochable "
                . 'qui ne produit rien est un dossier vide que personne ne remarque.',
            );
        }
    }

    public function test_la_grille_dhonoraires_suit_la_forme(): void
    {
        // Les trois couches de tarifs, dans l'ordre de `DatabaseSeeder` — et cet ordre est la
        // règle : le seeder d'honoraires ne crée presque rien, il **confronte** aux notes de
        // frais ce que les deux premiers ont posé. Le jouer seul ne trouverait aucune ligne.
        $this->semerLesTarifs();

        // Relevé sur les notes de frais de l'étude : la SAS coûte près du double de la SARL,
        // et ses frais APIP diffèrent. La base appliquait la même grille aux sept formes.
        $attendu = [
            'SOC-SARL'  => ['Honoraires forfaitaires' => 4_500_000, 'Frais APIP et RCCM' => 390_000],
            'SOC-SARLU' => ['Honoraires forfaitaires' => 4_500_000, 'Frais APIP et RCCM' => 390_000],
            'SOC-SAS'   => ['Honoraires forfaitaires' => 8_500_000, 'Frais APIP et RCCM' => 490_000],
            'SOC-SASU'  => ['Honoraires forfaitaires' => 8_500_000, 'Frais APIP et RCCM' => 490_000],
        ];

        foreach ($attendu as $code => $lignes) {
            $typeActe = TypeActe::where('code', $code)->first();

            if (! $typeActe) {
                continue;
            }

            foreach ($lignes as $libelle => $montant) {
                $bareme = Bareme::where('type_acte_id', $typeActe->id)->where('libelle', $libelle)->first();

                $this->assertNotNull($bareme, "{$code} : « {$libelle} » absent du barème.");
                $this->assertEquals(
                    $montant,
                    (float) $bareme->montant_fixe,
                    "{$code} — « {$libelle} » : la note de frais de l'étude porte {$montant} GNF.",
                );
                $this->assertFalse(
                    $bareme->a_confirmer,
                    "Ce montant vient d'une note de l'étude : il ne doit pas être marqué à confirmer.",
                );
            }
        }
    }

    public function test_un_tarif_sans_note_de_frais_se_signale(): void
    {
        // Les trois couches de tarifs, dans l'ordre de `DatabaseSeeder` — et cet ordre est la
        // règle : le seeder d'honoraires ne crée presque rien, il **confronte** aux notes de
        // frais ce que les deux premiers ont posé. Le jouer seul ne trouverait aucune ligne.
        $this->semerLesTarifs();

        // Le cœur du dispositif : un montant que personne n'a communiqué part quand même sur
        // une facture. Il doit au moins le dire — à l'écran **et** sur la ligne de note.
        $nonSources = Bareme::where('actif', true)->whereNull('source')->get();

        $this->assertCount(
            0,
            $nonSources,
            'Tout tarif actif porte sa provenance, ou se déclare à confirmer : ' .
            $nonSources->pluck('libelle')->implode(', '),
        );

        // Et le marqueur n'est pas décoratif : les formes sans note de frais le portent.
        $sa = TypeActe::where('code', 'SOC-SA')->first();

        if ($sa) {
            $honoraires = Bareme::where('type_acte_id', $sa->id)
                ->where('libelle', 'Honoraires forfaitaires')
                ->first();

            $this->assertTrue(
                $honoraires?->a_confirmer,
                "L'étude n'a fourni aucune note de frais pour la SA : son forfait est une reprise "
                . 'de celui de la SARL, et doit se signaler comme tel.',
            );
        }
    }

    public function test_une_note_de_frais_ne_facture_pas_ce_quelle_ne_porte_pas(): void
    {
        // Les trois couches de tarifs, dans l'ordre de `DatabaseSeeder` — et cet ordre est la
        // règle : le seeder d'honoraires ne crée presque rien, il **confronte** aux notes de
        // frais ce que les deux premiers ont posé. Le jouer seul ne trouverait aucune ligne.
        $this->semerLesTarifs();

        // La note de vente de l'étude compte six lignes, dont aucune n'est un enregistrement
        // APIP ; et la note « sans titre foncier » n'a pas de mutation à la Conservation —
        // il n'y a pas de titre à muter.
        $interdits = [
            ['VTE-IMM', 'Enregistrement APIP'],
            ['VTE-SAN', 'Enregistrement APIP'],
            ['VTE-SAN', 'Frais de mutation Conservation Foncière'],
        ];

        foreach ($interdits as [$code, $libelle]) {
            $typeActe = TypeActe::where('code', $code)->first();

            if (! $typeActe) {
                continue;
            }

            $this->assertFalse(
                Bareme::where('type_acte_id', $typeActe->id)
                    ->where('libelle', $libelle)
                    ->where('actif', true)
                    ->exists(),
                "{$code} facture « {$libelle} », que la note de frais de l'étude ne porte pas.",
            );
        }
    }

    public function test_tout_gabarit_actif_a_son_fichier(): void
    {
        $this->seed(\Database\Seeders\ModeleActeSeeder::class);

        // Un gabarit actif sans fichier produit un document vierge, et le dossier avance comme
        // si de rien n'était. Mesuré : zéro aujourd'hui, et ce test est là pour que cela dure.
        $orphelins = ModeleActe::where('est_actif', true)->get()->filter(function (ModeleActe $m) {
            if (! $m->chemin_fichier) {
                return true;
            }

            $relatif = str_starts_with($m->chemin_fichier, 'modeles/')
                ? $m->chemin_fichier
                : 'modeles/' . $m->chemin_fichier;

            return ! is_file(storage_path('app/private/' . $relatif));
        });

        $this->assertSame(
            [],
            $orphelins->pluck('nom')->all(),
            'Un gabarit actif dont le fichier manque produit un document vierge sans rien dire.',
        );
    }

    public function test_les_chemins_de_gabarit_suivent_une_seule_convention(): void
    {
        $this->seed(\Database\Seeders\ModeleActeSeeder::class);

        $horsConvention = ModeleActe::whereNotNull('chemin_fichier')
            ->where('chemin_fichier', 'not like', 'modeles/%')
            ->pluck('nom')
            ->all();

        $this->assertSame(
            [],
            $horsConvention,
            "Le générateur préfixe `modeles/` quand il manque, mais pas l'écran "
            . "d'administration, qui affichait « gabarit manquant » sur des fichiers présents.",
        );
    }

    /** Les trois couches de tarifs, dans l'ordre que `DatabaseSeeder` impose. */
    private function semerLesTarifs(): void
    {
        $this->seed(\Database\Seeders\BaremeSeeder::class);
        $this->seed(\Database\Seeders\ReglesGestionBaremeSeeder::class);
        $this->seed(ReglesGestionHonorairesSeeder::class);
    }

    // ═══ Les démarches (tableau signé du 2026-10-28) ═════════════════════

    private function dossierSociete(string $code, array $donnees): \App\Models\Dossier
    {
        $dossier = \App\Models\Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => TypeActe::where('code', $code)->firstOrFail()->id,
            'etape'        => \App\Enums\EtapeDossier::Initialisation,
            'redacteur_id' => \App\Models\User::factory()->create()->id,
            'objet'        => 'Dossier de vérification des démarches',
        ]);

        \App\Models\Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    /** @return array<int, string> Les organismes des formalités ouvertes, dédoublonnés. */
    private function organismesOuverts(\App\Models\Dossier $dossier): array
    {
        app(\App\Services\FormaliteGenerationService::class)->genererFormalites($dossier);

        return \App\Models\Formalite::where('dossier_id', $dossier->id)
            ->pluck('organisme')->unique()->sort()->values()->all();
    }

    public function test_une_modification_ouvre_ses_demarches_et_jamais_lapip(): void
    {
        $this->semerLesTarifs();
        $this->seed(\Database\Seeders\ReglesGestionFormalitesSeeder::class);

        $dossier = $this->dossierSociete('SOC-MOD', [
            'modif.types' => ['Transfert du siège social'],
        ]);

        $organismes = $this->organismesOuverts($dossier);

        // Lignes 12, 13 et 1 à 5 du tableau signé : impôts, greffe, journal d'annonces légales.
        $this->assertSame(['greffe', 'impots', 'jal'], $organismes);

        // ⚠️ Ligne 12, sans ambiguïté : « L'APIP n'intervient pas dans les modifications ».
        // Les neuf formalités SOC-MOD qui existaient en base étaient pourtant des démarches
        // APIP, héritées de la constitution par un filtre sur la catégorie.
        $this->assertNotContains('apip', $organismes);
    }

    public function test_la_dissolution_publie_au_journal_et_la_cloture_non(): void
    {
        $this->semerLesTarifs();
        $this->seed(\Database\Seeders\ReglesGestionFormalitesSeeder::class);

        // Ligne 15 : publication au JAL, puis dépôt au greffe qui délivre le RCCM.
        $dissolution = $this->dossierSociete('SOC-DIS', ['dissolution.phase' => 'Dissolution anticipée']);
        $this->assertSame(['greffe', 'impots', 'jal'], $this->organismesOuverts($dissolution));

        // Ligne 17 : quatre étapes, **aucune publication**. L'absence est une réponse — la
        // ligne 5 a justement été annotée à la main pour ajouter la publication là où elle
        // manquait, et la 17 ne l'a pas été.
        $cloture    = $this->dossierSociete('SOC-DIS', ['dissolution.phase' => 'Clôture de la liquidation']);
        $organismes = $this->organismesOuverts($cloture);

        $this->assertSame(['greffe'], $organismes);
        $this->assertNotContains('jal', $organismes);
    }

    public function test_une_demarche_de_dissolution_ne_souvre_pas_sur_une_cloture(): void
    {
        // ⚠️ Le prédicat `Bareme::estApplicableA()` ne résolvait sa condition que par
        // `TypeModificationStatutaire` : une condition `dissolution` était jugée illisible et
        // le barème écarté — sans message. Les deux phases auraient donc partagé toutes leurs
        // démarches, ou aucune.
        $this->semerLesTarifs();
        $this->seed(\Database\Seeders\ReglesGestionFormalitesSeeder::class);

        $cloture = $this->dossierSociete('SOC-DIS', ['dissolution.phase' => 'Clôture de la liquidation']);

        app(\App\Services\FormaliteGenerationService::class)->genererFormalites($cloture);

        $libelles = \App\Models\Formalite::where('dossier_id', $cloture->id)->pluck('libelle');

        $this->assertNotEmpty($libelles, 'Une clôture doit ouvrir sa démarche au greffe.');

        foreach ($libelles as $libelle) {
            $this->assertStringNotContainsStringIgnoringCase('dissolution', $libelle);
        }
    }

    public function test_le_rccm_dune_modification_ne_capte_aucune_donnee_au_retour(): void
    {
        // Le RCCM d'une modification atteste la mise à jour, il **ne change pas** le numéro de
        // la société. Semer `rccm_numero` sur cette démarche le réécrirait à chaque
        // modification — c'est le défaut que la décision 68 du devbook a corrigé.
        $this->semerLesTarifs();
        $this->seed(\Database\Seeders\ReglesGestionFormalitesSeeder::class);

        $socMod = TypeActe::where('code', 'SOC-MOD')->firstOrFail();
        $greffe = Bareme::where('type_acte_id', $socMod->id)
            ->where('libelle', 'Enregistrement au Tribunal de Commerce (RCCM)')
            ->firstOrFail();

        $this->assertTrue($greffe->genere_formalite);
        $this->assertSame([], $greffe->donnees_au_retour ?? []);
    }
}
