<?php

use App\Models\Dossier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nettoie `est_requis` là où il signifiait « obligatoire à la clôture », notion
 * supprimée avec la configuration par type d'acte.
 *
 * ⚠️ `est_requis` est une colonne SURCHARGÉE, et c'est tout l'enjeu de cette
 * migration :
 *   - sur un `document_fichiers` dont le documentable est un **Dossier** (un acte) →
 *     signifiait « obligatoire à la clôture ». Notion morte, on remet à false.
 *   - sur un `courriers` → idem.
 *   - sur un `document_fichiers` dont le documentable est une **Partie** ou une
 *     **Formalite** → signifie « pièce à fournir ». Notion BIEN VIVANTE, lue par
 *     Partie::piecesChecklist() et les onglets Formalités. NE PAS Y TOUCHER.
 *
 * Le filtre sur documentable_type est donc la partie critique, pas un détail.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_fichiers')
            ->where('documentable_type', Dossier::class)
            ->update(['est_requis' => false]);

        DB::table('courriers')->update(['est_requis' => false]);
    }

    public function down(): void
    {
        // Irréversible : la valeur d'origine venait de `obligatoire_cloture`, colonne
        // supprimée par la migration précédente. Rien à restaurer.
    }
};
