<?php

namespace App\Services;

use App\Models\Dossier;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Génère à la demande (jamais persisté en GED, toujours regénéré depuis les données
 * courantes) le PDF récapitulatif du questionnaire d'un dossier — destiné à être
 * imprimé et signé par le client, avant d'être re-téléversé comme preuve d'accord
 * via DossierController::televerserAccordClient().
 *
 * Pour les SARL et SARLU, produit une reproduction fidèle du document officiel
 * physique du cabinet (QUESTIONNAIRE SARL OK.pdf), sur 2 pages avec toutes les
 * mentions légales, checklist et blocs d'information.
 */
class FicheRecueilPdfService
{
    public function genererPdf(Dossier $dossier): string
    {
        $dossier->loadMissing(['typeActe', 'questionnaire', 'parties.client', 'societe']);

        if ($this->isSarl($dossier)) {
            $pdf = $this->genererPdfSarl($dossier);
        } else {
            $pdf = $this->genererPdfGenerique($dossier);
        }

        $chemin = 'fiches-recueil/' . $dossier->reference . '/fiche_' . time() . '.pdf';
        Storage::disk('public')->put($chemin, $pdf->output());

        return $chemin;
    }

    public function isSarl(Dossier $dossier): bool
    {
        $code = $dossier->typeActe?->code ?? '';
        if (in_array($code, ['SOC-SARL', 'SOC-SARLU'])) {
            return true;
        }

        $forme = $this->getFormeSociete($dossier);
        if (in_array($forme, ['SARL', 'SARLU'])) {
            return true;
        }

        $label = $dossier->typeActe?->label ?? '';
        if (stripos($label, 'SARL') !== false) {
            return true;
        }

        return false;
    }

    private function getFormeSociete(Dossier $dossier): string
    {
        $forme = $dossier->societe?->forme;
        if (is_object($forme)) {
            return $forme->value ?? (string) $forme;
        }
        return (string) ($forme ?? '');
    }

    private function genererPdfSarl(Dossier $dossier)
    {
        $donnees = $dossier->questionnaire?->donnees ?? [];

        $officeNom   = Setting::get('office_nom', 'Maître Ayelama BAH');
        $officeEmail = Setting::get('office_email', 'ayelama.bah@notaire-guinee.com');

        // a) Dénomination
        $denomination = $donnees['soc.denomination'] ?? $dossier->societe?->nom ?? $dossier->objet ?? '';
        $sigle = $donnees['soc.sigle'] ?? $dossier->societe?->sigle ?? null;
        if (!empty($sigle) && stripos($denomination, "({$sigle})") === false) {
            $denomination .= ' (' . $sigle . ')';
        }

        // b) Siège social
        $partsSiege = array_filter([
            $donnees['soc.siege_quartier'] ?? $dossier->societe?->quartier ?? null,
            $donnees['soc.siege_commune']  ?? $dossier->societe?->commune  ?? null,
            $donnees['soc.siege_ville']    ?? $dossier->societe?->ville    ?? null,
        ]);
        $siegeSocial = !empty($partsSiege) ? implode(', ', $partsSiege) : ($dossier->societe?->adresse ?? '');

        // c) Email
        $emailSociete = $donnees['soc.email_societe'] ?? $dossier->societe?->email ?? '';

        // d) Capital social
        $capitalChiffres = $donnees['soc.capital_chiffres'] ?? $dossier->societe?->capital_social ?? null;
        $nbParts         = $donnees['soc.nombre_parts'] ?? null;
        $valNominale     = $donnees['soc.valeur_nominale_chiffres'] ?? null;

        $capitalSocial = '';
        if ($capitalChiffres) {
            $capitalFormate = number_format((float) $capitalChiffres, 0, ',', ' ');
            $lettres = class_exists(NombreEnLettres::class)
                ? mb_convert_case(mb_strtolower(NombreEnLettres::convertir((float) $capitalChiffres, ''), 'UTF-8'), MB_CASE_TITLE, 'UTF-8')
                : '';

            $capitalSocial = $capitalFormate . ' GNF';
            if (!empty($lettres)) {
                $capitalSocial .= ' (' . $lettres . ' Francs Guinéens)';
            }
            if ($nbParts && $valNominale) {
                $capitalSocial .= ', divisé en ' . number_format((float) $nbParts, 0, ',', ' ') . ' parts de ' . number_format((float) $valNominale, 0, ',', ' ') . ' GNF chacune';
            } elseif ($nbParts) {
                $capitalSocial .= ', divisé en ' . number_format((float) $nbParts, 0, ',', ' ') . ' parts';
            }
        }

        // e) Répartition du capital
        $repartitionAssocies = [];
        $formeStr = $this->getFormeSociete($dossier);
        $isSarlu = ($dossier->typeActe?->code === 'SOC-SARLU') || ($formeStr === 'SARLU');

        // Récupération de l'associé unique si SARLU
        $ppNom = $donnees['pp.prenom_nom'] ?? null;
        $ppCiv = $donnees['pp.civilite'] ?? '';
        $partieAssocieUnique = $dossier->parties->firstWhere('role', 'associe_unique')
            ?? $dossier->parties->firstWhere('role', 'associe');

        $nomAssocieUnique = trim(($ppCiv ? $ppCiv . ' ' : '') . ($ppNom ?: ($partieAssocieUnique?->nom ?? '')));

        if ($isSarlu && !empty($nomAssocieUnique)) {
            $detailsParts = [];
            if ($nbParts) {
                $detailsParts[] = number_format((float) $nbParts, 0, ',', ' ') . ' parts';
            }
            if ($capitalChiffres) {
                $detailsParts[] = number_format((float) $capitalChiffres, 0, ',', ' ') . ' GNF (100 %)';
            }
            $repartitionAssocies[] = $nomAssocieUnique . (!empty($detailsParts) ? ' : ' . implode(' — ', $detailsParts) : '');
        } elseif (isset($donnees['associes']) && is_array($donnees['associes']) && count($donnees['associes']) > 0) {
            foreach ($donnees['associes'] as $associe) {
                $nomAssoc = trim(($associe['civilite'] ?? '') . ' ' . ($associe['nom'] ?? ''));
                if ($nomAssoc === '') continue;
                $p = $associe['parts_chiffres'] ?? null;
                $repartitionAssocies[] = $nomAssoc . ($p ? ' : ' . number_format((float) $p, 0, ',', ' ') . ' parts' : '');
            }
        } else {
            $partiesAssocies = $dossier->parties->whereIn('role', ['associe', 'associe_unique']);
            foreach ($partiesAssocies as $p) {
                $nomAssoc = $p->nom;
                $parts = $p->parts;
                $repartitionAssocies[] = $nomAssoc . ($parts ? ' : ' . number_format((float) $parts, 0, ',', ' ') . ' parts' : '');
            }
        }

        // f) Objet social
        $objetSocial = $donnees['soc.objet_social'] ?? $dossier->societe?->objet_social ?? $dossier->objet ?? '';
        $objetSocialLignes = [];
        if (!empty($objetSocial)) {
            $lignesBrutes = preg_split('/[\r\n]+/', (string) $objetSocial);
            foreach ($lignesBrutes as $ligne) {
                $ligne = trim($ligne, " \t\n\r\0\x0B•-");
                if ($ligne !== '') {
                    $objetSocialLignes[] = $ligne;
                }
            }
        }

        // g) Gérant(s)
        $gerants = [];
        $gerantEstDifferent = !empty($donnees['ger.est_different']);

        if ($isSarlu) {
            if (!$gerantEstDifferent) {
                if (!empty($nomAssocieUnique)) {
                    $gerants[] = $nomAssocieUnique;
                }
            } else {
                $gerantNom = trim(($donnees['ger.civilite'] ?? '') . ' ' . ($donnees['ger.prenom_nom'] ?? ''));
                if (!empty($gerantNom)) {
                    $gerants[] = $gerantNom;
                }
            }
        } elseif (isset($donnees['gerants']) && is_array($donnees['gerants']) && count($donnees['gerants']) > 0) {
            foreach ($donnees['gerants'] as $gerant) {
                $gNom = trim(($gerant['civilite'] ?? '') . ' ' . ($gerant['prenom_nom'] ?? ''));
                if ($gNom !== '') {
                    $gerants[] = $gNom;
                }
            }
        } else {
            $partiesGerants = $dossier->parties->where('role', 'gerant');
            foreach ($partiesGerants as $p) {
                $gerants[] = $p->nom;
            }
        }

        // Coordonnées pour la checklist gérant
        if ($isSarlu && !$gerantEstDifferent) {
            $situationMatrimonialeGerant = $donnees['pp.situation_matrimoniale'] ?? '';
            $telephoneGerant = $donnees['pp.telephone'] ?? $donnees['soc.telephone_societe'] ?? '';
            $emailGerant     = $donnees['pp.email'] ?? $donnees['soc.email_societe'] ?? '';
        } else {
            $situationMatrimonialeGerant = $donnees['ger.situation_matrimoniale'] ?? '';
            $telephoneGerant = $donnees['ger.telephone'] ?? $donnees['soc.telephone_societe'] ?? '';
            $emailGerant     = $donnees['ger.email'] ?? $donnees['soc.email_societe'] ?? '';
        }

        // h) Organes de contrôle
        $commissaireComptes = $donnees['soc.commissaire_titulaire']
            ?? $donnees['cac_titulaire.prenom_nom']
            ?? '';

        // Informations diverses & régime fiscal
        $regimeFiscalReference = $donnees['soc.regime_fiscal_reference'] ?? '';

        return Pdf::loadView('fiches_recueil.sarl_pdf', [
            'dossier'                      => $dossier,
            'officeNom'                    => $officeNom,
            'officeEmail'                  => $officeEmail,
            'denomination'                 => $denomination,
            'siegeSocial'                  => $siegeSocial,
            'emailSociete'                 => $emailSociete,
            'capitalSocial'                => $capitalSocial,
            'repartitionAssocies'          => $repartitionAssocies,
            'objetSocialLignes'            => $objetSocialLignes,
            'gerants'                      => $gerants,
            'situationMatrimonialeGerant'  => $situationMatrimonialeGerant,
            'telephoneGerant'              => $telephoneGerant,
            'emailGerant'                  => $emailGerant,
            'commissaireComptes'           => $commissaireComptes,
            'regimeFiscalReference'        => $regimeFiscalReference,
        ]);
    }

    private function genererPdfGenerique(Dossier $dossier)
    {
        $groupes = [];
        foreach ($dossier->questionnaire?->donnees ?? [] as $cle => $valeur) {
            if (is_array($valeur) || $valeur === null || $valeur === '') {
                continue;
            }

            $segments = explode('.', $cle, 2);
            $prefixe  = count($segments) > 1 ? $segments[0] : '';
            $suffixe  = count($segments) > 1 ? $segments[1] : $segments[0];

            $groupes[$prefixe][] = [
                'label'  => ucfirst(str_replace('_', ' ', $suffixe)),
                'valeur' => is_bool($valeur) ? ($valeur ? 'Oui' : 'Non') : (string) $valeur,
            ];
        }

        return Pdf::loadView('fiches_recueil.pdf', [
            'dossier' => $dossier,
            'groupes' => $groupes,
        ]);
    }
}
