<?php

namespace Database\Seeders;

use App\Models\ModeleCourrier;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seed les lettres de transmission (courriers de fin de dossier), en copiant
 * les fichiers depuis Documents reçus/Courrier de transmission/.
 *
 * Contrairement aux modèles d'actes classiques, ces lettres ne sont pas
 * rattachées à un « type d'acte » unique mais liées explicitement (many-to-many)
 * aux types d'actes précis pour lesquels elles doivent apparaître à l'étape
 * Expédition. `applicable_tous = true` sert de raccourci pour les lettres
 * génériques plutôt que de les lier une à une à chaque type d'acte.
 *
 * Règle de copie identique à ModeleActeSeeder :
 *   - source .docx existante → copie vers storage/local, est_actif = true
 *   - source .doc  ou introuvable → pas de copie, est_actif = false
 */
class ModeleCourrierSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::withRole('administrateur')->first();
        $types = TypeActe::pluck('id', 'code');

        $srcBase = base_path('Documents reçus');

        $mappings = [
            [
                'nom'  => 'Transmission actes (minute)',
                'type_document' => 'lettre', 'version' => '1.0',
                'src'  => 'Courrier de transmission/TRANSMISSION ACTES (MINUTE).docx',
                'dest' => 'modeles/courrier/transmission-actes-minute.docx',
                'applicable_tous' => true,
                'types_actes' => [],
            ],
            [
                'nom'  => 'Transmission certificat authentique société',
                'type_document' => 'lettre', 'version' => '1.0',
                'src'  => 'Courrier de transmission/TRANSMISSION C.AUTH. SOCIETE.docx',
                'dest' => 'modeles/courrier/transmission-cauth-societe.docx',
                'applicable_tous' => false,
                'types_actes' => ['SOC-SARLU', 'SOC-SARL', 'SOC-SA', 'SOC-SAS', 'SOC-SASU', 'SOC-SNC', 'SOC-GIE'],
            ],
            [
                'nom'  => 'Transmission modification société',
                'type_document' => 'lettre', 'version' => '1.0',
                'src'  => 'Courrier de transmission/Transmission Modification société.docx',
                'dest' => 'modeles/courrier/transmission-modification-societe.docx',
                'applicable_tous' => false,
                'types_actes' => ['SOC-MOD'],
            ],
            [
                'nom'  => 'Transmission modification avec acte de dépôt du PV',
                'type_document' => 'lettre', 'version' => '1.0',
                'src'  => 'Courrier de transmission/Transmission MODIF AVEC ACTE DE DEPOT DU PV.docx',
                'dest' => 'modeles/courrier/transmission-modif-acte-depot-pv.docx',
                'applicable_tous' => false,
                'types_actes' => ['SOC-MOD'],
            ],
            [
                'nom'  => 'Accusé de réception société',
                'type_document' => 'lettre', 'version' => '1.0',
                'src'  => 'Courrier de transmission/Accusé reception SOCIETE.docx',
                'dest' => 'modeles/courrier/accuse-reception-societe.docx',
                'applicable_tous' => false,
                'types_actes' => ['SOC-SARLU', 'SOC-SARL', 'SOC-SA', 'SOC-SAS', 'SOC-SASU', 'SOC-SNC', 'SOC-GIE', 'SOC-DIS', 'SOC-MOD'],
            ],
            [
                'nom'  => 'Transmission vente bien immeuble',
                'type_document' => 'lettre', 'version' => '1.0',
                'src'  => 'Courrier de transmission/TRANSMISSION VENTE BIEN IMMEUBLE.docx',
                'dest' => 'modeles/courrier/transmission-vente-bien-immeuble.docx',
                'applicable_tous' => false,
                'types_actes' => ['VTE-IMM', 'VTE-SAN'],
            ],
            [
                'nom'  => 'Réquisition conservation foncière',
                'type_document' => 'lettre', 'version' => '1.0',
                'src'  => 'Courrier de transmission/Réquisition  conservation foncière.docx',
                'dest' => 'modeles/courrier/requisition-conservation-fonciere.docx',
                'applicable_tous' => false,
                'types_actes' => ['VTE-IMM', 'HYP-CON', 'HYP-MAI'],
            ],
            [
                'nom'  => "Transmission mainlevée d'hypothèque",
                'type_document' => 'lettre', 'version' => '1.0',
                'src'  => "Courrier de transmission/Transmission Mainlevée d'hypo..docx",
                'dest' => 'modeles/courrier/transmission-mainlevee-hypo.docx',
                'applicable_tous' => false,
                'types_actes' => ['HYP-MAI'],
            ],
            [
                'nom'  => 'Accusé de réception banque',
                'type_document' => 'lettre', 'version' => '1.0',
                'src'  => 'Courrier de transmission/Accusé de reception BANQUE.docx',
                'dest' => 'modeles/courrier/accuse-reception-banque.docx',
                'applicable_tous' => false,
                'types_actes' => ['HYP-CON', 'HYP-MAI'],
            ],
            [
                'nom'  => 'Accusé de réception contrat de prêt immobilier',
                'type_document' => 'lettre', 'version' => '1.0',
                'src'  => 'Courrier de transmission/ACCUSEE RECEPTION CONTRAT DE PRET IMMOBILIER.docx',
                'dest' => 'modeles/courrier/accuse-reception-pret-immobilier.docx',
                'applicable_tous' => false,
                'types_actes' => ['HYP-CON'],
            ],
            [
                'nom'  => 'Mise en place de crédit',
                'type_document' => 'lettre', 'version' => '1.0',
                'src'  => 'Courrier de transmission/MISE EN PLACE  DE CREDIT.docx',
                'dest' => 'modeles/courrier/mise-en-place-credit.docx',
                'applicable_tous' => false,
                'types_actes' => ['HYP-CON'],
            ],
            [
                'nom'  => 'Contrat de prêt bancaire',
                'type_document' => 'acte_principal', 'version' => '1.0',
                'src'  => 'Courrier de transmission/CONTRAT DE PRET LA BANQUE.docx',
                'dest' => 'modeles/courrier/contrat-pret-banque.docx',
                'applicable_tous' => false,
                'types_actes' => ['HYP-CON'],
            ],
            [
                'nom'  => 'Récépissé de dépôt de dossier',
                'type_document' => 'recepisse', 'version' => '1.0',
                'src'  => null,
                'dest' => 'modeles/courrier/recepisse-depot.docx',
                'applicable_tous' => true,
                'types_actes' => [],
            ],
        ];

        foreach ($mappings as $m) {
            $estActif = false;

            if ($m['src'] !== null) {
                $srcAbs = $srcBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $m['src']);
                $srcExt = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));

                if ($srcExt === 'docx' && file_exists($srcAbs)) {
                    $destAbs = Storage::disk('local')->path($m['dest']);
                    $destDir = dirname($destAbs);

                    if (! is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    if (! file_exists($destAbs)) {
                        copy($srcAbs, $destAbs);
                    }

                    $estActif = true;
                }
            }

            $modele = ModeleCourrier::firstOrCreate(
                ['nom' => $m['nom']],
                [
                    'type_document'   => $m['type_document'],
                    'chemin_fichier'  => $m['dest'],
                    'version'         => $m['version'],
                    'est_actif'       => $estActif,
                    'applicable_tous' => $m['applicable_tous'],
                    'updated_by'      => $admin?->id,
                ]
            );

            $typeActeIds = collect($m['types_actes'])->map(fn ($code) => $types[$code] ?? null)->filter()->values();
            $modele->typesActes()->sync($typeActeIds);
        }
    }
}
