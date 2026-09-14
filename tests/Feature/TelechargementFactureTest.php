<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\LigneFacture;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Téléchargement de la note de frais (2026-09-10).
 *
 * ⚠️ **Deux défauts distincts, signalés d'un seul symptôme.** L'étude recevait un `.htm`
 * inexploitable en cliquant « Télécharger ».
 *
 * 1. **La route ne rendait pas un PDF.** Elle s'appelait pourtant `telechargerPdf`, mais produisait
 *    un `.docx` : le gabarit Word rendu dans un fichier temporaire, expédié avec
 *    `deleteFileAfterSend`. Le nom de la méthode mentait sur ce qu'elle faisait.
 * 2. **Un échec s'enregistrait en silence.** Le bouton était un `<a href download>` nu : quand la
 *    réponse était une page HTML — erreur serveur, redirection de session — le navigateur
 *    l'enregistrait quand même, sous le nom du dernier segment de l'URL. D'où « telecharger.htm ».
 *
 * Le PDF est désormais rendu en mémoire par `FacturePdfService` — même procédé que les reçus, déjà
 * en production — et sa mise en page suit `Facture_MAB_SARLU_v2 (1).docx`, l'exemplaire fourni par
 * l'étude. Le téléchargement Word a été retiré : un seul format, celui attendu.
 */
class TelechargementFactureTest extends TestCase
{
    use RefreshDatabase;

    private function notaire(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Notaire->value]);

        return $user->fresh();
    }

    private function facture(User $notaire): Facture
    {
        $typeActe = TypeActe::create([
            'code'      => 'TST-' . fake()->unique()->numberBetween(1000, 9999),
            'label'     => 'Type de test',
            'categorie' => 'societe',
        ]);

        $dossier = Dossier::create([
            'reference'    => 'TST-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'etape'        => EtapeDossier::Expedition,
            'redacteur_id' => $notaire->id,
            'notaire_id'   => $notaire->id,
            'objet'        => 'Téléchargement de la note de frais',
        ]);

        $facture = Facture::create([
            'dossier_id'     => $dossier->id,
            'note_numero'    => '001/MAB/26',
            'note_date'      => now(),
            'objet'          => 'Modification de capital',
            'total_chiffres' => 4570000,
        ]);

        LigneFacture::create([
            'facture_id'  => $facture->id,
            'designation' => 'Honoraires forfaitaires',
            'quantite'    => 1,
            'montant'     => 4500000,
        ]);

        return $facture->fresh(['lignes', 'dossier']);
    }

    public function test_le_telechargement_rend_un_vrai_pdf(): void
    {
        $notaire = $this->notaire();
        $facture = $this->facture($notaire);

        $reponse = $this->actingAs($notaire)->get("/factures/{$facture->id}/telecharger")->assertOk();

        $this->assertSame('application/pdf', $reponse->headers->get('Content-Type'));

        // La signature du format, pas seulement l'en-tête déclaré : un serveur peut annoncer un
        // type et renvoyer autre chose — c'est exactement ce qui se passait.
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    public function test_la_reponse_nest_jamais_une_page_html(): void
    {
        // C'est **ici** que se jouait le symptôme : une réponse HTML enregistrée par l'attribut
        // `download` du lien devient un `.htm` sur le poste de l'utilisateur.
        $notaire = $this->notaire();
        $facture = $this->facture($notaire);

        $type = $this->actingAs($notaire)
            ->get("/factures/{$facture->id}/telecharger")
            ->headers->get('Content-Type');

        $this->assertStringNotContainsString('text/html', (string) $type);
    }

    public function test_le_nom_de_fichier_ne_contient_pas_de_barre_oblique(): void
    {
        // `note_numero` vaut « 001/MAB/26 » : les « / » sont invalides dans un nom de fichier, et
        // sur certains systèmes ils tronquent le nom au dernier segment.
        $notaire = $this->notaire();
        $facture = $this->facture($notaire);

        $disposition = (string) $this->actingAs($notaire)
            ->get("/factures/{$facture->id}/telecharger")
            ->headers->get('Content-Disposition');

        $this->assertStringContainsString('note-de-frais-001-MAB-26.pdf', $disposition);
        $this->assertStringNotContainsString('001/MAB/26', $disposition);
    }

    public function test_le_pdf_porte_les_mentions_du_gabarit_de_letude(): void
    {
        // La mise en page suit le gabarit Word, relevé paragraphe par paragraphe. On vérifie la
        // **vue**, le texte d'un PDF compressé n'étant pas lisible en clair.
        $notaire = $this->notaire();
        $facture = $this->facture($notaire);

        $vue = view('factures.pdf', [
            'facture'        => $facture,
            'detail'         => '(Honoraires forfaitaires)',
            'totalEnLettres' => 'Quatre Millions Cinq Cent Mille Francs Guinéens',
        ])->render();

        foreach ([
            'FACTURE',
            'N° 001/MAB/26',
            'Désignation des prestations',
            'Timbres fiscaux',
            'Rôles',
            'TOTAL',
            'Arrêté en toutes lettres',
            'LE NOTAIRE',
            'Honoraires forfaitaires',
            // Reprises du document de l'étude : le papier à lettre et la qualité du notaire.
            'Cabinet Notarial de Conakry',
            'Notaire à Conakry, Guinée',
        ] as $mention) {
            $this->assertStringContainsString($mention, $vue, "Le gabarit attend « {$mention} ».");
        }
    }

    public function test_le_montant_dune_ligne_est_multiplie_par_sa_quantite(): void
    {
        // Le tableau affiche un total de ligne, pas le prix unitaire : une quantité de 3 à
        // 100 000 GNF doit se lire 300 000.
        $notaire = $this->notaire();
        $facture = $this->facture($notaire);

        \App\Models\LigneFacture::create([
            'facture_id'  => $facture->id,
            'designation' => 'Copie authentique',
            'quantite'    => 3,
            'montant'     => 100000,
        ]);

        $vue = view('factures.pdf', [
            'facture'        => $facture->fresh(['lignes', 'dossier']),
            'detail'         => '',
            'totalEnLettres' => '',
        ])->render();

        $this->assertStringContainsString('300 000', $vue);
    }

    public function test_un_role_sans_acces_au_dossier_ne_telecharge_pas(): void
    {
        // La note de frais porte l'identité du client et les montants : elle suit l'autorisation du
        // dossier, comme tout le reste.
        $facture = $this->facture($this->notaire());

        $intrus = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $intrus->id, 'role' => RoleUtilisateur::Clerc->value]);

        $this->actingAs($intrus->fresh())
            ->get("/factures/{$facture->id}/telecharger")
            ->assertForbidden();
    }

    public function test_le_bouton_verifie_ce_quil_recoit(): void
    {
        // Garde-fou de structure : un `<a href download>` nu enregistre **n'importe quelle**
        // réponse, page d'erreur comprise. C'est ce qui rendait le défaut muet côté utilisateur.
        $source = file_get_contents(resource_path('js/Pages/Dossiers/Show.jsx'));

        $this->assertStringContainsString('telechargerFichier', $source);
        $this->assertSame(
            0,
            preg_match('#<a href=\{`/factures/\$\{facture\.id\}/telecharger`\} download#', $source),
            'Le téléchargement de la facture ne doit plus passer par un lien nu.',
        );

        $helper = file_get_contents(resource_path('js/lib/telechargement.js'));

        $this->assertStringContainsString('text/html', $helper, 'Le helper doit détecter une page web.');
        $this->assertStringContainsString('same-origin', $helper, 'Le cookie de session doit accompagner la requête.');
    }
}
