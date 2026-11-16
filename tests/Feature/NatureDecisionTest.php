<?php

namespace Tests\Feature;

use App\Enums\TypeAssemblee;
use App\Enums\TypeModificationStatutaire;
use App\Support\Heure;
use Tests\TestCase;

/**
 * Assemblée d'un PV de modification — retours R02 (heure), R05 (nature de la décision), 2026-11-16.
 */
class NatureDecisionTest extends TestCase
{
    // ── R05 : nature de la décision ─────────────────────────────────────────

    public function test_une_forme_unipersonnelle_donne_la_decision_de_lassocie_unique(): void
    {
        // Arbitré par l'étude : même avec une opération qui exigerait une AGE.
        foreach (['SARLU', 'SASU'] as $forme) {
            $this->assertSame(
                TypeAssemblee::LIBELLE_ASSOCIE_UNIQUE,
                TypeAssemblee::natureDeduite($forme, [TypeModificationStatutaire::CapitalAugmentation->label()]),
            );
        }
    }

    public function test_sinon_loperation_la_plus_exigeante_tranche(): void
    {
        $this->assertSame(
            TypeAssemblee::Age->label(),
            TypeAssemblee::natureDeduite('SARL', [
                TypeModificationStatutaire::DecisionGestion->label(),
                TypeModificationStatutaire::SiegeSocial->label(),
            ]),
        );
        $this->assertSame(
            TypeAssemblee::Ago->label(),
            TypeAssemblee::natureDeduite('SARL', [TypeModificationStatutaire::GerantNonStatutaire->label()]),
        );
        // Rien de coché, forme pluripersonnelle : rien à proposer plutôt qu'une AGO inventée.
        $this->assertNull(TypeAssemblee::natureDeduite('SARL', []));
    }

    public function test_les_options_du_champ_sont_exactement_les_libelles_servis(): void
    {
        // Le préremplissage écrit un **libellé** dans un select : une option renommée d'un seul
        // côté donnerait une valeur que le select ne sait pas afficher.
        $source = file_get_contents(resource_path('js/data/questionnaires.js'));
        $this->assertSame(1, preg_match("/id: 'ag\.type'.*?options: \[(.*?)\]/s", $source, $m));
        preg_match_all("/(['\"])(.*?)\\1/", $m[1], $options);

        $servis = array_values(TypeAssemblee::reglesNatureDecision()['libelles']);
        $js = array_map(fn ($o) => str_replace("\\'", "'", $o), $options[2]);
        sort($servis);
        sort($js);

        $this->assertSame($servis, $js);
    }

    public function test_le_quorum_se_masque_sur_le_libelle_servi_de_lassocie_unique(): void
    {
        // `ag.quorum_atteint` est masqué pour une décision de l'associé unique en comparant un
        // libellé écrit dans questionnaires.js : renommé côté serveur seulement, le quorum
        // réapparaîtrait en silence sur toutes les SARLU.
        $source = file_get_contents(resource_path('js/data/questionnaires.js'));
        $this->assertSame(1, preg_match(
            "/id: 'ag\.quorum_atteint'.*?showIf: \{ field: 'ag\.type', equals: \"(.*?)\", not: true \}/s",
            $source,
            $m,
        ), 'Le quorum doit être masqué pour une décision de l\'associé unique.');

        $this->assertSame(TypeAssemblee::LIBELLE_ASSOCIE_UNIQUE, $m[1]);
    }

    public function test_le_resolveur_js_ne_porte_aucun_libelle_en_dur(): void
    {
        $code = file_get_contents(resource_path('js/lib/natureDecision.js'));
        $code = preg_replace('#//.*$#m', '', $code);

        foreach (TypeAssemblee::reglesNatureDecision()['libelles'] as $libelle) {
            $this->assertStringNotContainsString($libelle, $code, 'Le libellé doit venir du serveur.');
        }
        foreach (TypeAssemblee::reglesNatureDecision()['formesAssocieUnique'] as $forme) {
            $this->assertStringNotContainsString("'{$forme}'", $code, 'Les formes doivent venir du serveur.');
        }
    }

    // ── R02 : l'heure ────────────────────────────────────────────────────────

    public function test_les_graphies_mesurees_se_lisent_toutes(): void
    {
        // Les six graphies relevées dans les PV le jour du passage au type `time`.
        $this->assertSame('10:00', Heure::normaliser('10H'));
        $this->assertSame('10:00', Heure::normaliser('10h00'));
        $this->assertSame('10:30', Heure::normaliser('10H30'));
        $this->assertSame('10:30', Heure::normaliser('10h30'));
        $this->assertSame('10:00', Heure::normaliser('10H00'));
        $this->assertSame('09:05', Heure::normaliser('9:05'));
        $this->assertSame('10:30', Heure::normaliser('10 heures 30'));
        $this->assertNull(Heure::normaliser('25h'));
        $this->assertNull(Heure::normaliser('le matin'));
    }

    public function test_lheure_en_lettres_est_au_feminin(): void
    {
        $this->assertSame('DIX HEURES TRENTE', Heure::enLettres('10:30'));
        $this->assertSame('DIX HEURES', Heure::enLettres('10H'));
        $this->assertSame('UNE HEURE', Heure::enLettres('01:00'));
        $this->assertSame('VINGT ET UNE HEURES UNE', Heure::enLettres('21:01'));
        $this->assertSame('10 h 30', Heure::formatee('10h30'));
        $this->assertSame('10 h', Heure::formatee('10:00'));
    }
}
