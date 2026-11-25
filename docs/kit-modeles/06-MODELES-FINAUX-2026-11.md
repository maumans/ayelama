# 06 — Modèles finaux reçus le 2026-11-20 (`docs/ACTES/`)

Analyse avant balisage. Les dix `.doc` ont été convertis en `.docx` par Word (COM, lecture seule,
macros désactivées — procédé du `05`), puis lus paragraphe par paragraphe et confrontés aux
gabarits en service et au moteur (`ActesGeneratorService`).

---

## 1. Ce que contient le dossier

Ce ne sont plus des formulaires à pointillés : ce sont **deux dossiers réels, complets**, rédigés
dans la forme définitive de l'étude.

| Dossier | Société | Forme | Pièces |
|---|---|---|---|
| `docs/ACTES/` (racine) | KOUMI INVESTMENTS LTD | **SARLU**, 10 000 000 GNF, associé unique gérant | Statuts, Page de garde, Attestation de dépôt (`ATTESTATION DE DEPOT.docx`), Déclaration sur l'honneur, DNSV, Insertion, RCCM, Facture |
| `docs/ACTES/SARL/` | NEXIUM | **SARL**, 100 000 000 GNF, deux associés, un gérant | Statuts, Page de garde, Attestation, Déclaration, DNSV, Insertion, RCCM, Facture + **un courrier** de transmission (DOUSSOU AGRO INDUSTRIES SARLU) |

⚠️ `docs/ACTES/ATTESTATION DE DEPÔT DU CAPITAL.docx` (racine) est l'attestation **NEXIUM SARL**,
doublon de celle du sous-dossier — pas celle de la SARLU.

⚠️ Ce sont des **données de clients réels** (identités, passeports). Les gabarits produits ne
doivent en garder **aucune** : le contrôle final cherche les noms, numéros de pièce et montants
dans chaque fichier livré.

## 2. État des gabarits concernés (mesuré en base)

| Forme | Actifs avec fichier | Absents |
|---|---|---|
| SARLU | Statuts, Attestation, Déclaration, DNSV, RCCM | Page de garde, Insertion |
| SARL | Attestation, DNSV | Statuts, Page de garde, Déclaration, Insertion, RCCM |

Les cinq SARLU actifs ont été balisés sur les **anciens** formulaires. Les nouveaux en diffèrent :
c'est la nouvelle rédaction qui doit faire foi.

## 3. Méthode retenue

Partir du `.docx` final (mise en page de l'étude intacte) et remplacer chaque **valeur réelle** par
sa balise avec le Rechercher/Remplacer de Word, qui traverse les découpages de runs. Pas de
reconstruction. Puis `php tools/verifier-balises.php`, recherche des données résiduelles,
génération réelle sur un dossier de test, et lecture du document produit.

## 4. Ce que le moteur ne sait pas rendre aujourd'hui — et que les actes finaux écrivent

C'est le point de décision. Remplacer les valeurs par les balises existantes produirait des actes
**différents** de ceux fournis par l'étude sur ces points :

| # | Ce que l'acte final écrit | Ce que le moteur rend | Proposition |
|---|---|---|---|
| M1 | « **Monsieur** Ousman JALLOH » | `${pp.civilite}` → « M. » | variante dérivée `${…civilite_longue}` → Monsieur / Madame / Mademoiselle |
| M2 | « **Né** à… », « ci-après dénommé », « présent », « Je soussigné » | rien — masculin écrit en dur dans les gabarits actuels | variantes d'accord dérivées de la civilité (`${pp.ne}` → Né/Née, `${pp.e}` → « » / « e »). Mesuré : 2 questionnaires sur 28 en « Mme », **11 sans civilité** — le masculin par défaut y serait une supposition |
| M3 | « le **quinze Février mille neuf cent quatre-vingt** » | `${pp.date_naissance_lettres}` → « QUINZE FÉVRIER MILLE NEUF CENT QUATRE-VINGT » (capitales) | variante en casse de phrase pour les dates d'état civil et de pièce |
| M4 | « **CENT** (100) parts sociales » | `${soc.nombre_parts}` n'a pas de `_lettres` (piège déjà noté au `03`) | dériver `soc.nombre_parts_lettres` |
| M5 | Objet social en **liste à puces**, une activité par ligne | `${soc.objet_social}` = un seul texte libre | bloc dérivé `soc.activites` (une puce par ligne saisie) ; le RCCM garde le texte en ligne |
| M6 | « cinquante (50) parts sociales, **numérotées de 1 à 50** ; … **de 51 à 100** » | aucun champ | dériver `associes.parts_debut` / `parts_fin` (cumul dans l'ordre de saisie) |
| M7 | « par **deux (02) personnes physiques** » | aucun champ | dériver le décompte des souscripteurs |
| M8 | « jusqu'au trente et un Décembre **Deux Mille vingt-sept** » (insertion) | `soc.premier_exercice_annee` sans lettres | dériver `_lettres` |
| M9 | RCCM : colonnes **NOM** et **PRÉNOM** séparées | `ger.prenom_nom` d'un seul tenant (les champs `ger.nom`/`ger.prenom` ont été retirés comme faux) | à trancher : une seule cellule, ou découpage par la convention « NOM en capitales » |
| M10 | Dates de naissance d'un **associé de SARL** en lettres | ⚠️ **défaut existant** : `remplirBlocRepetable()` ne dérive ni `_jma` ni `_lettres` des dates, alors que `balises-resolvables.txt` les annonce (`associes.date_naissance_lettres`) — le vérificateur les valide, l'acte sort un blanc | corriger le moteur |

Tout ajout est une variante **dérivée** (pas de nouveau champ à saisir), déclarée dans le moteur
**et** dans `tools/generer-balises-resolvables.mjs`, sinon `BalisesGabaritsActifsTest` la refuse.

## 5. Ce qui reste volontairement en pointillés

- **Nombre de pages** (« DONT ACTE EN MINUTE SUR HUIT (08) PAGES ») : `${acte.nb_pages}` est
  toujours vide, la pagination n'existe qu'après production. Garder les pointillés.
- **Numéro et date RCCM de l'insertion** (« RCCM/GN.TCC.2026.B….. du ……. ») : `retour.rccm_*`
  existe, mais l'insertion est produite à l'Édition, avant le retour du greffe. À décider :
  pointillés, ou régénération après le retour de formalité.
- **Date de signature du formulaire RCCM** (« Le ……… 2026 ») : laissée en blanc par l'étude.

## 6. Écarts entre les documents de l'étude (à ne pas recopier)

| Écart | Où | Traitement proposé |
|---|---|---|
| Adresse de l'office : « Immeuble **FiBank** » (insertion SARLU) / « Immeuble **VISTA BANK** » (tout le reste) | Insertion | `${office.adresse}` partout — déjà relevé dans les actes de dissolution |
| Formule d'en-tête du notaire rédigée de deux façons (statuts SARL vs DNSV) | Statuts SARL | conserver chaque rédaction telle que fournie |
| Objet de l'insertion SARLU ≠ objet des statuts (« L'immobilier » ajouté) | Insertion SARLU | une seule source : `soc.activites` |
| « la somme DIX MILLIONS » (sans « de ») ; « Par **exeption** » | DNSV, Insertion SARLU | corriger |
| « Date de début » du RCCM = date de l'acte | RCCM | `${soc.date_debut_activite_jma}` (champ ajouté en R17) |

## 7. Hors balisage, pour la suite

- **Notes de frais** (`FACTURE.xls`, n° 324 et 325/MAB) : titre « NOTE DE FRAIS ET ÉMOLUMENTS »,
  compte ORABANK, ligne forfaitaire « **Débours** » (500 000 / 1 000 000), droits
  d'enregistrement à **0**, timbres et rôles à 52 pages. À confronter aux barèmes et à la note
  générée — relève de la facturation (plan comptable), pas des actes.
- **Courrier** « Transmission des actes constitutifs » (DOUSSOU) : correspond au modèle de courrier
  n° 2 ; à rebaliser dans un second temps.

---

## 8. Réalisé le 2026-11-20

Les variantes M1 à M10 sont dans le moteur (`App\Support\VariantesRedaction`, décision #125 du
devbook) et les **14 gabarits** sont balisés, versionnés dans
`Documents reçus/Modèles finaux 2026-11/` et installés par la migration
`2026_11_20_100000_importer_modeles_finaux_sarlu_sarl` (version 2.0, actifs). Les fichiers
remplacés sont conservés dans `storage/app/private/modeles-archives/2026-11-20/`.

| Forme | Statuts | Page de garde | Attestation | Déclaration | DNSV | Insertion | RCCM |
|---|---|---|---|---|---|---|---|
| SARLU | 50 | 5 | 6 | 17 | 34 | 19 | 17 |
| SARL | 58 | 5 | 6 | 19 | 36 | 21 | 17 |

*(nombre de balises ; toutes résolvables.)*

Contrôles : `VariantesRedactionTest` (le moteur) et `ModelesFinauxSocieteTest`, qui **génère les
14 actes** sur des données fictives, refuse toute balise restante et toute donnée des deux
dossiers réels (corps, en-têtes, pieds de page, métadonnées). Rendu vérifié en PDF : statuts SARL
(9 pages, listes « 1. / 2. » prolongées par Word) et RCCM (une ligne de dirigeant par gérant).

M9 est tranché ainsi : NOM et PRÉNOM du RCCM viennent de `nom_famille` / `prenoms`, que seule une
**fiche client liée** fournit (5 questionnaires sur 28). Sans fiche, les deux cases restent vides
et l'historique le signale — jamais un découpage du nom complet.

### Ce qui reste, délibérément

| Point | Pourquoi |
|---|---|
| « titulaire du **passeport** … **délivré** » | l'accord suit la pièce (« la carte … délivrée ») ; `piece_type` est un texte libre, on ne devine pas son genre |
| « est nommé gérant Statutaire » avec plusieurs gérants | la phrase d'introduction est au singulier dans les statuts de l'étude ; chaque gérant est bien énuméré |
| Déclaration sur l'honneur SARL à plusieurs gérants | une déclaration par gérant, à la suite dans le même fichier, **sans saut de page** |
| Associé **personne morale** dans les statuts SARL | la comparution reprend l'état civil d'une personne physique ; `${associes.comparution}` existe pour ce cas, à brancher quand l'étude fournira un exemple |
| Durée « quatre-vingt-dix-neuf (99) années » | écrite en dur, comme dans tous les actes fournis ; `soc.duree` est facultatif |
| Nombre de pages, n° et date RCCM de l'insertion, date de signature du RCCM | pointillés (§5) |
| Courrier DOUSSOU, notes de frais | §7 |
