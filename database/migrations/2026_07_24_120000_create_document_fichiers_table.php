<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifie les 3 systèmes de documents jusqu'ici séparés (documents, formalite_pieces,
 * et les colonnes Partie.photo_chemin/pieces jamais câblées) dans un modèle polymorphe
 * unique avec un vrai historique de versions (document_versions) — voir le plan GED.
 * Les tables `documents`/`formalite_pieces` ne sont PAS supprimées ici : elles deviennent
 * mortes (plus lues par le code après la bascule) mais restent en base comme filet de
 * sécurité jusqu'à la migration de nettoyage finale, une fois la bascule vérifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_fichiers', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->string('nom');
            $table->string('categorie', 30);
            $table->string('statut', 20)->nullable();
            $table->boolean('est_requis')->default(false);
            $table->boolean('est_fourni')->default(false);
            // FK ajoutée après coup : document_versions n'existe pas encore à ce stade
            // (référence circulaire document_fichiers <-> document_versions).
            $table->unsignedBigInteger('version_actuelle_id')->nullable();
            $table->foreignId('edite_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edite_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_fichier_id')->constrained('document_fichiers')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('chemin_fichier')->nullable();
            $table->string('nom_original')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('taille_octets')->nullable();
            $table->string('source', 20)->default('upload'); // upload | genere
            $table->foreignId('cree_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('document_fichiers', function (Blueprint $table) {
            $table->foreign('version_actuelle_id')->references('id')->on('document_versions')->nullOnDelete();
        });

        $this->backfillDepuisDocuments();
        $this->backfillDepuisFormalitePieces();
    }

    private function backfillDepuisDocuments(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        foreach (DB::table('documents')->orderBy('id')->cursor() as $doc) {
            $fichierId = DB::table('document_fichiers')->insertGetId([
                'documentable_type' => 'App\\Models\\Dossier',
                'documentable_id'   => $doc->dossier_id,
                'nom'               => $doc->nom,
                'categorie'         => $doc->type_document,
                'statut'            => $doc->statut,
                'est_requis'        => false,
                'est_fourni'        => !empty($doc->chemin_fichier),
                'edite_par_id'      => $doc->edite_par,
                'edite_at'          => $doc->edite_at,
                'created_at'        => $doc->created_at,
                'updated_at'        => $doc->updated_at,
            ]);

            if (!empty($doc->chemin_fichier)) {
                $versionId = DB::table('document_versions')->insertGetId([
                    'document_fichier_id' => $fichierId,
                    'numero'              => 1,
                    'chemin_fichier'      => $doc->chemin_fichier,
                    'source'              => 'genere',
                    'cree_par_id'         => $doc->edite_par,
                    'created_at'          => $doc->edite_at ?? $doc->created_at,
                    'updated_at'          => $doc->edite_at ?? $doc->updated_at,
                ]);

                DB::table('document_fichiers')->where('id', $fichierId)->update(['version_actuelle_id' => $versionId]);
            }
        }
    }

    private function backfillDepuisFormalitePieces(): void
    {
        if (!Schema::hasTable('formalite_pieces')) {
            return;
        }

        foreach (DB::table('formalite_pieces')->orderBy('id')->cursor() as $piece) {
            $fichierId = DB::table('document_fichiers')->insertGetId([
                'documentable_type' => 'App\\Models\\Formalite',
                'documentable_id'   => $piece->formalite_id,
                'nom'               => $piece->label,
                'categorie'         => 'piece_justificative',
                'statut'            => null,
                'est_requis'        => true,
                'est_fourni'        => (bool) $piece->est_fourni,
                'edite_par_id'      => $piece->televerse_par_id,
                'edite_at'          => $piece->televerse_at,
                'created_at'        => $piece->created_at,
                'updated_at'        => $piece->updated_at,
            ]);

            if (!empty($piece->chemin_fichier)) {
                $versionId = DB::table('document_versions')->insertGetId([
                    'document_fichier_id' => $fichierId,
                    'numero'              => 1,
                    'chemin_fichier'      => $piece->chemin_fichier,
                    'nom_original'        => $piece->nom_original,
                    'mime_type'           => $piece->mime_type,
                    'taille_octets'       => $piece->taille_octets,
                    'source'              => 'upload',
                    'cree_par_id'         => $piece->televerse_par_id,
                    'created_at'          => $piece->televerse_at ?? $piece->created_at,
                    'updated_at'          => $piece->televerse_at ?? $piece->updated_at,
                ]);

                DB::table('document_fichiers')->where('id', $fichierId)->update(['version_actuelle_id' => $versionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('document_fichiers', function (Blueprint $table) {
            $table->dropForeign(['version_actuelle_id']);
        });

        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('document_fichiers');
    }
};
