<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La comptabilité **des dossiers** (gestion financière notariale) — et elle seulement : l'étude a
 * précisé le 2026-11-23 qu'elle ne demande pas de comptabilité générale.
 *
 * Ce qu'un dossier doit pouvoir dire : ce que le client doit, ce qu'il a versé, ce que l'étude a
 * reversé aux organismes, et ce qu'elle détient ou avance pour lui. Trois manques le rendaient
 * impossible :
 *
 *  1. **honoraire ou débours** ne se lisait que sur `baremes.organisme = 'Notaire'`, et pas du tout
 *     sur les lignes saisies à la main. → `nature` sur le barème **et** sur la ligne (copiée à la
 *     génération, comme `remise_autorisee`). Mesuré : 18 barèmes « Notaire », tous les autres des
 *     débours ; les 26 lignes manuelles sont toutes des débours (JAL ×14, RCCM ×7, DNSV ×4,
 *     « Debours » ×1).
 *  2. le **versement à un organisme** n'était qu'un nombre sur la formalité (`montant_paye`), sans
 *     date de paiement, moyen ni trace séparée — et impossible hors formalité. → table
 *     `decaissements`. Les 41 montants existants (11 100 000 GNF) y sont repris, datés du dépôt où
 *     ils ont été saisis, marqués `reprise` : leur moyen de paiement n'a jamais été demandé.
 *  3. le **moyen de paiement** était une chaîne libre. → `App\Enums\MoyenPaiement` ; la chaîne vide
 *     trouvée en base (1 paiement) devient `null`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Nature ────────────────────────────────────────────────────────
        if (! Schema::hasColumn('baremes', 'nature')) {
            Schema::table('baremes', fn (Blueprint $t) => $t->string('nature', 20)->nullable()->after('organisme'));
        }
        if (! Schema::hasColumn('lignes_factures', 'nature')) {
            Schema::table('lignes_factures', fn (Blueprint $t) => $t->string('nature', 20)->nullable()->after('designation'));
        }

        DB::table('baremes')->whereNull('nature')->where('organisme', 'Notaire')->update(['nature' => 'honoraire']);
        DB::table('baremes')->whereNull('nature')->update(['nature' => 'debours']);

        // La ligne prend la nature de son barème ; une ligne sans barème est un débours (les 26
        // mesurées le sont toutes — aucune ne facture un travail de l'étude).
        foreach (DB::table('lignes_factures')->whereNull('nature')->get(['id', 'bareme_id']) as $ligne) {
            $nature = $ligne->bareme_id
                ? DB::table('baremes')->where('id', $ligne->bareme_id)->value('nature')
                : null;
            DB::table('lignes_factures')->where('id', $ligne->id)->update(['nature' => $nature ?? 'debours']);
        }

        // ── 2. Versements aux organismes ─────────────────────────────────────
        if (! Schema::hasTable('decaissements')) {
            Schema::create('decaissements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dossier_id')->constrained('dossiers')->cascadeOnDelete();
                $table->foreignId('formalite_id')->nullable()->constrained('formalites')->nullOnDelete();
                $table->string('libelle', 255);
                $table->string('organisme', 60)->nullable();
                $table->date('date_decaissement');
                $table->decimal('montant', 15, 2);
                $table->string('moyen', 20)->nullable();
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                // Le versement saisi au dépôt de la formalité — un seul par formalité, mis à jour
                // si le dépôt est réenregistré ; les autres versements s'ajoutent à la main.
                $table->boolean('via_depot')->default(false);
                $table->boolean('reprise')->default(false);
                $table->foreignId('enregistre_par_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            foreach (DB::table('formalites')->whereNotNull('montant_paye')->where('montant_paye', '>', 0)->get() as $f) {
                DB::table('decaissements')->insert([
                    'dossier_id'        => $f->dossier_id,
                    'formalite_id'      => $f->id,
                    'libelle'           => $f->libelle ?: $f->organisme,
                    'organisme'         => $f->organisme,
                    'date_decaissement' => substr((string) ($f->depose_at ?? $f->retour_at ?? $f->updated_at), 0, 10),
                    'montant'           => $f->montant_paye,
                    'moyen'             => null,
                    'reference'         => $f->numero_recepisse,
                    'via_depot'         => true,
                    'reprise'           => true,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }

        // ── 3. Moyen de paiement ─────────────────────────────────────────────
        DB::table('paiements')->where('moyen_paiement', '')->update(['moyen_paiement' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('decaissements');
        Schema::table('lignes_factures', fn (Blueprint $t) => $t->dropColumn('nature'));
        Schema::table('baremes', fn (Blueprint $t) => $t->dropColumn('nature'));
    }
};
