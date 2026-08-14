<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\ModeleActe;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Vocabulaire des types de document — une seule référence, servie par le serveur (2026-08-11).
 *
 * Signalé à l'usage : la colonne TYPE de l'onglet Actes affichait `statuts_maj` et
 * `declaration_rccm` en brut, là où les autres lignes montraient « DNSV ». Le vocabulaire était
 * recopié **trois fois** en JavaScript, et les trois copies avaient divergé de
 * `ModeleActe::TYPES_DOCUMENT` — les quatre types de la modification statutaire y
 * manquaient.
 */
class VocabulaireTypesDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Administrateur->value]);

        return $user->fresh();
    }

    private function typeActe(string $code = 'SOC-MOD'): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => $code],
            ['label' => 'Type ' . $code, 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
        );
    }

    private function dossier(?EtapeDossier $etape = null): Dossier
    {
        return Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $this->typeActe()->id,
            'etape'        => $etape ?? EtapeDossier::Edition,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test pour le vocabulaire des types de document',
        ]);
    }

    // ── Le modèle sait nommer son type ───────────────────────────────────────

    public function test_document_fichier_rend_le_libelle_de_son_type(): void
    {
        // `DocumentFichier` nomme `categorie` ce que les modèles d'actes nomment `type_document` —
        // héritage de l'unification GED. Le trait doit servir les deux conventions.
        foreach (ModeleActe::TYPES_DOCUMENT as $slug => $libelle) {
            $this->assertSame(
                $libelle,
                (new DocumentFichier(['categorie' => $slug]))->typeDocumentLabel(),
                "Le slug « {$slug} » doit rendre son libellé.",
            );
        }
    }

    public function test_les_quatre_types_de_modification_ont_un_libelle(): void
    {
        // Le cas signalé, nommément.
        $attendus = [
            'statuts_maj'      => 'Statuts mis à jour',
            'declaration_rccm' => 'Déclaration de modification RCCM',
            'pv_modification'  => "Procès-verbal de l'assemblée",
            'acte_cession'     => 'Acte de cession de parts',
        ];

        foreach ($attendus as $slug => $libelle) {
            $this->assertSame($libelle, (new DocumentFichier(['categorie' => $slug]))->typeDocumentLabel());
        }
    }

    public function test_un_slug_inconnu_reste_lisible(): void
    {
        // Repli sur le slug et non sur une chaîne vide : un type non répertorié doit se voir, c'est
        // ainsi qu'on a repéré le défaut.
        $this->assertSame('type_invente', (new DocumentFichier(['categorie' => 'type_invente']))->typeDocumentLabel());
    }

    public function test_les_modeles_dactes_gardent_leur_convention(): void
    {
        // Non-régression : la surcharge de `DocumentFichier` ne doit pas déplacer le champ lu par
        // `ModeleActe` et `ModeleCourrier`.
        $this->assertSame('RCCM', (new ModeleActe(['type_document' => 'rccm']))->typeDocumentLabel());
    }

    // ── Le libellé voyage avec le document ───────────────────────────────────

    public function test_la_fiche_dossier_sert_le_libelle_de_chaque_document(): void
    {
        $dossier = $this->dossier();
        $dossier->documents()->create(['nom' => 'Statuts mis à jour', 'categorie' => 'statuts_maj', 'statut' => 'a_editer']);

        $reponse = $this->actingAs($this->utilisateur())
            ->get("/dossiers/{$dossier->reference}")
            ->assertOk();

        $document = collect($reponse->viewData('page')['props']['dossier']['documents'])
            ->firstWhere('categorie', 'statuts_maj');

        $this->assertNotNull($document);
        $this->assertSame('Statuts mis à jour', $document['typeDocLabel'], 'Le slug brut ne doit plus atteindre l’écran.');
    }

    // ── Les deux régressions de l'unification GED ────────────────────────────

    public function test_lecran_de_certification_sert_le_type_et_voit_le_fichier(): void
    {
        // `RevisionController` lisait `$doc->type_document` et `$doc->chemin_fichier` — deux
        // colonnes qui n'existent pas sur `DocumentFichier` depuis le 2026-07-24. Le type était donc
        // vide et `has_file` **toujours faux** : l'aperçu et le téléchargement disparaissaient de
        // l'écran où le certificateur doit précisément lire les actes.
        Storage::fake('public');

        $dossier  = $this->dossier(EtapeDossier::Revision);
        $document = $dossier->documents()->create([
            'nom' => 'Déclaration RCCM', 'categorie' => 'declaration_rccm', 'statut' => 'edite',
        ]);
        $document->nouvelleVersion(UploadedFile::fake()->create('rccm.docx', 20), 'documents/' . $dossier->reference);

        $reponse = $this->actingAs($this->utilisateur())
            ->get("/dossiers/{$dossier->reference}/revision")
            ->assertOk();

        $vu = collect($reponse->viewData('page')['props']['documents'])->firstWhere('categorie', 'declaration_rccm');

        $this->assertNotNull($vu);
        $this->assertSame('Déclaration de modification RCCM', $vu['typeDocLabel']);
        $this->assertTrue($vu['has_file'], 'Le fichier existe : aperçu et téléchargement doivent être offerts.');
    }

    // ── Garde-fou ────────────────────────────────────────────────────────────

    public function test_les_options_exposees_couvrent_tout_le_vocabulaire(): void
    {
        // C'est cette liste que les écrans consomment. Si elle s'écarte de la référence, un type
        // ajouté redeviendrait invisible quelque part — exactement le défaut corrigé.
        $this->assertSame(
            array_keys(ModeleActe::TYPES_DOCUMENT),
            array_column(ModeleActe::typesDocumentOptions(), 'value'),
        );
    }
}
