<?php

namespace App\Models;

use App\Concerns\HasTypeDocumentLabel;
use App\Enums\TypeModificationStatutaire;
use App\Support\VariantesTypeActe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Document qu'une procédure exige — configuration effective de l'étude.
 *
 * **L'enum reste la référence.** `TypeModificationStatutaire::documentsRequis()` porte la règle du CR
 * de juillet 2026, datée et traçable ; cette table en est la configuration appliquée, seedée depuis
 * lui. Tant qu'aucune ligne n'existe pour une procédure, les services **replient sur l'enum** : le
 * déploiement ne change donc rien, et l'étude n'a la main que là où elle l'a explicitement prise.
 *
 * ⚠️ Modifier ces lignes change le contenu d'actes authentiques. Deux garde-fous en conséquence :
 * `divergeDeLaReference()` signale tout écart au seed dans la vue Processus, et
 * `ReglesGestionDocumentsSeeder` permet de revenir à la référence à tout moment.
 */
class DocumentAttendu extends Model
{
    use HasTypeDocumentLabel;

    protected $table = 'documents_attendus';

    protected $fillable = ['type_acte_id', 'variante', 'type_document', 'obligatoire', 'ordre'];

    protected function casts(): array
    {
        return ['obligatoire' => 'boolean', 'ordre' => 'integer'];
    }

    public function typeActe()
    {
        return $this->belongsTo(TypeActe::class);
    }

    public function scopePourProcedure(Builder $query, int $typeActeId, ?string $variante): Builder
    {
        return $query
            ->where('type_acte_id', $typeActeId)
            // Une ligne sans variante vaut pour toute la procédure : c'est ainsi qu'on déclare un
            // document exigé quelle que soit la résolution (la page de garde, par exemple).
            ->where(fn (Builder $q) => $q->whereNull('variante')->orWhere('variante', $variante))
            ->orderBy('ordre');
    }

    public function varianteLabel(): string
    {
        if ($this->variante === null) {
            return 'Toutes les variantes';
        }

        return VariantesTypeActe::resoudre($this->typeActe?->code, $this->variante)?->label()
            ?? $this->variante;
    }

    /**
     * Documents exigés par la **référence légale** pour une procédure — le seed.
     *
     * Isolé ici pour que la vue Processus puisse comparer la configuration effective à la référence
     * et signaler l'écart. Retourne `null` quand le type d'acte n'est pas régi par une règle écrite
     * (créations, ventes, baux : leurs actes sont ceux de leurs gabarits actifs, sans liste imposée).
     *
     * @return array<string, string>|null slug ⇒ libellé
     */
    public static function reference(?string $codeTypeActe, ?string $variante): ?array
    {
        if ($codeTypeActe !== 'SOC-MOD') {
            return null;
        }

        $cas = VariantesTypeActe::resoudre($codeTypeActe, $variante);

        return $cas instanceof TypeModificationStatutaire ? $cas->documentsRequis() : null;
    }

    /**
     * La configuration effective d'une procédure s'écarte-t-elle de la référence légale ?
     *
     * @param  array<int, string> $slugsConfigures
     */
    public static function divergeDeLaReference(?string $codeTypeActe, ?string $variante, array $slugsConfigures): bool
    {
        $reference = self::reference($codeTypeActe, $variante);

        if ($reference === null) {
            return false;
        }

        $attendus = array_keys($reference);
        sort($attendus);
        sort($slugsConfigures);

        return $attendus !== $slugsConfigures;
    }
}
