<?php

namespace App\Services;

use App\Enums\RubriqueCloture;
use App\Models\ClotureVerification;
use App\Models\Courrier;
use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\Recu;
use Illuminate\Support\Collection;

/**
 * Inventaire complet des pièces d'un dossier, rangé par rubrique.
 *
 * Remplace la clôture « configurée par type d'acte » (`obligatoire_cloture`) : il n'y a
 * plus rien à déclarer à l'avance, l'inventaire se DÉDUIT de ce que le workflow a
 * produit. Chaque étape dépose ses pièces, ce service les rassemble.
 *
 * Un service est nécessaire parce qu'aucun modèle ne peut produire cet inventaire seul :
 * il traverse trois modèles de stockage — `DocumentFichier` (polymorphe Dossier |
 * Partie | Formalite), `Courrier` et `Recu`, ces deux dernières familles ayant leur
 * propre table et leur propre `chemin_fichier`, héritage antérieur au module GED unifié.
 *
 * C'est aussi la source unique de l'onglet Clôture ET de la GED, pour que les deux
 * écrans ne puissent pas diverger sur le rangement.
 */
class InventaireClotureService
{
    /**
     * Inventaire d'un dossier : une entrée par rubrique non vide, dans l'ordre de
     * classement, chaque pièce normalisée à une forme unique quelle que soit sa table
     * d'origine.
     *
     * @return Collection<int, array{rubrique: string, label: string, description: string, ordre: int, pieces: array}>
     */
    public function pour(Dossier $dossier): Collection
    {
        $verifications = $this->verificationsIndexees($dossier);

        $pieces = $this->pieces($dossier)
            ->map(fn (array $piece) => [
                ...$piece,
                ...$this->etatVerification($verifications, $piece['type'], $piece['id']),
            ]);

        return collect(RubriqueCloture::ordonnees())
            ->map(fn (RubriqueCloture $rubrique) => [
                'rubrique'    => $rubrique->value,
                'label'       => $rubrique->label(),
                'description' => $rubrique->description(),
                'ordre'       => $rubrique->ordre(),
                'pieces'      => $pieces->where('rubrique', $rubrique->value)->values()->all(),
            ])
            ->values();
    }

    /**
     * Pièces restant à vérifier, tous rubriques confondues — ce qui bloque le passage à
     * Clôturé (voir DossierStepService::verifierExpedition).
     *
     * @return Collection<int, array>
     */
    public function piecesNonVerifiees(Dossier $dossier): Collection
    {
        $verifications = $this->verificationsIndexees($dossier);

        return $this->pieces($dossier)
            ->reject(fn (array $p) => isset($verifications[$this->cle($p['type'], $p['id'])]))
            ->values();
    }

    public function estCompletementVerifie(Dossier $dossier): bool
    {
        return $this->piecesNonVerifiees($dossier)->isEmpty();
    }

    /**
     * Compteurs pour le bandeau de progression de l'onglet Clôture.
     *
     * @return array{total: int, verifiees: int}
     */
    public function progression(Dossier $dossier): array
    {
        $total = $this->pieces($dossier)->count();

        return [
            'total'     => $total,
            'verifiees' => $total - $this->piecesNonVerifiees($dossier)->count(),
        ];
    }

    /**
     * Toutes les pièces du dossier, à plat, sans état de vérification.
     *
     * Une seule pièce peut manquer de fichier (un acte requis pas encore généré, une
     * pièce de partie pas encore fournie) : elle figure quand même à l'inventaire, avec
     * `has_file = false`. Masquer les pièces sans fichier reviendrait à cacher
     * précisément ce qui manque.
     *
     * @return Collection<int, array>
     */
    private function pieces(Dossier $dossier): Collection
    {
        $dossier->loadMissing([
            'documents.versionActuelle',
            'documents.signeCachetePar',
            'parties.pieces.versionActuelle',
            'formalites.pieces.versionActuelle',
            // Dossier constitutif d'une société que l'étude n'a pas constituée : la seule famille
            // de pièces qui n'appartienne pas au dossier mais au registre. Elle figure donc à
            // l'inventaire de chacun des dossiers de cette société, et s'y vérifie
            // indépendamment — d'où l'unicité par dossier des `cloture_verifications`.
            'societe.piecesConstitutives.versionActuelle',
            'courriers',
            'factures.paiements.recu',
        ]);

        $documents = collect($dossier->documents)
            ->merge($dossier->parties->flatMap->pieces)
            ->merge($dossier->formalites->flatMap->pieces)
            ->merge($dossier->societe?->piecesConstitutives ?? collect())
            ->map(fn (DocumentFichier $doc) => $this->depuisDocument($doc));

        $courriers = collect($dossier->courriers)
            ->map(fn (Courrier $courrier) => $this->depuisCourrier($courrier));

        $recus = collect($dossier->factures)
            ->flatMap->paiements
            ->map->recu
            ->filter()
            ->map(fn (Recu $recu) => $this->depuisRecu($recu));

        return $documents->merge($courriers)->merge($recus)->values();
    }

    private function depuisDocument(DocumentFichier $doc): array
    {
        $rubrique  = RubriqueCloture::pourDocument($doc);
        $aUnFichier = (bool) $doc->versionActuelle;

        return [
            'type'              => 'document',
            'id'                => $doc->id,
            'nom'               => $doc->nom,
            'rubrique'          => $rubrique->value,
            // Contexte d'origine : sans lui, une liste de « CNI » sur un dossier à
            // quatre associés est illisible.
            'origine'           => $this->origine($doc),
            'categorie'         => $doc->categorie,
            'has_file'          => $aUnFichier,
            'version'           => $doc->versionActuelle?->numero,
            'chemin_fichier'    => $doc->versionActuelle?->chemin_fichier,
            'url_preview'       => $aUnFichier ? route('documents.preview', $doc) : null,
            'url_download'      => $aUnFichier ? route('documents.download', $doc) : null,
            'est_signe_cachete' => (bool) $doc->est_signe_cachete,
            'signe_cachete_at'  => $doc->signe_cachete_at?->format('d/m/Y H:i'),
        ];
    }

    private function depuisCourrier(Courrier $courrier): array
    {
        $aUnFichier = (bool) $courrier->chemin_fichier;

        return [
            'type'              => 'courrier',
            'id'                => $courrier->id,
            'nom'               => $courrier->objet ?: $courrier->reference,
            'rubrique'          => RubriqueCloture::pourModele($courrier)->value,
            'origine'           => $courrier->destinataire ? "Destinataire : {$courrier->destinataire}" : $courrier->reference,
            'categorie'         => $courrier->type,
            'has_file'          => $aUnFichier,
            'version'           => null,
            'chemin_fichier'    => $courrier->chemin_fichier,
            'url_preview'       => $aUnFichier ? route('courriers.preview', $courrier) : null,
            'url_download'      => $aUnFichier ? route('courriers.download', $courrier) : null,
            'est_signe_cachete' => (bool) $courrier->est_signe_cachete,
            'signe_cachete_at'  => $courrier->signe_cachete_at?->format('d/m/Y H:i'),
        ];
    }

    private function depuisRecu(Recu $recu): array
    {
        $aUnFichier = (bool) $recu->chemin_fichier;

        return [
            'type'              => 'recu',
            'id'                => $recu->id,
            'nom'               => "Reçu n° {$recu->numero}",
            'rubrique'          => RubriqueCloture::pourModele($recu)->value,
            'origine'           => $recu->date_emission ? 'Émis le ' . $recu->date_emission->format('d/m/Y') : null,
            'categorie'         => 'recu',
            'has_file'          => $aUnFichier,
            'version'           => null,
            'chemin_fichier'    => $recu->chemin_fichier,
            'url_preview'       => $aUnFichier ? route('recus.apercu', $recu) : null,
            'url_download'      => $aUnFichier ? route('recus.telecharger', $recu) : null,
            // Un reçu est émis par l'office, il n'a pas de circuit de signature externe.
            'est_signe_cachete' => false,
            'signe_cachete_at'  => null,
        ];
    }

    /** Provenance lisible d'un document, selon son rattachement. */
    private function origine(DocumentFichier $doc): ?string
    {
        $documentable = $doc->documentable;

        return match (true) {
            $documentable instanceof \App\Models\Partie    => $documentable->nom,
            $documentable instanceof \App\Models\Formalite => $documentable->labelAffiche(),
            // Sans cette mention, une ligne « Statuts en vigueur » serait indistinguable d'un acte
            // produit par le dossier — alors qu'elle vient du registre et sert à d'autres dossiers.
            $documentable instanceof \App\Models\Societe   => 'Registre — ' . $documentable->denomination,
            default                                        => null,
        };
    }

    /**
     * Vérifications du dossier, indexées par « type:id » pour éviter une requête par
     * pièce sur un dossier qui peut en compter plusieurs dizaines.
     *
     * @return array<string, ClotureVerification>
     */
    private function verificationsIndexees(Dossier $dossier): array
    {
        return ClotureVerification::with('verifiePar:id,name')
            ->where('dossier_id', $dossier->id)
            ->get()
            ->keyBy(fn (ClotureVerification $v) => $this->cle(
                self::typeCourtDepuisClasse($v->verifiable_type),
                $v->verifiable_id,
            ))
            ->all();
    }

    /** @param array<string, ClotureVerification> $verifications */
    private function etatVerification(array $verifications, string $type, int $id): array
    {
        $verification = $verifications[$this->cle($type, $id)] ?? null;

        return [
            'verifie_at'   => $verification?->verifie_at?->format('d/m/Y H:i'),
            'verifie_par'  => $verification?->verifiePar?->name,
        ];
    }

    private function cle(string $type, int|string $id): string
    {
        return "{$type}:{$id}";
    }

    /**
     * Correspondance entre le type court exposé au frontend et la classe Eloquent.
     * Centralisée ici : le contrôleur s'en sert pour valider ce qu'il reçoit, plutôt que
     * d'accepter un nom de classe arbitraire venant du client.
     *
     * @return array<string, class-string>
     */
    public static function classesParType(): array
    {
        return [
            'document' => DocumentFichier::class,
            'courrier' => Courrier::class,
            'recu'     => Recu::class,
        ];
    }

    public static function classePourType(string $type): ?string
    {
        return self::classesParType()[$type] ?? null;
    }

    private static function typeCourtDepuisClasse(string $classe): string
    {
        return array_search($classe, self::classesParType(), true) ?: $classe;
    }
}
