# Dictionnaire des balises — Office Notarial Ayelama BAH

> Guide pratique pour normaliser les modèles `.docx`.  
> **Règle** : remplacer chaque marqueur original (pointillés, MAJUSCULES, instructions) par la balise `${nom_balise}` correspondante.  
> **Convention de nommage** : `bloc.champ` en `snake_case` minuscule.  
> Tout montant a deux variantes : `_chiffres` (saisi) et `_lettres` (généré automatiquement).  
> Toute date a la variante `_jma` (JJ/MM/AAAA) et parfois `_lettres` (en toutes lettres).

---

## Sommaire des blocs

| Bloc | Préfixe balise | Usage principal |
|------|---------------|-----------------|
| [Office (constantes)](#1-bloc-office--constantes-de-loffice) | `office.` | En-tête de tous les actes |
| [Dossier](#2-bloc-dossier) | `dossier.` | Référence, date de l'acte |
| [Personne physique](#3-bloc-personne-physique) | `pp.` | Associé unique, gérant (SARLU/SASU), vendeur |
| [Acquéreur](#acquéreur) | `acq.` | Vente — distinct du vendeur `pp.*` |
| [Locataire](#locataire) | `loc.` | Bail — distinct du bailleur `pp.*` |
| [Personne morale](#4-bloc-personne-morale) | `pm.` | Associé société, représentant |
| [Société](#5-bloc-société) | `soc.` | Statuts, DNSV, Page de garde, RCCM, JAL |
| [Blocs répétables](#blocs-répétables-associés-gérants-administrateurs) | `associes`, `gerants`, `administrateurs`, `actionnaires`, `membres` | Listes de personnes |
| [Bien immobilier](#6-bloc-bien-immobilier) | `bien.` | Contrat de vente, bail à construction |
| [Banque / Hypothèque](#7-bloc-banque--hypothèque) | `bq.` | Hypothèque, courriers banque |
| [Bail](#8-bloc-bail) | `bail.` | Contrat de bail habitation, pro, à construction |
| [Courrier](#9-bloc-courrier) | `cr.` | Les 13 courriers de transmission |
| [Facture](#10-bloc-facture) | `fac.` | Notes de frais, bordereaux |
| [Dissolution](#dissolution) | `dissolution.` + `liquidateur.` | Acte de dissolution |

---

## 1. Bloc OFFICE — Constantes de l'Office

> Ces valeurs sont **stockées en configuration**, **jamais saisies** dans un formulaire.  
> Le service les injecte automatiquement dans tous les documents.

| Balise | Valeur fixe | Marqueurs à remplacer dans le .docx |
|--------|------------|--------------------------------------|
| `${office.notaire}` | `Maître Ayelama BAH` | `MAITRE AYELAMA BAH`, `Maître …………` |
| `${office.titre}` | `Notaire` | `Notaire` (déjà correct en général) |
| `${office.charge}` | `n°21` | `n°…`, `N° DE CHARGE` |
| `${office.residence}` | `Ratoma` | `COMMUNE DE RÉSIDENCE`, `Ratoma` |
| `${office.adresse}` | `Nongo, 3ᵉ étage, Immeuble VISTA BANK` | `ADRESSE DE L'OFFICE`, `Nongo……` |
| `${office.bp}` | `BP 2668/2868` | `BP …………` |
| `${office.commune}` | `Commune de Ratoma/Lambanyi` | `COMMUNE DE RATOMA`, `……………` |
| `${office.telephones}` | `622 49 69 44 / 664 20 96 07 / 655 61 38 38` | `Tél : …………`, `TEL :` |
| `${office.email}` | `ayelama.bah@notaire-guinee.com` | `email@…`, `EMAIL :` |

---

## 2. Bloc DOSSIER

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${dossier.reference}` | Référence automatique du dossier (ex : `VTE-2026-0012`) | Texte | `N° DE DOSSIER`, `Réf. …………` |
| `${date_acte_jma}` | Date de signature de l'acte (JJ/MM/AAAA) | Date | `J/M/A`, `(JOUR/MOIS/AN)`, `LE …………`, `……/……/………` |
| `${annee_lettres}` | Année en lettres majuscules | Texte auto | `DEUX MILLE VINGT…`, `(ANNÉE EN LETTRES)` |
| `${date_acte_lettres}` | Date complète en lettres (`LE TRENTE JUIN DEUX MILLE VINGT-SIX`) | Texte auto | `LE (DATE EN LETTRES)` |

---

## 3. Bloc PERSONNE PHYSIQUE

> Utilisé pour : associé, gérant, vendeur, acquéreur, bailleur, preneur, emprunteur, déclarant.  
> Dans les statuts et DNSV, les personnes sont en **liste répétable** : `${bloc_associe}…${/bloc_associe}`.  
> Préfixe par défaut : `pp.` — en liste : `pp_1.`, `pp_2.` ou via bloc clonable.

### 3.1 État civil

| Balise | Description | Type / Valeurs | Marqueurs à remplacer |
|--------|------------|----------------|-----------------------|
| `${pp.civilite}` | M. ou Mme | Énumération : `M. / Mme` | `M./Mme`, `CIVILITE`, `……` avant le nom |
| `${pp.prenom_nom}` | Prénom(s) et Nom de famille | Texte | `PRENOM ET NOM`, `NOM ET PRENOM(S)`, `……………………` |
| `${pp.ne_a}` | Lieu de naissance (ville) | Texte | `NÉ(E) À …………`, `LIEU DE NAISSANCE` |
| `${pp.date_naissance}` | Date de naissance (JJ/MM/AAAA) | Date | `LE …………`, `DATE DE NAISSANCE : …………` |
| `${pp.nationalite}` | Nationalité | Texte (déf. `Guinéenne`) | `DE NATIONALITÉ …………`, `NATIONALITÉ` |
| `${pp.situation_matrimoniale}` | Célibataire / Marié(e) / Divorcé(e) / Veuf(ve) | Énumération | `SITUATION MATRIMONIALE`, `…………` |
| `${pp.regime_matrimonial}` | Séparation de biens / Communauté | Texte (vente uniquement) | `RÉGIME MATRIMONIAL`, `(À PRÉCISER)` |

### 3.2 Adresse

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${pp.demeurant_ville}` | Ville de résidence | Texte | `VILLE`, `DEMEURANT À …………` |
| `${pp.quartier}` | Quartier | Texte | `QUARTIER`, `QUARTIER : …………` |
| `${pp.commune}` | Commune | Texte | `COMMUNE`, `COMMUNE DE …………` |
| `${pp.pays}` | Pays | Texte (déf. `République de Guinée`) | `PAYS`, `EN RÉPUBLIQUE DE …………` |

### 3.3 Pièce d'identité

| Balise | Description | Type / Valeurs | Marqueurs à remplacer |
|--------|------------|----------------|-----------------------|
| `${pp.piece_type}` | Type de document | Énumération : `Passeport / CNI CEDEAO` | `PASSEPORT/CNI`, `PIÈCE D'IDENTITÉ`, `…………` |
| `${pp.piece_numero}` | Numéro de la pièce | Texte | `N° …………`, `NUMÉRO …………` |
| `${pp.piece_delivree_le}` | Date de délivrance (JJ/MM/AAAA) | Date | `DÉLIVRÉE LE …………`, `DATE DE DÉLIVRANCE` |
| `${pp.piece_delivree_a}` | Lieu de délivrance | Texte | `À …………`, `PAR …………` |
| `${pp.piece_expire_le}` | Date d'expiration (JJ/MM/AAAA) | Date | `VALABLE JUSQU'AU …………`, `EXPIRE LE …………` |

### 3.4 Contact

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${pp.telephone}` | Numéro de téléphone | Texte | `TEL : …………`, `TÉLÉPHONE …………` |
| `${pp.email}` | Adresse email | Texte | `EMAIL : …………`, `@` |

### 3.5 Pièces jointes attendues (non injectées dans l'acte — pour la checklist)

- `cni` — Copie CNI / Passeport
- `certificat_residence` — Certificat de résidence
- `photos` — 2 photos d'identité
- `casier_judiciaire` — Casier judiciaire (dirigeants uniquement)

---

## Acquéreur

> Utilisé dans les contrats de **vente** uniquement, distinct du vendeur (`pp.*`).

| Balise | Description | Marqueurs à remplacer |
|--------|------------|----------------------|
| `${acq.civilite}` | M. / Mme | `M./Mme` (côté acquéreur) |
| `${acq.prenom_nom}` | Nom complet de l'acquéreur | `NOM DE L'ACQUÉREUR`, `AU PROFIT DE …………` |
| `${acq.nationalite}` | Nationalité | `DE NATIONALITÉ …………` |
| `${acq.adresse}` | Adresse complète | `DEMEURANT À …………` |
| `${acq.piece_type}` | Type de pièce d'identité | `CNI CEDEAO / Passeport` |
| `${acq.piece_numero}` | Numéro de pièce | `N° …………` |
| `${acq.telephone}` | Téléphone | `TEL …………` |
| `${acq.email}` | Email | `EMAIL …………` |

---

## Locataire

> Utilisé dans les **baux** uniquement, distinct du bailleur (`pp.*`).

| Balise | Description | Marqueurs à remplacer |
|--------|------------|----------------------|
| `${loc.civilite}` | M. / Mme | `M./Mme` (côté locataire) |
| `${loc.prenom_nom}` | Nom complet du locataire | `LE PRENEUR …………`, `LOCATAIRE …………` |
| `${loc.nationalite}` | Nationalité | `DE NATIONALITÉ …………` |
| `${loc.adresse}` | Adresse complète | `DEMEURANT À …………` |
| `${loc.piece_type}` | Type de pièce d'identité | `CNI CEDEAO / Passeport` |
| `${loc.piece_numero}` | Numéro de pièce | `N° …………` |
| `${loc.telephone}` | Téléphone | `TEL …………` |
| `${loc.email}` | Email | `EMAIL …………` |

---

## Blocs répétables — Associés, Gérants, Administrateurs

> **Syntaxe PhpWord** : le bloc dans le `.docx` est délimité par `${associes}` … `${/associes}`.  
> PhpWord clone le bloc N fois (`cloneBlock`). Les variables internes sont indexées : `${associes#1.nom}`, `${associes#2.nom}`, etc.  
> **Nommage en base** : les données sont stockées en JSON sous `donnees.associes = [{nom:'...', parts_chiffres:'100'}, ...]`.

### Bloc `associes` — Associés (SARL, SAS, SA, SNC, GIE)

| Balise | Description | Type |
|--------|------------|------|
| `${associes#N.nom}` | Nom / dénomination de l'associé | Texte |
| `${associes#N.type_personne}` | Personne physique / Personne morale | Énumération |
| `${associes#N.parts_chiffres}` | Nombre de parts / actions | Entier |
| `${associes#N.parts_lettres}` | Nombre de parts en lettres | **Auto** |
| `${associes#N.nationalite}` | Nationalité / Pays | Texte |
| `${associes#N.adresse}` | Adresse | Texte |
| `${associes#N.cni}` | N° pièce d'identité / RCCM | Texte |

### Bloc `actionnaires` — Actionnaires SA

| Balise | Description | Type |
|--------|------------|------|
| `${actionnaires#N.nom}` | Nom / dénomination | Texte |
| `${actionnaires#N.type_personne}` | PP / PM | Énumération |
| `${actionnaires#N.actions_chiffres}` | Nombre d'actions | Entier |
| `${actionnaires#N.actions_lettres}` | Nombre d'actions en lettres | **Auto** |
| `${actionnaires#N.nationalite}` | Nationalité / Pays | Texte |

### Bloc `gerants` — Gérants (SARL multi-gérants)

| Balise | Description | Type |
|--------|------------|------|
| `${gerants#N.civilite}` | M. / Mme | Énumération |
| `${gerants#N.prenom_nom}` | Nom et prénoms | Texte |
| `${gerants#N.ne_a}` | Lieu de naissance | Texte |
| `${gerants#N.date_naissance}` | Date de naissance | Date |
| `${gerants#N.nationalite}` | Nationalité | Texte |
| `${gerants#N.adresse}` | Adresse | Texte |
| `${gerants#N.piece_numero}` | N° pièce d'identité | Texte |

### Bloc `administrateurs` — Conseil d'Administration SA / Administrateurs GIE

| Balise | Description | Type |
|--------|------------|------|
| `${administrateurs#N.prenom_nom}` | Nom et prénoms | Texte |
| `${administrateurs#N.nationalite}` | Nationalité | Texte |
| `${administrateurs#N.domicile}` | Domicile | Texte |
| `${administrateurs#N.fonction}` | Fonction au CA | Texte |

### Bloc `membres` — Membres GIE

| Balise | Description | Type |
|--------|------------|------|
| `${membres#N.nom}` | Nom / dénomination | Texte |
| `${membres#N.type_personne}` | PP / PM | Énumération |
| `${membres#N.apport_chiffres}` | Apport en GNF | Montant |
| `${membres#N.apport_lettres}` | Apport en lettres | **Auto** |
| `${membres#N.adresse}` | Adresse | Texte |

---

## Dissolution

| Balise | Description | Marqueurs à remplacer |
|--------|------------|----------------------|
| `${soc.rccm}` | Numéro RCCM de la société dissoute | `RCCM N° …………` |
| `${dissolution.date_assemblee}` | Date de l'assemblée de dissolution | `EN DATE DU …………` |
| `${dissolution.raison}` | Raison / motif de dissolution | `POUR LES MOTIFS SUIVANTS : …………` |
| `${dissolution.type}` | Amiable / Judiciaire | `(TYPE DE DISSOLUTION)` |
| `${liquidateur.nom}` | Nom du liquidateur | `LIQUIDATEUR : …………` |
| `${liquidateur.qualite}` | Qualité du liquidateur | `EN SA QUALITÉ DE …………` |
| `${liquidateur.adresse}` | Adresse du liquidateur | `DEMEURANT …………` |

---

## 4. Bloc PERSONNE MORALE

> Utilisé pour un **associé qui est lui-même une société** (pas le gérant).

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${pm.denomination}` | Dénomination sociale | Texte | `NOM DE LA SOCIÉTÉ ASSOCIÉE`, `…………` |
| `${pm.forme}` | Forme juridique | Texte | `SARL / SAS / SA…` |
| `${pm.rccm}` | Numéro RCCM | Texte | `RCCM N° …………` |
| `${pm.representant_legal}` | Nom du représentant légal | Texte | `REPRÉSENTÉ PAR …………` |
| `${pm.siege}` | Siège social (adresse) | Texte | `DONT LE SIÈGE EST À …………` |

---

## 5. Bloc SOCIÉTÉ

> Cœur des statuts SARL, SARLU, SAS, SASU, SA, GIE.

### 5.1 Identification

| Balise | Description | Type / Valeurs | Marqueurs à remplacer |
|--------|------------|----------------|-----------------------|
| `${soc.denomination}` | Dénomination sociale de la société | Texte | `NOM DE LA SOCIÉTÉ`, `DÉNOMINATION SOCIALE`, `………………………` |
| `${soc.forme}` | Forme juridique | Énumération : `SARL / SARLU / SAS / SASU / SA / GIE` | `FORME JURIDIQUE`, `(SARL)`, `………` |
| `${soc.sigle}` | Sigle (optionnel) | Texte | `SIGLE : …………` (laisser vide si absent) |

### 5.2 Capital

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${soc.capital_chiffres}` | Montant du capital social en GNF | Montant (entier) | `MONTANT DU CAPITAL EN CHIFFRE`, `……………… GNF` |
| `${soc.capital_lettres}` | Capital en lettres majuscules | **Auto** depuis `capital_chiffres` | `MONTANT DU CAPITAL EN LETTRE`, `(CAPITAL EN TOUTES LETTRES)` |
| `${soc.capital_libere_chiffres}` | Capital libéré à la constitution (SA — min. ¼) | Montant | `CAPITAL LIBÉRÉ EN CHIFFRE`, `… GNF LIBÉRÉS` |
| `${soc.capital_libere_lettres}` | Capital libéré en lettres | **Auto** | `CAPITAL LIBÉRÉ EN LETTRE` |
| `${soc.nombre_parts}` | Nombre de parts sociales (SARL/SARLU/SNC/GIE) | Entier | `NOMBRE DE PARTS`, `………` |
| `${soc.nombre_actions}` | Nombre d'actions (SA/SAS/SASU) | Entier | `NOMBRE D'ACTIONS`, `………` |
| `${soc.valeur_nominale_chiffres}` | Valeur nominale d'une part/action en GNF | Montant | `VALEUR NOMINALE EN CHIFFRE`, `………… GNF PAR PART/ACTION` |
| `${soc.valeur_nominale_lettres}` | Valeur nominale en lettres | **Auto** | `VALEUR NOMINALE EN LETTRE` |

### 5.3 Siège social

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${soc.siege_quartier}` | Quartier du siège | Texte | `QUARTIER`, `………………` |
| `${soc.siege_commune}` | Commune du siège | Texte | `COMMUNE`, `COMMUNE DE ………` |
| `${soc.siege_ville}` | Ville du siège | Texte | `VILLE`, `À …………` |
| `${soc.email_societe}` | Email de la société | Texte | `EMAIL SOCIÉTÉ`, `@………` |
| `${soc.telephone_societe}` | Téléphone de la société | Texte | `TEL SOCIÉTÉ`, `………………` |

### 5.4 Objet et durée

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${soc.objet_social}` | Objet social (liste des secteurs d'activité) | Texte long | `OBJET SOCIAL`, `(DÉCRIRE L'ACTIVITÉ)`, `………………………………` |
| `${soc.duree}` | Durée de la société en années | Entier (déf. `99`) | `DURÉE : … ANS`, `QUATRE-VINGT-DIX-NEUF (99) ANS` |
| `${soc.exercice_social}` | Période de l'exercice | Texte (déf. `1er janvier au 31 décembre`) | `DU … AU …`, `EXERCICE SOCIAL` |

### 5.5 Direction (variable selon la forme)

#### SARL / SARLU — Gérant(s)

| Balise | Description | Marqueurs |
|--------|------------|-----------|
| `${soc.gerant_1}` | Nom complet du 1er gérant | `NOM DU GÉRANT`, `GÉRANT : …………` |
| `${soc.gerant_2}` | Nom du 2e gérant (si cogérance) | `2ème GÉRANT : …………` |

#### SAS / SASU — Président + DG

| Balise | Description | Marqueurs |
|--------|------------|-----------|
| `${soc.president_nom}` | Nom du Président | `PRÉSIDENT : …………` |
| `${soc.president_civilite}` | Civilité | `M./Mme` (Président) |
| `${soc.president_ne_a}` | Lieu de naissance | `NÉ(E) À …………` |
| `${soc.president_date_naissance}` | Date de naissance | `LE …………` |
| `${soc.president_nationalite}` | Nationalité | `DE NATIONALITÉ …………` |
| `${soc.president_adresse}` | Adresse | `DEMEURANT …………` |
| `${soc.president_piece_numero}` | N° pièce d'identité | `N° …………` |
| `${soc.dg_nom}` | Nom du Directeur Général (optionnel) | `DIRECTEUR GÉNÉRAL : …………` |
| `${soc.dg_civilite}` | Civilité DG | `M./Mme` (DG) |

#### SA — PCA + Conseil d'Administration + DG

| Balise | Description | Marqueurs |
|--------|------------|-----------|
| `${soc.pca_nom}` | Nom du PCA | `PRÉSIDENT DU CONSEIL …………`, `PCA : …………` |
| `${soc.pca_civilite}` | Civilité PCA | `M./Mme` (PCA) |
| `${soc.pca_adresse}` | Adresse PCA | `DEMEURANT …………` |
| `${soc.dg_nom}` | Nom du DG | `DIRECTEUR GÉNÉRAL : …………` |
| `${soc.dg_civilite}` | Civilité DG | `M./Mme` (DG) |
| Bloc `${administrateurs}…${/administrateurs}` | Liste des administrateurs (3-12) | Voir section **Blocs répétables** |

#### GIE — Administrateurs

| Balise | Marqueurs |
|--------|-----------|
| Bloc `${administrateurs}…${/administrateurs}` | Voir section **Blocs répétables** |

### 5.6 Commissaires aux comptes (optionnel)

| Balise | Description | Marqueurs |
|--------|------------|-----------|
| `${soc.commissaire_titulaire}` | Nom du commissaire titulaire | `COMMISSAIRE AUX COMPTES TITULAIRE`, `…………` |
| `${soc.commissaire_suppleant}` | Nom du commissaire suppléant | `COMMISSAIRE SUPPLÉANT`, `…………` |

> ⚠️ **SAS** : obligatoire si >50 % du capital détenu par des sociétés.  
> ⚠️ **SA** : capital minimum 140 000 000 GNF, libération minimale ¼ à la constitution.

### 5.7 Associés — Bloc répétable

> Utilise `${bloc_associe}…${/bloc_associe}` (cloneBlock PhpWord).  
> Chaque entrée contient les champs du **Bloc Personne physique** ou **Bloc Personne morale** + les champs de répartition ci-dessous.

| Balise | Description | Marqueurs |
|--------|------------|-----------|
| `${associe.prenom_nom}` | Nom de l'associé | `NOM DE L'ASSOCIÉ …………` |
| `${associe.nombre_parts}` | Nombre de parts apportées | `… PARTS SOCIALES` |
| `${associe.nombre_parts_lettres}` | Idem en lettres | `(… EN LETTRES) PARTS` |
| `${associe.montant_apport_chiffres}` | Montant de l'apport en GNF | `… GNF` |
| `${associe.montant_apport_lettres}` | Idem en lettres | **Auto** |
| `${associe.pourcentage}` | Pourcentage du capital | `(… %)` |

### 5.8 Immatriculation et publication

| Balise | Description | Marqueurs |
|--------|------------|-----------|
| `${soc.rccm_numero}` | Numéro RCCM obtenu | `RCCM N° …………` |
| `${soc.nif}` | Numéro d'Identification Fiscale | `NIF …………` |
| `${soc.jal_journal}` | Nom du journal d'annonces légales | `JOURNAL …………`, `JAL : …………` |

---

## 6. Bloc BIEN IMMOBILIER

> Utilisé dans : Contrat de vente, Page de garde, Tableau de bordereau, Bail à construction.

### 6.1 Identification de la parcelle

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${bien.parcelle_numero}` | Numéro de parcelle | Texte | `PARCELLE N° …………`, `N° DE PARCELLE` |
| `${bien.lot_numero}` | Numéro de lot | Texte | `LOT N° …………` |
| `${bien.lieu_de}` | Lieu-dit / localisation | Texte | `LIEU-DIT …………`, `SIS À …………` |
| `${bien.nature_terrain}` | Nature du terrain | Énumération : `urbain bâti / non bâti` | `NATURE DU TERRAIN`, `(BÂTI/NON BÂTI)` |
| `${bien.usage}` | Usage | Énumération : `habitation / commercial / mixte` | `USAGE …………` |
| `${bien.superficie_m2}` | Superficie en m² | Décimal | `SUPERFICIE : … m²`, `D'UNE CONTENANCE DE …………` |
| `${bien.pcp}` | Plan de codification parcellaire | Texte | `PCP …………` |

### 6.2 Titre foncier

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${bien.titre_foncier_numero}` | Numéro du titre foncier | Texte | `TF N° …………`, `TITRE FONCIER N°` |
| `${bien.tf_date_jma}` | Date d'établissement du TF (JJ/MM/AAAA) | Date | `EN DATE DU …………` |
| `${bien.livre_foncier_ville}` | Ville du livre foncier | Texte | `DU LIVRE FONCIER DE …………` |
| `${bien.tf_volume}` | Volume du livre foncier | Texte | `VOLUME …………` |
| `${bien.tf_folio}` | Folio | Texte | `FOLIO …………` |
| `${bien.tf_annee}` | Année du TF | Texte | `ANNÉE …………` |

> ℹ️ Pour la **vente sans titre foncier**, les balises `bien.tf_*` sont absentes du modèle — utiliser le modèle variante.

### 6.3 Limites (bail à construction obligatoire, vente optionnel)

| Balise | Description | Marqueurs |
|--------|------------|-----------|
| `${bien.limites_ne}` | Limite Nord-Est | `NORD-EST : …………`, `AU NORD-EST …………` |
| `${bien.limites_so}` | Limite Sud-Ouest | `SUD-OUEST : …………` |
| `${bien.limites_se}` | Limite Sud-Est | `SUD-EST : …………` |
| `${bien.limites_no}` | Limite Nord-Ouest | `NORD-OUEST : …………` |

### 6.4 Origine de propriété et prix

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${bien.origine_propriete}` | Comment le vendeur a acquis le bien | Texte long | `(DÉCRIRE L'ORIGINE)`, `PROVENANT DE …………` |
| `${bien.prix_vente_chiffres}` | Prix de vente en GNF | Montant | `PRIX DE VENTE EN CHIFFRE`, `… GNF` |
| `${bien.prix_vente_lettres}` | Prix en lettres majuscules | **Auto** | `PRIX DE VENTE EN LETTRE`, `(EN TOUTES LETTRES) FRANCS GUINÉENS` |

---

## 7. Bloc BANQUE / HYPOTHÈQUE

> Utilisé dans : contrat d'hypothèque, mainlevée, courriers banque.  
> Débiteur / Emprunteur = `pp.*` (voir section 3).

### 7.1 Identification de la banque

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${bq.denomination}` | Dénomination de la banque | Texte | `NOM DE LA BANQUE`, `BANQUE : …………` |
| `${bq.forme}` | Forme juridique | Texte | `SA / SAS…` |
| `${bq.siege_quartier}` | Quartier du siège | Texte | `QUARTIER …………` |
| `${bq.siege_commune}` | Commune du siège | Texte | `COMMUNE …………` |
| `${bq.siege_ville}` | Ville | Texte | `VILLE …………` |
| `${bq.representant_nom}` | Nom du représentant légal | Texte | `REPRÉSENTÉ PAR …………`, `DIRECTEUR GÉNÉRAL` |
| `${bq.representant_qualite}` | Qualité du représentant | Texte | `EN SA QUALITÉ DE …………`, `FONDÉ DE POUVOIR` |

### 7.2 Crédit et garantie

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${bq.montant_credit_chiffres}` | Montant du crédit accordé (GNF) | Montant | `MONTANT DU CRÉDIT EN CHIFFRE`, `… GNF` |
| `${bq.montant_credit_lettres}` | Montant en lettres | **Auto** | `MONTANT DU CRÉDIT EN LETTRE` |
| `${bq.taux_interet}` | Taux d'intérêt annuel (%) | Décimal | `TAUX D'INTÉRÊT : … %` |
| `${bq.duree_credit_chiffres}` | Durée du crédit (mois) | Entier | `DURÉE : … MOIS` |
| `${bq.duree_credit_lettres}` | Durée en lettres | **Auto** | `(DURÉE EN LETTRES) MOIS` |
| `${bq.type_garantie}` | Type de garantie hypothécaire | Texte | `AFFECTATION HYPOTHÉCAIRE`, `(Type de garantie)` |
| `${bq.rang_hypothecaire}` | Rang de l'hypothèque | Texte | `1ER RANG`, `(RANG À PRÉCISER)` |

> ℹ️ Le bien hypothéqué réutilise le **Bloc Bien immobilier** (`${bien.titre_foncier_numero}`, `${bien.superficie}`, etc.).

### 7.3 Mainlevée — Hypothèque originale

> Utilisé uniquement dans l'acte de **mainlevée**. Fait référence à l'acte d'hypothèque initial.

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${hypotheque.reference_acte}` | Référence de l'acte d'hypothèque original | Texte | `ACTE N° …………`, `REF : HYP-…………` |
| `${hypotheque.date_acte}` | Date de l'acte original (JJ/MM/AAAA) | Date | `EN DATE DU …………`, `REÇU LE …………` |
| `${hypotheque.notaire_acte}` | Notaire instrumentaire de l'hypothèque originale | Texte | `PAR MAÎTRE …………` |
| `${hypotheque.montant_chiffres}` | Montant garanti à l'origine (GNF) | Montant | `POUR SÛRETÉ DE … GNF` |
| `${hypotheque.montant_lettres}` | Montant en lettres | **Auto** | `(MONTANT EN LETTRES)` |
| `${hypotheque.rang}` | Rang de l'hypothèque à radier | Texte | `DU … RANG`, `1ER RANG` |

---

## 8. Bloc BAIL

> Utilisé dans : Contrat de bail d'habitation, Bail commercial, Bail à construction.  
> Bailleur = `pp.*` / Locataire ou Preneur = `loc.*` (voir sections dédiées ci-dessus).

### 8.1 Conditions communes

| Balise | Description | Type / Valeurs | Marqueurs à remplacer |
|--------|------------|----------------|-----------------------|
| `${bail.date_prise_effet}` | Date de prise d'effet (JJ/MM/AAAA) | Date | `À COMPTER DU …………`, `DATE D'ENTRÉE EN JOUISSANCE` |
| `${bail.duree_chiffres}` | Durée du bail (en années) | Entier | `DURÉE : … ANS`, `POUR UNE DURÉE DE …………` |
| `${bail.duree_lettres}` | Durée en lettres majuscules | **Auto** | `(DURÉE EN LETTRES) ANS` |
| `${bail.loyer_chiffres}` | Loyer mensuel ou redevance annuelle (GNF) | Montant | `LOYER : … GNF`, `LOYER EN CHIFFRE` |
| `${bail.loyer_lettres}` | Loyer en lettres | **Auto** | `LOYER EN LETTRE` |
| `${bail.periodicite}` | Périodicité du paiement | Énumération : `Mensuel / Trimestriel / Semestriel / Annuel` | `PAR MOIS`, `PAR TRIMESTRE`, `(PÉRIODICITÉ)` |
| `${bail.destination}` | Usage convenu du bien loué | Texte | `DESTINÉ À …………`, `USAGE : …………` |

### 8.2 Habitation & Commercial

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${bail.caution_chiffres}` | Dépôt de garantie / caution (GNF) | Montant | `CAUTION : … GNF`, `DÉPÔT DE GARANTIE …………` |
| `${bail.caution_lettres}` | Caution en lettres | **Auto** | `(CAUTION EN LETTRES)` |
| `${bail.avance_loyer}` | Nombre de mois d'avance | Entier | `… MOIS D'AVANCE`, `AVANCE SUR LOYER` |

### 8.3 Bail commercial uniquement

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${bail.droit_entree_chiffres}` | Droit d'entrée / Pas-de-porte (GNF) | Montant | `DROIT D'ENTRÉE : … GNF`, `PAS-DE-PORTE` |
| `${bail.droit_entree_lettres}` | Droit d'entrée en lettres | **Auto** | `(DROIT D'ENTRÉE EN LETTRES)` |
| `${bail.clause_renouvellement}` | Clause de renouvellement | Texte | `TACITE RECONDUCTION`, `(CLAUSE DE RENOUVELLEMENT)` |

### 8.4 Bail à construction uniquement

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${bail.engagement_construction}` | Description et délai des constructions prévues | Texte long | `(DÉCRIRE LA CONSTRUCTION PRÉVUE)`, `BÂTIMENT R+…` |
| `${bail.valeur_constructions_chiffres}` | Valeur estimée des constructions (GNF) | Montant | `VALEUR DES CONSTRUCTIONS : … GNF` |
| `${bail.valeur_constructions_lettres}` | Valeur en lettres | **Auto** | `(VALEUR EN LETTRES)` |

---

## 9. Bloc COURRIER

> Utilisé dans les 13 courriers de transmission/réquisition/accusé.

### 9.1 En-tête du courrier

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${cr.lieu}` | Lieu d'émission | Texte | `Conakry`, `LIEU D'ÉMISSION`, `…………` |
| `${cr.date_jma}` | Date du courrier (JJ/MM/AAAA) | Date | `LE …………`, `J/M/A` |
| `${cr.nref}` | Numéro de référence du courrier | Texte | `Notre Réf. : AB/N/CD/N°…/année`, `NREF : …………` |

### 9.2 Destinataire

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${cr.destinataire_civilite}` | M. / Mme / Monsieur / Madame | Texte | `M./Mme`, `MONSIEUR`, `MADAME` |
| `${cr.destinataire_nom}` | Nom du destinataire | Texte | `NOM DU DESTINATAIRE`, `…………` |
| `${cr.destinataire_fonction}` | Fonction | Texte | `Directeur Général`, `Conservateur Foncier`, `(FONCTION)` |
| `${cr.destinataire_organisation}` | Organisation / Institution | Texte | `NOM DE L'INSTITUTION`, `…………` |
| `${cr.destinataire_adresse}` | Adresse (quartier/commune/ville) | Texte | `ADRESSE DU DESTINATAIRE`, `…………` |

### 9.3 Corps du courrier

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${cr.objet}` | Objet du courrier | Texte | `OBJET : …………` |
| `${cr.dossier_intitule}` | Intitulé du dossier concerné | Texte | `DOSSIER : …………`, `RE : …………` |

### 9.4 Pièces transmises — Bloc répétable

> Utilise `${bloc_piece}…${/bloc_piece}` (cloneBlock).

| Balise | Description | Marqueurs |
|--------|------------|-----------|
| `${piece.label}` | Libellé de la pièce transmise | `- …………` (liste à puces) |
| `${piece.nombre}` | Nombre d'exemplaires | `(… EXEMPLAIRE(S))` |

### 9.5 Titres fonciers multiples (réquisition) — Bloc répétable

> Utilise `${bloc_tf}…${/bloc_tf}`.

| Balise | Description | Marqueurs |
|--------|------------|-----------|
| `${tf.numero}` | Numéro du TF | `TF N° …………` |
| `${tf.livre}` | Ville du livre foncier | `DU LIVRE DE …………` |

---

## 10. Bloc FACTURE / NOTE DE FRAIS

### 10.1 En-tête de la note

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${fac.note_numero}` | Numéro de la note (format `001/MAB/26`) | Texte | `N° …/MAB/…`, `NUMÉRO DE NOTE` |
| `${fac.note_date_jma}` | Date de la note (JJ/MM/AAAA) | Date | `DATE : …………`, `LE …………` |
| `${fac.compte_numero}` | Numéro de compte bancaire de l'office | Texte | `COMPTE N° …………` |
| `${fac.objet}` | Objet de la facture | Texte | `OBJET : …………`, `POUR : …………` |

### 10.2 Assiette

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${fac.assiette_chiffres}` | Valeur de base du calcul (prix vente / loyers / capital) | Montant | `PRIX DE VENTE`, `MONTANT DE BASE`, `… GNF` |
| `${fac.assiette_lettres}` | Assiette en lettres | **Auto** | `(ASSIETTE EN LETTRES)` |

### 10.3 Lignes — Bloc répétable

> Utilise `${bloc_ligne}…${/bloc_ligne}` (cloneRowAndSetValues dans un tableau Word).

| Balise | Description | Marqueurs |
|--------|------------|-----------|
| `${ligne.designation}` | Désignation de la ligne | `DÉSIGNATION`, `………………………` |
| `${ligne.quantite}` | Quantité | `QTE`, `1` |
| `${ligne.montant}` | Montant de la ligne en GNF | `MONTANT`, `… GNF` |

### 10.4 Total

| Balise | Description | Type | Marqueurs à remplacer |
|--------|------------|------|-----------------------|
| `${fac.total_chiffres}` | Total TTC en GNF | Montant (calculé) | `TOTAL : … GNF`, `MONTANT TOTAL EN CHIFFRE` |
| `${fac.total_lettres}` | Total en lettres majuscules | **Auto** | `MONTANT TOTAL EN LETTRE`, `(TOTAL EN TOUTES LETTRES) FRANCS GUINÉENS` |

---

## 11. Récapitulatif — Champs auto-générés (jamais saisis)

Ces champs sont **calculés automatiquement** par `ActesGeneratorService` + `NombreEnLettres` :

| Balise auto | Source |
|-------------|--------|
| `${soc.capital_lettres}` | `soc.capital_chiffres` |
| `${soc.valeur_nominale_lettres}` | `soc.valeur_nominale_chiffres` |
| `${associe.montant_apport_lettres}` | `associe.montant_apport_chiffres` |
| `${bien.prix_vente_lettres}` | `bien.prix_vente_chiffres` |
| `${bq.montant_credit_lettres}` | `bq.montant_credit_chiffres` |
| `${bail.loyer_lettres}` | `bail.loyer_chiffres` |
| `${bail.loyer_total_chiffres}` | `bail.loyer_chiffres × periodicite × duree` |
| `${bail.loyer_total_lettres}` | `bail.loyer_total_chiffres` |
| `${bail.duree_lettres}` | `bail.duree` |
| `${fac.total_chiffres}` | Somme des `ligne.montant` |
| `${fac.total_lettres}` | `fac.total_chiffres` |
| `${fac.assiette_lettres}` | `fac.assiette_chiffres` |
| `${date_acte_lettres}` | `date_acte_jma` |
| `${annee_lettres}` | Année extraite de `date_acte_jma` |

---

## 12. Mapping rapide modèle → blocs à insérer

| Modèle | Blocs requis |
|--------|-------------|
| Statuts SARL/SARLU | Office · Dossier · Société · associes[] · direction |
| Statuts SAS/SASU | Office · Dossier · Société · associes[] · president/DG/DGA |
| DNSV | Office · Dossier · Société · associes[] (parts + apports) |
| Page de garde société | Office · Société (denomination, forme, capital, siège) |
| Attestation de dépôt | Office · Dossier · Société (denomination, capital) |
| Déclaration sur l'honneur | Office · Dossier · Personne physique (gérant) |
| RCCM (formulaire) | Société (siège, forme, capital, durée, activités) + direction |
| Insertion JAL | Société complète + RCCM + tribunal |
| Contrat de vente (avec TF) | Office · Dossier · pp (vendeur) · pp (acquéreur) · Bien (avec TF) |
| Contrat de vente (sans TF) | Office · Dossier · pp (vendeur) · pp (acquéreur) · Bien (sans TF) |
| Page de garde vente | Office · Bien (TF) · pp (vendeur + acquéreur) |
| Tableau de bordereau | Office · Bien · pp (vendeur + acquéreur) · fac (prix, taux) |
| Contrat de bail habitation/pro | Office · Dossier · pp (bailleur) · pp (preneur) · Bien · Bail |
| Bail à construction | Office · Dossier · pp (bailleur) · pp (preneur) · Bien (+ limites) · Bail |
| Facture (toutes) | Office · Dossier · fac (assiette, lignes[], total) |
| Courrier transmission | Office · Courrier · (Société ou Bien ou Banque selon l'objet) |

---

## 13. Procédure de normalisation d'un modèle

1. **Ouvrir** le `.docx` dans Word
2. **`Ctrl+H`** (Rechercher/Remplacer) — activer "Utiliser les caractères génériques" si nécessaire
3. **Remplacer** chaque marqueur par la balise correspondante :
   - `PRENOM ET NOM` → `${pp.prenom_nom}`
   - `J/M/A` → `${date_acte_jma}`
   - `MONTANT DU CAPITAL EN CHIFFRE` → `${soc.capital_chiffres}`
   - `MONTANT DU CAPITAL EN LETTRE` → `${soc.capital_lettres}`
   - etc.
4. **Pour les listes répétées** (associés, lignes de facture) : encadrer le bloc par `${bloc_associe}` … `${/bloc_associe}`
5. **Sauvegarder** en `.docx` (format Word 2007-365)
6. **Uploader** via `Paramètres → Modèles d'actes` dans l'interface Ayelema
7. **Tester** en créant un dossier du type correspondant → les balises doivent être remplacées automatiquement

---

*Dernière mise à jour : 01/07/2026 — Ajout blocs répétables (`associes`, `gerants`, `administrateurs`, `actionnaires`, `membres`), préfixes `acq.*`, `loc.*`, champs SA (`capital_libere`, `nombre_actions`, PCA/DG), dissolution/liquidateur. Total blocs : 15.*
