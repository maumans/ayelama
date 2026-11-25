<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les comptes bancaires de l'étude, et la banque choisie sur chaque facture.
 *
 * Les notes de frais de l'étude impriment « N° de compte : 011406700100 ORABANK GUINEE SA » ; les
 * 25 factures de la plateforme imprimaient « — », la colonne `compte_numero` n'étant saisissable
 * nulle part. L'étude tient **huit** comptes (liste remise le 2026-11-20) et choisit la banque
 * facture par facture : le numéro suit la banque.
 *
 * La facture garde une **copie** de la banque et du numéro (`banque`, `compte_numero`) en plus du
 * lien : une facture émise est un instantané, et corriger un numéro de compte ne doit pas réécrire
 * ce qu'un client a déjà reçu. Le lien (`compte_bancaire_id`) servira la trésorerie.
 *
 * ⚠️ Deux numéros sont `a_verifier` : lus sur une liste manuscrite, un chiffre y reste douteux
 * (ACCESS BANK, dernier chiffre ; BSIC, troisième). L'écran Paramètres les signale, et le choix
 * de la banque sur la facture aussi.
 */
return new class extends Migration
{
    /** [banque, numéro, à vérifier] — dans l'ordre de la liste de l'étude. */
    private const COMPTES = [
        ['FIRST BANK',        '008 301 2010 0011 7727',   false],
        ['ORIS BANK',         '037 0010 21 0010 51 01 16', false],
        ['ACCESS BANK',       '036 002 01 00000568 12',   true],  // un « 0 » et le dernier chiffre pâles
        ['VISTA GUI SA',      '062 48 4000 562179',       false],
        ['VISTA BANK',        '011 100 111 8722 70215',   false],
        ['BSIC',              '32 1656 0015',             true],  // « 1 » et « 5 » du 2ᵉ groupe pâles
        ['ECOBANK',           '73 44 00 2411',            false],
        ['ORABANK GUINEE SA', '011406700100',             false], // tel qu'imprimé sur les notes 324 et 325
    ];

    public function up(): void
    {
        Schema::create('comptes_bancaires', function (Blueprint $table) {
            $table->id();
            $table->string('banque', 100);
            $table->string('numero', 60);
            $table->boolean('actif')->default(true);
            $table->boolean('a_verifier')->default(false);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->foreignId('compte_bancaire_id')->nullable()->after('compte_numero')
                ->constrained('comptes_bancaires')->nullOnDelete();
            $table->string('banque', 100)->nullable()->after('compte_bancaire_id');
        });

        foreach (self::COMPTES as $i => [$banque, $numero, $aVerifier]) {
            DB::table('comptes_bancaires')->insert([
                'banque'     => $banque,
                // Sans espaces, comme l'étude l'imprime (« 011406700100 ») : les groupements de la
                // liste manuscrite ne sont pas ceux d'un RIB, et les reproduire serait arbitraire.
                'numero'     => str_replace(' ', '', $numero),
                'actif'      => true,
                'a_verifier' => $aVerifier,
                'ordre'      => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('compte_bancaire_id');
            $table->dropColumn('banque');
        });

        Schema::dropIfExists('comptes_bancaires');
    }
};
