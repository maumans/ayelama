# 02 — Variables réellement disponibles, questionnaire par questionnaire

> **Fichier généré automatiquement** depuis `resources/js/data/questionnaires.js`, la source
> de vérité du code. Toute balise absente de ce document **ne sera pas remplie** : elle sera
> effacée du document produit et signalée dans l'historique du dossier.

Pour chaque champ : la balise à écrire dans le Word, son libellé à l'écran, son type, et sa
section dans le questionnaire. La colonne **Dérivées** indique les variantes automatiquement
disponibles en plus (voir `01-MOTEUR-DE-GENERATION.md`).

## Table des matières

- [creation_sarlu](#creation-sarlu) — SOC-SARLU
- [creation_sarl](#creation-sarl) — SOC-SARL
- [creation_sa](#creation-sa) — SOC-SA
- [creation_sas](#creation-sas) — SOC-SAS
- [creation_sasu](#creation-sasu) — SOC-SASU
- [creation_snc](#creation-snc) — SOC-SNC
- [creation_gie](#creation-gie) — SOC-GIE
- [dissolution](#dissolution) — SOC-DIS
- [vente_immeuble](#vente-immeuble) — VTE-IMM
- [vente_sans_titre](#vente-sans-titre) — VTE-SAN
- [bail_habitation](#bail-habitation) — BAI-HAB
- [bail_commercial](#bail-commercial) — BAI-COM
- [bail_construction](#bail-construction) — BAI-CON
- [hypotheque_conv](#hypotheque-conv) — HYP-CON
- [mainlevee](#mainlevee) — HYP-MAI
- [modification](#modification) — SOC-MOD

---

## creation_sarlu

**Type(s) d'acte** : SOC-SARLU — **59 champs**

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Société** | | | | |
| `${soc.denomination}` | Dénomination sociale | text | oui |  |
| `${soc.sigle}` | Sigle (facultatif) | text |  |  |
| `${soc.capital_chiffres}` | Capital social (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.nombre_parts}` | Nombre de parts sociales | number | oui |  |
| `${soc.valeur_nominale_chiffres}` | Valeur nominale d'une part (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.siege_ville}` | Ville du siège social | text | oui |  |
| `${soc.siege_commune}` | Commune du siège social | text | oui |  |
| `${soc.siege_quartier}` | Quartier du siège social | text | oui |  |
| `${soc.objet_social}` | Objet social | textarea | oui |  |
| `${soc.duree}` | Durée (années) | number |  |  |
| `${soc.premier_exercice_annee}` | 1er exercice — année | year |  |  |
| `${soc.email_societe}` | Email de la société | text |  |  |
| `${soc.telephone_societe}` | Téléphone de la société | tel |  |  |
| `${soc.regime_fiscal_faveur}` | Bénéficie d'un régime fiscal de faveur | checkbox |  |  |
| `${soc.regime_fiscal_reference}` | Référence du décret/arrêté d'agrément | text |  |  |
| **▸ Associé unique** | | | | |
| `${pp.civilite}` | Civilité | select (3) | oui |  |
| `${pp.prenom_nom}` | Nom et prénoms | text | oui |  |
| `${pp.ne_a}` | Né(e) à | text | oui |  |
| `${pp.date_naissance}` | Date de naissance | date | oui | `_jma` `_lettres` |
| `${pp.nationalite}` | Nationalité | text |  |  |
| `${pp.situation_matrimoniale}` | Situation matrimoniale | select (4) |  |  |
| `${pp.regime_matrimonial}` | Régime matrimonial | select (4) |  |  |
| `${pp.demeurant_ville}` | Ville (résidence) | text | oui |  |
| `${pp.commune}` | Commune (résidence) | text | oui |  |
| `${pp.quartier}` | Quartier (résidence) | text | oui |  |
| `${pp.pays}` | Pays de résidence | text |  |  |
| `${pp.piece_type}` | Type de pièce d'identité | text | oui |  |
| `${pp.piece_numero}` | Numéro de pièce | text | oui |  |
| `${pp.piece_delivree_le}` | Pièce délivrée le | date | oui | `_jma` `_lettres` |
| `${pp.piece_delivree_a}` | Délivrée à | text | oui |  |
| `${pp.piece_expire_le}` | Expire le | date |  | `_jma` `_lettres` |
| `${pp.telephone}` | Téléphone | tel |  |  |
| `${pp.email}` | Email | email |  |  |
| **▸ Gérant** | | | | |
| `${ger.est_different}` | Le gérant est une personne différente de l'associé unique | checkbox |  |  |
| `${ger.civilite}` | Civilité du gérant | select (3) |  |  |
| `${ger.prenom_nom}` | Nom et prénoms | text |  |  |
| `${ger.ne_a}` | Né(e) à | text |  |  |
| `${ger.date_naissance}` | Date de naissance | date |  | `_jma` `_lettres` |
| `${ger.nationalite}` | Nationalité | text |  |  |
| `${ger.situation_matrimoniale}` | Situation matrimoniale | select (4) |  |  |
| `${ger.demeurant_ville}` | Ville (résidence) | text |  |  |
| `${ger.commune}` | Commune (résidence) | text |  |  |
| `${ger.quartier}` | Quartier (résidence) | text |  |  |
| `${ger.pays}` | Pays de résidence | text |  |  |
| `${ger.piece_type}` | Type de pièce d'identité | text |  |  |
| `${ger.piece_numero}` | Numéro de pièce | text |  |  |
| `${ger.piece_delivree_le}` | Pièce délivrée le | date |  | `_jma` `_lettres` |
| `${ger.piece_delivree_a}` | Délivrée à | text |  |  |
| `${ger.piece_expire_le}` | Expire le | date |  | `_jma` `_lettres` |
| `${ger.telephone}` | Téléphone | tel |  |  |
| `${ger.email}` | Email | email |  |  |
| **▸ Commissaire aux comptes titulaire** | | | | |
| `${cac_titulaire.civilite}` | Civilité / Type | select (3) |  |  |
| `${cac_titulaire.prenom_nom}` | Nom du cabinet ou expert | text |  |  |
| `${cac_titulaire.agrement}` | N° d'agrément | text |  |  |
| `${cac_titulaire.adresse}` | Adresse complète | text |  |  |
| **▸ Commissaire aux comptes suppléant** | | | | |
| `${cac_suppleant.civilite}` | Civilité / Type | select (3) |  |  |
| `${cac_suppleant.prenom_nom}` | Nom du cabinet ou expert | text |  |  |
| `${cac_suppleant.agrement}` | N° d'agrément | text |  |  |
| `${cac_suppleant.adresse}` | Adresse complète | text |  |  |

---

## creation_sarl

**Type(s) d'acte** : SOC-SARL — **23 champs** + 2 bloc(s) répétable(s)

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Société** | | | | |
| `${soc.denomination}` | Dénomination sociale | text | oui |  |
| `${soc.sigle}` | Sigle (facultatif) | text |  |  |
| `${soc.capital_chiffres}` | Capital social (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.nombre_parts}` | Nombre de parts sociales | number | oui |  |
| `${soc.valeur_nominale_chiffres}` | Valeur nominale d'une part (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.siege_ville}` | Ville du siège social | text | oui |  |
| `${soc.siege_commune}` | Commune du siège social | text | oui |  |
| `${soc.siege_quartier}` | Quartier du siège social | text | oui |  |
| `${soc.objet_social}` | Objet social | textarea | oui |  |
| `${soc.duree}` | Durée (années) | number |  |  |
| `${soc.premier_exercice_annee}` | 1er exercice — année | year |  |  |
| `${soc.email_societe}` | Email de la société | text |  |  |
| `${soc.telephone_societe}` | Téléphone de la société | tel |  |  |
| `${soc.regime_fiscal_faveur}` | Bénéficie d'un régime fiscal de faveur | checkbox |  |  |
| `${soc.regime_fiscal_reference}` | Référence du décret/arrêté d'agrément | text |  |  |
| **▸ Commissaire aux comptes titulaire** | | | | |
| `${cac_titulaire.civilite}` | Civilité / Type | select (3) |  |  |
| `${cac_titulaire.prenom_nom}` | Nom du cabinet ou expert | text |  |  |
| `${cac_titulaire.agrement}` | N° d'agrément | text |  |  |
| `${cac_titulaire.adresse}` | Adresse complète | text |  |  |
| **▸ Commissaire aux comptes suppléant** | | | | |
| `${cac_suppleant.civilite}` | Civilité / Type | select (3) |  |  |
| `${cac_suppleant.prenom_nom}` | Nom du cabinet ou expert | text |  |  |
| `${cac_suppleant.agrement}` | N° d'agrément | text |  |  |
| `${cac_suppleant.adresse}` | Adresse complète | text |  |  |

### Bloc répétable `associes` — Associés

```
${associes}
   … texte répété, utilisant ${associes.civilite} …
${/associes}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${associes.civilite}` | Civilité | select |  |
| `${associes.nom}` | Nom et prénoms / Dénomination | text |  |
| `${associes.type_personne}` | Type | select |  |
| `${associes.parts_chiffres}` | Nombre de parts | number | `_lettres` `_formate` |
| `${associes.forme}` | Forme juridique | text |  |
| `${associes.rccm}` | Numéro RCCM | text |  |
| `${associes.representant_legal}` | Représentant légal | text |  |
| `${associes.ne_a}` | Né(e) à | text |  |
| `${associes.date_naissance}` | Date de naissance | date | `_jma` `_lettres` |
| `${associes.nationalite}` | Nationalité / Pays | text |  |
| `${associes.situation_matrimoniale}` | Situation matrimoniale | select |  |
| `${associes.regime_matrimonial}` | Régime matrimonial | select |  |
| `${associes.demeurant_ville}` | Ville (résidence) | text |  |
| `${associes.commune}` | Commune (résidence) | text |  |
| `${associes.quartier}` | Quartier (résidence) | text |  |
| `${associes.pays}` | Pays de résidence | text |  |
| `${associes.piece_type}` | Type de pièce d'identité | text |  |
| `${associes.cni}` | N° pièce d'identité | text |  |
| `${associes.piece_delivree_le}` | Pièce délivrée le | date | `_jma` `_lettres` |
| `${associes.piece_delivree_a}` | Délivrée à | text |  |
| `${associes.piece_expire_le}` | Expire le | date | `_jma` `_lettres` |

### Bloc répétable `gerants` — Gérant(s)

```
${gerants}
   … texte répété, utilisant ${gerants.civilite} …
${/gerants}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${gerants.civilite}` | Civilité | select |  |
| `${gerants.prenom_nom}` | Nom et prénoms | text |  |
| `${gerants.ne_a}` | Né(e) à | text |  |
| `${gerants.date_naissance}` | Date de naissance | date | `_jma` `_lettres` |
| `${gerants.nationalite}` | Nationalité | text |  |
| `${gerants.situation_matrimoniale}` | Situation matrimoniale | select |  |
| `${gerants.regime_matrimonial}` | Régime matrimonial | select |  |
| `${gerants.demeurant_ville}` | Ville (résidence) | text |  |
| `${gerants.commune}` | Commune (résidence) | text |  |
| `${gerants.quartier}` | Quartier (résidence) | text |  |
| `${gerants.pays}` | Pays de résidence | text |  |
| `${gerants.piece_type}` | Type de pièce d'identité | text |  |
| `${gerants.piece_numero}` | N° pièce d'identité | text |  |
| `${gerants.piece_delivree_le}` | Pièce délivrée le | date | `_jma` `_lettres` |
| `${gerants.piece_delivree_a}` | Délivrée à | text |  |
| `${gerants.piece_expire_le}` | Expire le | date | `_jma` `_lettres` |

---

## creation_sa

**Type(s) d'acte** : SOC-SA — **27 champs** + 2 bloc(s) répétable(s)

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Société** | | | | |
| `${soc.denomination}` | Dénomination sociale | text | oui |  |
| `${soc.sigle}` | Sigle (facultatif) | text |  |  |
| `${soc.capital_chiffres}` | Capital social (GNF — min. 140 000 000) | number | oui | `_lettres` `_formate` |
| `${soc.capital_libere_chiffres}` | Capital libéré à la constitution (min. 35 000 000) | number | oui | `_lettres` `_formate` |
| `${soc.nombre_actions}` | Nombre d'actions | number | oui |  |
| `${soc.valeur_nominale_chiffres}` | Valeur nominale d'une action (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.siege_ville}` | Ville du siège social | text | oui |  |
| `${soc.siege_commune}` | Commune du siège social | text | oui |  |
| `${soc.siege_quartier}` | Quartier du siège social | text | oui |  |
| `${soc.objet_social}` | Objet social | textarea | oui |  |
| `${soc.duree}` | Durée (années) | number |  |  |
| `${soc.premier_exercice_annee}` | 1er exercice — année | year |  |  |
| `${soc.email_societe}` | Email de la société | text |  |  |
| `${soc.telephone_societe}` | Téléphone de la société | tel |  |  |
| **▸ Direction** | | | | |
| `${soc.pca_nom}` | Président du Conseil d'Administration (PCA) | text | oui |  |
| `${soc.pca_civilite}` | Civilité PCA | select (2) | oui |  |
| `${soc.pca_adresse}` | Adresse du PCA | text |  |  |
| `${soc.dg_nom}` | Directeur Général (DG) | text |  |  |
| `${soc.dg_civilite}` | Civilité DG | select (2) |  |  |
| **▸ Commissaire aux comptes titulaire** | | | | |
| `${cac_titulaire.civilite}` | Civilité / Type | select (3) | oui |  |
| `${cac_titulaire.prenom_nom}` | Nom du cabinet ou expert | text | oui |  |
| `${cac_titulaire.agrement}` | N° d'agrément | text | oui |  |
| `${cac_titulaire.adresse}` | Adresse complète | text | oui |  |
| **▸ Commissaire aux comptes suppléant** | | | | |
| `${cac_suppleant.civilite}` | Civilité / Type | select (3) |  |  |
| `${cac_suppleant.prenom_nom}` | Nom du cabinet ou expert | text |  |  |
| `${cac_suppleant.agrement}` | N° d'agrément | text |  |  |
| `${cac_suppleant.adresse}` | Adresse complète | text |  |  |

### Bloc répétable `actionnaires` — Actionnaires

```
${actionnaires}
   … texte répété, utilisant ${actionnaires.nom} …
${/actionnaires}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${actionnaires.nom}` | Nom / Dénomination | text |  |
| `${actionnaires.type_personne}` | Type | select |  |
| `${actionnaires.actions_chiffres}` | Nombre d'actions | number | `_lettres` `_formate` |
| `${actionnaires.nationalite}` | Nationalité / Pays | text |  |

### Bloc répétable `administrateurs` — Membres du Conseil d'Administration

```
${administrateurs}
   … texte répété, utilisant ${administrateurs.prenom_nom} …
${/administrateurs}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${administrateurs.prenom_nom}` | Nom et prénoms | text |  |
| `${administrateurs.nationalite}` | Nationalité | text |  |
| `${administrateurs.domicile}` | Domicile | text |  |
| `${administrateurs.fonction}` | Fonction au CA | text |  |

---

## creation_sas

**Type(s) d'acte** : SOC-SAS — **31 champs** + 1 bloc(s) répétable(s)

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Société** | | | | |
| `${soc.denomination}` | Dénomination sociale | text | oui |  |
| `${soc.sigle}` | Sigle (facultatif) | text |  |  |
| `${soc.capital_chiffres}` | Capital social (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.nombre_parts}` | Nombre de parts sociales | number | oui |  |
| `${soc.valeur_nominale_chiffres}` | Valeur nominale d'une part (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.siege_ville}` | Ville du siège social | text | oui |  |
| `${soc.siege_commune}` | Commune du siège social | text | oui |  |
| `${soc.siege_quartier}` | Quartier du siège social | text | oui |  |
| `${soc.objet_social}` | Objet social | textarea | oui |  |
| `${soc.duree}` | Durée (années) | number |  |  |
| `${soc.premier_exercice_annee}` | 1er exercice — année | year |  |  |
| `${soc.email_societe}` | Email de la société | text |  |  |
| `${soc.telephone_societe}` | Téléphone de la société | tel |  |  |
| `${soc.regime_fiscal_faveur}` | Bénéficie d'un régime fiscal de faveur | checkbox |  |  |
| `${soc.regime_fiscal_reference}` | Référence du décret/arrêté d'agrément | text |  |  |
| **▸ Président** | | | | |
| `${soc.president_nom}` | Président de la SAS | text | oui |  |
| `${soc.president_civilite}` | Civilité | select (3) | oui |  |
| `${soc.president_ne_a}` | Né(e) à | text |  |  |
| `${soc.president_date_naissance}` | Date de naissance | date |  | `_jma` `_lettres` |
| `${soc.president_nationalite}` | Nationalité | text |  |  |
| `${soc.president_adresse}` | Adresse | text |  |  |
| `${soc.president_piece_numero}` | N° pièce d'identité | text |  |  |
| **▸ Direction** | | | | |
| `${soc.dg_nom}` | Directeur Général (facultatif) | text |  |  |
| **▸ Commissaire aux comptes titulaire** | | | | |
| `${cac_titulaire.civilite}` | Civilité / Type | select (3) |  |  |
| `${cac_titulaire.prenom_nom}` | Nom du cabinet ou expert | text |  |  |
| `${cac_titulaire.agrement}` | N° d'agrément | text |  |  |
| `${cac_titulaire.adresse}` | Adresse complète | text |  |  |
| **▸ Commissaire aux comptes suppléant** | | | | |
| `${cac_suppleant.civilite}` | Civilité / Type | select (3) |  |  |
| `${cac_suppleant.prenom_nom}` | Nom du cabinet ou expert | text |  |  |
| `${cac_suppleant.agrement}` | N° d'agrément | text |  |  |
| `${cac_suppleant.adresse}` | Adresse complète | text |  |  |

### Bloc répétable `associes` — Associés

```
${associes}
   … texte répété, utilisant ${associes.civilite} …
${/associes}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${associes.civilite}` | Civilité | select |  |
| `${associes.nom}` | Nom et prénoms / Dénomination | text |  |
| `${associes.type_personne}` | Type | select |  |
| `${associes.parts_chiffres}` | Nombre de parts | number | `_lettres` `_formate` |
| `${associes.forme}` | Forme juridique | text |  |
| `${associes.rccm}` | Numéro RCCM | text |  |
| `${associes.representant_legal}` | Représentant légal | text |  |
| `${associes.ne_a}` | Né(e) à | text |  |
| `${associes.date_naissance}` | Date de naissance | date | `_jma` `_lettres` |
| `${associes.nationalite}` | Nationalité / Pays | text |  |
| `${associes.situation_matrimoniale}` | Situation matrimoniale | select |  |
| `${associes.regime_matrimonial}` | Régime matrimonial | select |  |
| `${associes.demeurant_ville}` | Ville (résidence) | text |  |
| `${associes.commune}` | Commune (résidence) | text |  |
| `${associes.quartier}` | Quartier (résidence) | text |  |
| `${associes.pays}` | Pays de résidence | text |  |
| `${associes.piece_type}` | Type de pièce d'identité | text |  |
| `${associes.cni}` | N° pièce d'identité | text |  |
| `${associes.piece_delivree_le}` | Pièce délivrée le | date | `_jma` `_lettres` |
| `${associes.piece_delivree_a}` | Délivrée à | text |  |
| `${associes.piece_expire_le}` | Expire le | date | `_jma` `_lettres` |

---

## creation_sasu

**Type(s) d'acte** : SOC-SASU — **46 champs**

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Société** | | | | |
| `${soc.denomination}` | Dénomination sociale | text | oui |  |
| `${soc.sigle}` | Sigle (facultatif) | text |  |  |
| `${soc.capital_chiffres}` | Capital social (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.nombre_parts}` | Nombre de parts sociales | number | oui |  |
| `${soc.valeur_nominale_chiffres}` | Valeur nominale d'une part (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.siege_ville}` | Ville du siège social | text | oui |  |
| `${soc.siege_commune}` | Commune du siège social | text | oui |  |
| `${soc.siege_quartier}` | Quartier du siège social | text | oui |  |
| `${soc.objet_social}` | Objet social | textarea | oui |  |
| `${soc.duree}` | Durée (années) | number |  |  |
| `${soc.premier_exercice_annee}` | 1er exercice — année | year |  |  |
| `${soc.email_societe}` | Email de la société | text |  |  |
| `${soc.telephone_societe}` | Téléphone de la société | tel |  |  |
| `${soc.regime_fiscal_faveur}` | Bénéficie d'un régime fiscal de faveur | checkbox |  |  |
| `${soc.regime_fiscal_reference}` | Référence du décret/arrêté d'agrément | text |  |  |
| **▸ Associé unique** | | | | |
| `${pp.civilite}` | Civilité | select (3) | oui |  |
| `${pp.prenom_nom}` | Nom et prénoms | text | oui |  |
| `${pp.ne_a}` | Né(e) à | text | oui |  |
| `${pp.date_naissance}` | Date de naissance | date | oui | `_jma` `_lettres` |
| `${pp.nationalite}` | Nationalité | text |  |  |
| `${pp.situation_matrimoniale}` | Situation matrimoniale | select (4) |  |  |
| `${pp.regime_matrimonial}` | Régime matrimonial | select (4) |  |  |
| `${pp.demeurant_ville}` | Ville (résidence) | text | oui |  |
| `${pp.commune}` | Commune (résidence) | text | oui |  |
| `${pp.quartier}` | Quartier (résidence) | text | oui |  |
| `${pp.pays}` | Pays de résidence | text |  |  |
| `${pp.piece_type}` | Type de pièce d'identité | text | oui |  |
| `${pp.piece_numero}` | Numéro de pièce | text | oui |  |
| `${pp.piece_delivree_le}` | Pièce délivrée le | date | oui | `_jma` `_lettres` |
| `${pp.piece_delivree_a}` | Délivrée à | text | oui |  |
| `${pp.piece_expire_le}` | Expire le | date |  | `_jma` `_lettres` |
| `${pp.telephone}` | Téléphone | tel |  |  |
| `${pp.email}` | Email | email |  |  |
| **▸ Président** | | | | |
| `${soc.president_est_different}` | Le président est une personne différente de l'associé unique | checkbox |  |  |
| `${soc.president_civilite}` | Civilité | select (3) |  |  |
| `${soc.president_nom}` | Nom et prénoms du président | text |  |  |
| `${soc.president_adresse}` | Adresse | text |  |  |
| `${soc.president_piece_numero}` | N° pièce d'identité | text |  |  |
| **▸ Commissaire aux comptes titulaire** | | | | |
| `${cac_titulaire.civilite}` | Civilité / Type | select (3) |  |  |
| `${cac_titulaire.prenom_nom}` | Nom du cabinet ou expert | text |  |  |
| `${cac_titulaire.agrement}` | N° d'agrément | text |  |  |
| `${cac_titulaire.adresse}` | Adresse complète | text |  |  |
| **▸ Commissaire aux comptes suppléant** | | | | |
| `${cac_suppleant.civilite}` | Civilité / Type | select (3) |  |  |
| `${cac_suppleant.prenom_nom}` | Nom du cabinet ou expert | text |  |  |
| `${cac_suppleant.agrement}` | N° d'agrément | text |  |  |
| `${cac_suppleant.adresse}` | Adresse complète | text |  |  |

---

## creation_snc

**Type(s) d'acte** : SOC-SNC — **15 champs** + 2 bloc(s) répétable(s)

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Société** | | | | |
| `${soc.denomination}` | Dénomination sociale | text | oui |  |
| `${soc.sigle}` | Sigle (facultatif) | text |  |  |
| `${soc.capital_chiffres}` | Capital social (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.nombre_parts}` | Nombre de parts sociales | number | oui |  |
| `${soc.valeur_nominale_chiffres}` | Valeur nominale d'une part (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.siege_ville}` | Ville du siège social | text | oui |  |
| `${soc.siege_commune}` | Commune du siège social | text | oui |  |
| `${soc.siege_quartier}` | Quartier du siège social | text | oui |  |
| `${soc.objet_social}` | Objet social | textarea | oui |  |
| `${soc.duree}` | Durée (années) | number |  |  |
| `${soc.premier_exercice_annee}` | 1er exercice — année | year |  |  |
| `${soc.email_societe}` | Email de la société | text |  |  |
| `${soc.telephone_societe}` | Téléphone de la société | tel |  |  |
| `${soc.regime_fiscal_faveur}` | Bénéficie d'un régime fiscal de faveur | checkbox |  |  |
| `${soc.regime_fiscal_reference}` | Référence du décret/arrêté d'agrément | text |  |  |

### Bloc répétable `associes` — Associés (responsabilité illimitée)

```
${associes}
   … texte répété, utilisant ${associes.nom} …
${/associes}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${associes.nom}` | Nom et prénoms | text |  |
| `${associes.apport_chiffres}` | Apport (GNF) | number | `_lettres` `_formate` |
| `${associes.nationalite}` | Nationalité | text |  |
| `${associes.adresse}` | Adresse | text |  |
| `${associes.cni}` | N° pièce d'identité | text |  |

### Bloc répétable `gerants` — Gérant(s)

```
${gerants}
   … texte répété, utilisant ${gerants.civilite} …
${/gerants}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${gerants.civilite}` | Civilité | select |  |
| `${gerants.prenom_nom}` | Nom et prénoms | text |  |
| `${gerants.ne_a}` | Né(e) à | text |  |
| `${gerants.date_naissance}` | Date de naissance | date | `_jma` `_lettres` |
| `${gerants.nationalite}` | Nationalité | text |  |
| `${gerants.situation_matrimoniale}` | Situation matrimoniale | select |  |
| `${gerants.regime_matrimonial}` | Régime matrimonial | select |  |
| `${gerants.demeurant_ville}` | Ville (résidence) | text |  |
| `${gerants.commune}` | Commune (résidence) | text |  |
| `${gerants.quartier}` | Quartier (résidence) | text |  |
| `${gerants.pays}` | Pays de résidence | text |  |
| `${gerants.piece_type}` | Type de pièce d'identité | text |  |
| `${gerants.piece_numero}` | N° pièce d'identité | text |  |
| `${gerants.piece_delivree_le}` | Pièce délivrée le | date | `_jma` `_lettres` |
| `${gerants.piece_delivree_a}` | Délivrée à | text |  |
| `${gerants.piece_expire_le}` | Expire le | date | `_jma` `_lettres` |

---

## creation_gie

**Type(s) d'acte** : SOC-GIE — **15 champs** + 2 bloc(s) répétable(s)

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Groupement** | | | | |
| `${soc.denomination}` | Dénomination du groupement | text | oui |  |
| `${soc.capital_chiffres}` | Capital (GNF — facultatif) | number |  | `_lettres` `_formate` |
| `${soc.siege_ville}` | Ville du siège | text | oui |  |
| `${soc.siege_commune}` | Commune du siège | text | oui |  |
| `${soc.siege_quartier}` | Quartier du siège | text | oui |  |
| `${soc.objet_social}` | Objet du groupement | textarea | oui |  |
| `${soc.duree}` | Durée (années) | number |  |  |
| **▸ Commissaire aux comptes titulaire** | | | | |
| `${cac_titulaire.civilite}` | Civilité / Type | select (3) |  |  |
| `${cac_titulaire.prenom_nom}` | Nom du cabinet ou expert | text |  |  |
| `${cac_titulaire.agrement}` | N° d'agrément | text |  |  |
| `${cac_titulaire.adresse}` | Adresse complète | text |  |  |
| **▸ Commissaire aux comptes suppléant** | | | | |
| `${cac_suppleant.civilite}` | Civilité / Type | select (3) |  |  |
| `${cac_suppleant.prenom_nom}` | Nom du cabinet ou expert | text |  |  |
| `${cac_suppleant.agrement}` | N° d'agrément | text |  |  |
| `${cac_suppleant.adresse}` | Adresse complète | text |  |  |

### Bloc répétable `membres` — Membres

```
${membres}
   … texte répété, utilisant ${membres.nom} …
${/membres}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${membres.nom}` | Nom / Dénomination | text |  |
| `${membres.type_personne}` | Type | select |  |
| `${membres.apport_chiffres}` | Apport (GNF) | number | `_lettres` `_formate` |
| `${membres.adresse}` | Adresse | text |  |

### Bloc répétable `administrateurs` — Administrateur(s)

```
${administrateurs}
   … texte répété, utilisant ${administrateurs.prenom_nom} …
${/administrateurs}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${administrateurs.prenom_nom}` | Nom et prénoms | text |  |
| `${administrateurs.fonction}` | Fonction | text |  |
| `${administrateurs.adresse}` | Adresse | text |  |

---

## dissolution

**Type(s) d'acte** : SOC-DIS — **13 champs**

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Société dissoute** | | | | |
| `${soc.denomination}` | Dénomination de la société dissoute | text | oui |  |
| `${soc.forme}` | Forme juridique | select (9) | oui |  |
| `${soc.rccm}` | Numéro RCCM | text | oui |  |
| `${soc.capital_chiffres}` | Capital social (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.siege_ville}` | Ville du siège | text | oui |  |
| `${soc.siege_commune}` | Commune du siège | text | oui |  |
| `${soc.siege_quartier}` | Quartier du siège | text | oui |  |
| **▸ Décision de dissolution** | | | | |
| `${dissolution.date_assemblee}` | Date de l'assemblée de dissolution | date | oui | `_jma` `_lettres` |
| `${dissolution.raison}` | Raison de dissolution | textarea | oui |  |
| `${dissolution.type}` | Type de dissolution | select (2) | oui |  |
| **▸ Liquidateur** | | | | |
| `${liquidateur.nom}` | Nom du liquidateur | text | oui |  |
| `${liquidateur.qualite}` | Qualité du liquidateur | text | oui |  |
| `${liquidateur.adresse}` | Adresse du liquidateur | text |  |  |

---

## vente_immeuble

**Type(s) d'acte** : VTE-IMM — **53 champs**

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Vendeur** | | | | |
| `${pp.civilite}` | Civilité du vendeur | select (4) | oui |  |
| `${pp.prenom_nom}` | Nom et prénoms du vendeur | text | oui |  |
| `${pp.ne_a}` | Né(e) à | text |  |  |
| `${pp.date_naissance}` | Date de naissance | date |  | `_jma` `_lettres` |
| `${pp.nationalite}` | Nationalité | text |  |  |
| `${pp.situation_matrimoniale}` | Situation matrimoniale | select (4) |  |  |
| `${pp.regime_matrimonial}` | Régime matrimonial | select (4) |  |  |
| `${pp.demeurant_ville}` | Ville (résidence) | text | oui |  |
| `${pp.commune}` | Commune (résidence) | text | oui |  |
| `${pp.quartier}` | Quartier (résidence) | text | oui |  |
| `${pp.pays}` | Pays de résidence | text |  |  |
| `${pp.piece_type}` | Type de pièce | text | oui |  |
| `${pp.piece_numero}` | Numéro de pièce | text | oui |  |
| `${pp.piece_delivree_le}` | Pièce délivrée le | date |  | `_jma` `_lettres` |
| `${pp.piece_delivree_a}` | Délivrée à | text |  |  |
| `${pp.piece_expire_le}` | Expire le | date |  | `_jma` `_lettres` |
| `${pp.telephone}` | Téléphone vendeur | tel |  |  |
| `${pp.email}` | Email vendeur | email |  |  |
| **▸ Acquéreur** | | | | |
| `${acq.civilite}` | Civilité de l'acquéreur | select (4) | oui |  |
| `${acq.prenom_nom}` | Nom et prénoms de l'acquéreur | text | oui |  |
| `${acq.ne_a}` | Né(e) à | text |  |  |
| `${acq.date_naissance}` | Date de naissance | date |  | `_jma` `_lettres` |
| `${acq.nationalite}` | Nationalité | text |  |  |
| `${acq.situation_matrimoniale}` | Situation matrimoniale | select (4) |  |  |
| `${acq.regime_matrimonial}` | Régime matrimonial | select (4) |  |  |
| `${acq.demeurant_ville}` | Ville (résidence) | text | oui |  |
| `${acq.commune}` | Commune (résidence) | text | oui |  |
| `${acq.quartier}` | Quartier (résidence) | text | oui |  |
| `${acq.pays}` | Pays de résidence | text |  |  |
| `${acq.piece_type}` | Type de pièce | text | oui |  |
| `${acq.piece_numero}` | Numéro de pièce | text | oui |  |
| `${acq.piece_delivree_le}` | Pièce délivrée le | date |  | `_jma` `_lettres` |
| `${acq.piece_delivree_a}` | Délivrée à | text |  |  |
| `${acq.piece_expire_le}` | Expire le | date |  | `_jma` `_lettres` |
| `${acq.telephone}` | Téléphone acquéreur | tel |  |  |
| `${acq.email}` | Email acquéreur | email |  |  |
| **▸ Bien immobilier** | | | | |
| `${bien.parcelle_numero}` | Numéro de parcelle | text |  |  |
| `${bien.lot}` | Lot | text |  |  |
| `${bien.lieu_de}` | Situé à | text | oui |  |
| `${bien.nature_terrain}` | Nature du terrain | text | oui |  |
| `${bien.usage}` | Usage | select (5) | oui |  |
| `${bien.superficie}` | Superficie (m²) | number | oui |  |
| `${bien.pcp}` | PCP (Plan Cadastral Parcellaire) | text |  |  |
| `${bien.titre_foncier_numero}` | Numéro du titre foncier | text | oui |  |
| `${bien.livre_foncier_ville}` | Ville du livre foncier | text | oui |  |
| `${bien.limite_nord}` | Limite Nord | text |  |  |
| `${bien.limite_sud}` | Limite Sud | text |  |  |
| `${bien.limite_est}` | Limite Est | text |  |  |
| `${bien.limite_ouest}` | Limite Ouest | text |  |  |
| `${bien.origine_propriete}` | Origine de la propriété | textarea |  |  |
| **▸ Transaction** | | | | |
| `${bien.prix_vente_chiffres}` | Prix de vente (GNF) | number | oui | `_lettres` `_formate` |
| `${transaction.taxe_plusvalue_chiffres}` | Taxe de plus-value (GNF) | number |  | `_lettres` `_formate` |
| `${transaction.provision_chiffres}` | Provision réclamée (GNF) | number |  | `_lettres` `_formate` |

---

## vente_sans_titre

**Type(s) d'acte** : VTE-SAN — **47 champs**

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Vendeur** | | | | |
| `${pp.civilite}` | Civilité du vendeur | select (4) | oui |  |
| `${pp.prenom_nom}` | Nom et prénoms du vendeur | text | oui |  |
| `${pp.ne_a}` | Né(e) à | text |  |  |
| `${pp.date_naissance}` | Date de naissance | date |  | `_jma` `_lettres` |
| `${pp.nationalite}` | Nationalité | text |  |  |
| `${pp.situation_matrimoniale}` | Situation matrimoniale | select (4) |  |  |
| `${pp.regime_matrimonial}` | Régime matrimonial | select (4) |  |  |
| `${pp.demeurant_ville}` | Ville (résidence) | text | oui |  |
| `${pp.commune}` | Commune (résidence) | text | oui |  |
| `${pp.quartier}` | Quartier (résidence) | text | oui |  |
| `${pp.pays}` | Pays de résidence | text |  |  |
| `${pp.piece_type}` | Type de pièce | text | oui |  |
| `${pp.piece_numero}` | Numéro de pièce | text | oui |  |
| `${pp.piece_delivree_le}` | Pièce délivrée le | date |  | `_jma` `_lettres` |
| `${pp.piece_delivree_a}` | Délivrée à | text |  |  |
| `${pp.piece_expire_le}` | Expire le | date |  | `_jma` `_lettres` |
| `${pp.telephone}` | Téléphone vendeur | tel |  |  |
| `${pp.email}` | Email vendeur | email |  |  |
| **▸ Acquéreur** | | | | |
| `${acq.civilite}` | Civilité de l'acquéreur | select (4) | oui |  |
| `${acq.prenom_nom}` | Nom et prénoms de l'acquéreur | text | oui |  |
| `${acq.ne_a}` | Né(e) à | text |  |  |
| `${acq.date_naissance}` | Date de naissance | date |  | `_jma` `_lettres` |
| `${acq.nationalite}` | Nationalité | text |  |  |
| `${acq.situation_matrimoniale}` | Situation matrimoniale | select (4) |  |  |
| `${acq.regime_matrimonial}` | Régime matrimonial | select (4) |  |  |
| `${acq.demeurant_ville}` | Ville (résidence) | text | oui |  |
| `${acq.commune}` | Commune (résidence) | text | oui |  |
| `${acq.quartier}` | Quartier (résidence) | text | oui |  |
| `${acq.pays}` | Pays de résidence | text |  |  |
| `${acq.piece_type}` | Type de pièce | text | oui |  |
| `${acq.piece_numero}` | Numéro de pièce | text | oui |  |
| `${acq.piece_delivree_le}` | Pièce délivrée le | date |  | `_jma` `_lettres` |
| `${acq.piece_delivree_a}` | Délivrée à | text |  |  |
| `${acq.piece_expire_le}` | Expire le | date |  | `_jma` `_lettres` |
| `${acq.telephone}` | Téléphone acquéreur | tel |  |  |
| `${acq.email}` | Email acquéreur | email |  |  |
| **▸ Bien immobilier** | | | | |
| `${bien.parcelle_numero}` | Numéro de parcelle | text | oui |  |
| `${bien.lot}` | Lot | text |  |  |
| `${bien.lieu_de}` | Situé à | text | oui |  |
| `${bien.nature_terrain}` | Nature du terrain | text | oui |  |
| `${bien.usage}` | Usage | select (5) | oui |  |
| `${bien.superficie}` | Superficie (m²) | number | oui |  |
| `${bien.autorisation_occuper}` | Autorisation d'occuper / Acte de cession | text |  |  |
| `${bien.origine_propriete}` | Origine de la propriété | textarea |  |  |
| **▸ Transaction** | | | | |
| `${bien.prix_vente_chiffres}` | Prix de vente (GNF) | number | oui | `_lettres` `_formate` |
| `${transaction.taxe_plusvalue_chiffres}` | Taxe de plus-value (GNF) | number |  | `_lettres` `_formate` |
| `${transaction.provision_chiffres}` | Provision réclamée (GNF) | number |  | `_lettres` `_formate` |

---

## bail_habitation

**Type(s) d'acte** : BAI-HAB — **28 champs**

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Bailleur** | | | | |
| `${pp.civilite}` | Civilité du bailleur | select (4) | oui |  |
| `${pp.prenom_nom}` | Nom et prénoms | text | oui |  |
| `${pp.nationalite}` | Nationalité | text |  |  |
| `${pp.adresse}` | Adresse du bailleur | text | oui |  |
| `${pp.piece_type}` | Type de pièce d'identité | text | oui |  |
| `${pp.piece_numero}` | Numéro de pièce | text | oui |  |
| `${pp.telephone}` | Téléphone bailleur | tel |  |  |
| `${pp.email}` | Email bailleur | email |  |  |
| **▸ Locataire / Preneur** | | | | |
| `${loc.civilite}` | Civilité du locataire/preneur | select (5) | oui |  |
| `${loc.prenom_nom}` | Nom et prénoms / Dénomination | text | oui |  |
| `${loc.nationalite}` | Nationalité / Pays | text |  |  |
| `${loc.adresse}` | Adresse du locataire | text | oui |  |
| `${loc.piece_type}` | Type de pièce | text | oui |  |
| `${loc.piece_numero}` | Numéro de pièce | text | oui |  |
| `${loc.telephone}` | Téléphone locataire | tel |  |  |
| `${loc.email}` | Email locataire | email |  |  |
| **▸ Bien immobilier** | | | | |
| `${bien.adresse}` | Adresse du bien loué | text | oui |  |
| `${bien.description}` | Description du bien | textarea |  |  |
| `${bien.superficie}` | Superficie (m²) | number |  |  |
| `${bien.usage}` | Usage | select (2) | oui |  |
| `${bien.origine_propriete}` | Origine de la propriété du bailleur | textarea |  |  |
| **▸ Conditions du bail** | | | | |
| `${bail.date_prise_effet}` | Date de prise d'effet | date | oui | `_jma` `_lettres` |
| `${bail.duree_chiffres}` | Durée du bail (années) | number | oui | `_lettres` `_formate` |
| `${bail.loyer_chiffres}` | Loyer mensuel (GNF) | number | oui | `_lettres` `_formate` |
| `${bail.periodicite}` | Périodicité du paiement | select (4) | oui |  |
| `${bail.caution_chiffres}` | Caution (GNF) | number |  | `_lettres` `_formate` |
| `${bail.avance_loyer}` | Avance sur loyer (mois) | number |  |  |
| `${bail.destination}` | Destination des lieux | text |  |  |

---

## bail_commercial

**Type(s) d'acte** : BAI-COM — **27 champs**

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Bailleur** | | | | |
| `${pp.civilite}` | Civilité du bailleur | select (4) | oui |  |
| `${pp.prenom_nom}` | Nom et prénoms | text | oui |  |
| `${pp.nationalite}` | Nationalité | text |  |  |
| `${pp.adresse}` | Adresse du bailleur | text | oui |  |
| `${pp.piece_type}` | Type de pièce d'identité | text | oui |  |
| `${pp.piece_numero}` | Numéro de pièce | text | oui |  |
| `${pp.telephone}` | Téléphone bailleur | tel |  |  |
| `${pp.email}` | Email bailleur | email |  |  |
| **▸ Locataire / Preneur** | | | | |
| `${loc.civilite}` | Civilité du locataire/preneur | select (5) | oui |  |
| `${loc.prenom_nom}` | Nom et prénoms / Dénomination | text | oui |  |
| `${loc.nationalite}` | Nationalité / Pays | text |  |  |
| `${loc.adresse}` | Adresse du locataire | text | oui |  |
| `${loc.piece_type}` | Type de pièce | text | oui |  |
| `${loc.piece_numero}` | Numéro de pièce | text | oui |  |
| `${loc.telephone}` | Téléphone locataire | tel |  |  |
| `${loc.email}` | Email locataire | email |  |  |
| **▸ Local commercial** | | | | |
| `${bien.adresse}` | Adresse du local commercial | text | oui |  |
| `${bien.description}` | Description du local | textarea |  |  |
| `${bien.superficie}` | Superficie (m²) | number |  |  |
| **▸ Conditions du bail** | | | | |
| `${bail.date_prise_effet}` | Date de prise d'effet | date | oui | `_jma` `_lettres` |
| `${bail.duree_chiffres}` | Durée du bail (années) | number | oui | `_lettres` `_formate` |
| `${bail.loyer_chiffres}` | Loyer mensuel (GNF) | number | oui | `_lettres` `_formate` |
| `${bail.periodicite}` | Périodicité du paiement | select (4) | oui |  |
| `${bail.caution_chiffres}` | Caution (GNF) | number |  | `_lettres` `_formate` |
| `${bail.droit_entree_chiffres}` | Droit d'entrée / Pas-de-porte (GNF) | number |  | `_lettres` `_formate` |
| `${bail.destination}` | Activité commerciale autorisée | text | oui |  |
| `${bail.clause_renouvellement}` | Clause de renouvellement | text |  |  |

---

## bail_construction

**Type(s) d'acte** : BAI-CON — **26 champs**

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Bailleur** | | | | |
| `${pp.civilite}` | Civilité du bailleur | select (4) | oui |  |
| `${pp.prenom_nom}` | Nom et prénoms | text | oui |  |
| `${pp.nationalite}` | Nationalité | text |  |  |
| `${pp.adresse}` | Adresse du bailleur | text | oui |  |
| `${pp.piece_type}` | Type de pièce d'identité | text | oui |  |
| `${pp.piece_numero}` | Numéro de pièce | text | oui |  |
| `${pp.telephone}` | Téléphone bailleur | tel |  |  |
| `${pp.email}` | Email bailleur | email |  |  |
| **▸ Locataire / Preneur** | | | | |
| `${loc.civilite}` | Civilité du locataire/preneur | select (5) | oui |  |
| `${loc.prenom_nom}` | Nom et prénoms / Dénomination | text | oui |  |
| `${loc.nationalite}` | Nationalité / Pays | text |  |  |
| `${loc.adresse}` | Adresse du locataire | text | oui |  |
| `${loc.piece_type}` | Type de pièce | text | oui |  |
| `${loc.piece_numero}` | Numéro de pièce | text | oui |  |
| `${loc.telephone}` | Téléphone locataire | tel |  |  |
| `${loc.email}` | Email locataire | email |  |  |
| **▸ Terrain** | | | | |
| `${bien.superficie}` | Superficie du terrain (m²) | number | oui |  |
| `${bien.lieu_de}` | Situé à | text | oui |  |
| `${bien.titre_foncier_numero}` | Numéro du titre foncier | text |  |  |
| `${bien.description}` | Description du terrain | textarea |  |  |
| **▸ Conditions du bail** | | | | |
| `${bail.date_prise_effet}` | Date de prise d'effet | date | oui | `_jma` `_lettres` |
| `${bail.duree_chiffres}` | Durée du bail à construction (années) | number | oui | `_lettres` `_formate` |
| `${bail.loyer_chiffres}` | Redevance annuelle (GNF) | number | oui | `_lettres` `_formate` |
| `${bail.engagement_construction}` | Engagement de construction | textarea | oui |  |
| `${bail.valeur_constructions_chiffres}` | Valeur estimée des constructions (GNF) | number |  | `_lettres` `_formate` |
| `${bail.destination}` | Destination des constructions | text |  |  |

---

## hypotheque_conv

**Type(s) d'acte** : HYP-CON — **34 champs**

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Débiteur / Emprunteur** | | | | |
| `${pp.civilite}` | Civilité du débiteur | select (3) | oui |  |
| `${pp.prenom_nom}` | Nom et prénoms | text | oui |  |
| `${pp.ne_a}` | Né(e) à | text |  |  |
| `${pp.date_naissance}` | Date de naissance | date |  | `_jma` `_lettres` |
| `${pp.nationalite}` | Nationalité | text |  |  |
| `${pp.situation_matrimoniale}` | Situation matrimoniale | select (4) |  |  |
| `${pp.regime_matrimonial}` | Régime matrimonial | select (4) |  |  |
| `${pp.adresse}` | Adresse complète | text | oui |  |
| `${pp.piece_type}` | Type de pièce d'identité | text | oui |  |
| `${pp.piece_numero}` | Numéro de pièce | text | oui |  |
| `${pp.piece_delivree_le}` | Pièce délivrée le | date |  | `_jma` `_lettres` |
| `${pp.piece_delivree_a}` | Délivrée à | text |  |  |
| `${pp.telephone}` | Téléphone | tel |  |  |
| `${pp.email}` | Email | email |  |  |
| **▸ Banque / Créancier** | | | | |
| `${bq.denomination}` | Dénomination de la banque | text | oui |  |
| `${bq.forme}` | Forme juridique | text |  |  |
| `${bq.siege_ville}` | Ville | text |  |  |
| `${bq.siege_commune}` | Commune du siège | text |  |  |
| `${bq.siege_quartier}` | Quartier du siège | text |  |  |
| `${bq.representant_nom}` | Représentant légal de la banque | text | oui |  |
| `${bq.representant_qualite}` | Qualité du représentant | text |  |  |
| `${bq.montant_credit_chiffres}` | Montant du crédit accordé (GNF) | number | oui | `_lettres` `_formate` |
| `${bq.taux_interet}` | Taux d'intérêt annuel (%) | number |  |  |
| `${bq.duree_credit_chiffres}` | Durée du crédit (mois) | number |  | `_lettres` `_formate` |
| `${bq.type_garantie}` | Type de garantie | text |  |  |
| `${bq.rang_hypothecaire}` | Rang de l'hypothèque | text |  |  |
| **▸ Bien hypothéqué** | | | | |
| `${bien.titre_foncier_numero}` | Numéro du titre foncier hypothéqué | text | oui |  |
| `${bien.superficie}` | Superficie (m²) | number |  |  |
| `${bien.lieu_de}` | Situé à | text | oui |  |
| `${bien.nature_terrain}` | Nature du terrain / bien | text |  |  |
| `${bien.limite_nord}` | Limite Nord | text |  |  |
| `${bien.limite_sud}` | Limite Sud | text |  |  |
| `${bien.limite_est}` | Limite Est | text |  |  |
| `${bien.limite_ouest}` | Limite Ouest | text |  |  |

---

## mainlevee

**Type(s) d'acte** : HYP-MAI — **15 champs**

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Débiteur** | | | | |
| `${pp.civilite}` | Civilité du débiteur | select (3) | oui |  |
| `${pp.prenom_nom}` | Nom et prénoms | text | oui |  |
| `${pp.adresse}` | Adresse | text | oui |  |
| `${pp.piece_type}` | Type de pièce | text |  |  |
| `${pp.piece_numero}` | Numéro de pièce | text |  |  |
| `${pp.telephone}` | Téléphone | tel |  |  |
| **▸ Banque créancière** | | | | |
| `${bq.denomination}` | Dénomination de la banque créancière | text | oui |  |
| `${bq.representant_nom}` | Représentant légal | text | oui |  |
| `${bq.representant_qualite}` | Qualité du représentant | text |  |  |
| **▸ Hypothèque à radier** | | | | |
| `${hypotheque.reference_acte}` | Référence de l'acte d'hypothèque | text | oui |  |
| `${hypotheque.date_acte}` | Date de l'acte | date | oui | `_jma` `_lettres` |
| `${hypotheque.notaire_acte}` | Notaire instrumentaire | text |  |  |
| `${hypotheque.montant_chiffres}` | Montant garanti à l'origine (GNF) | number |  | `_lettres` `_formate` |
| `${bien.titre_foncier_numero}` | Numéro du titre foncier concerné | text | oui |  |
| `${hypotheque.rang}` | Rang de l'hypothèque | text |  |  |

---

## modification

**Type(s) d'acte** : SOC-MOD — **72 champs** + 4 bloc(s) répétable(s)

| Balise | Libellé | Type | Oblig. | Dérivées |
|---|---|---|---|---|
| **▸ Société concernée** | | | | |
| `${soc.denomination}` | Dénomination sociale | text | oui |  |
| `${soc.sigle}` | Sigle | text |  |  |
| `${soc.forme}` | Forme juridique | select (9) | oui |  |
| `${soc.rccm}` | Numéro RCCM actuel | text | oui |  |
| `${soc.nif}` | NIF | text |  |  |
| `${soc.date_constitution}` | Date de constitution | date |  | `_jma` `_lettres` |
| `${soc.capital_chiffres}` | Capital social actuel (GNF) | number | oui | `_lettres` `_formate` |
| `${soc.nombre_parts}` | Nombre de parts actuel | number |  |  |
| `${soc.valeur_nominale_chiffres}` | Valeur nominale d'une part (GNF) | number |  | `_lettres` `_formate` |
| `${soc.siege_ville}` | Ville du siège actuel | text | oui |  |
| `${soc.siege_commune}` | Commune du siège actuel | text | oui |  |
| `${soc.siege_quartier}` | Quartier du siège actuel | text | oui |  |
| `${soc.objet_social}` | Objet social actuel | textarea |  |  |
| `${soc.gerant_actuel}` | Gérant / dirigeant actuel | text |  |  |
| `${soc.email_societe}` | Email de la société | text |  |  |
| `${soc.telephone_societe}` | Téléphone de la société | tel |  |  |
| **▸ Modification(s) décidée(s)** | | | | |
| `${modif.types}` | Modifications décidées | checkbox_group (9) | oui |  |
| **▸ Assemblée générale** | | | | |
| `${ag.type}` | Nature de la décision | select (3) | oui |  |
| `${ag.date}` | Date de l'assemblée | date | oui | `_jma` `_lettres` |
| `${ag.heure}` | Heure | text |  |  |
| `${ag.lieu}` | Lieu de l'assemblée | text |  |  |
| `${ag.president_seance}` | Président de séance | text |  |  |
| `${ag.secretaire_seance}` | Secrétaire de séance | text |  |  |
| `${ag.parts_representees}` | Parts présentes ou représentées | number |  |  |
| `${ag.quorum_atteint}` | Quorum atteint | checkbox |  |  |
| `${ag.date_effet}` | Date d'effet de la modification | date |  | `_jma` `_lettres` |
| `${ag.resolutions}` | Résolutions adoptées | textarea |  |  |
| **▸ Cession de parts — conditions** | | | | |
| `${modif.date_cession}` | Date de la cession | date |  | `_jma` `_lettres` |
| `${modif.valeur_parts_cedees}` | Valeur totale des parts cédées (GNF) | number | oui |  |
| `${modif.agrement_associes}` | Agrément des associés obtenu | checkbox |  |  |
| **▸ Transfert du siège social** | | | | |
| `${modif.siege_nouveau_ville}` | Nouvelle ville | text | oui |  |
| `${modif.siege_nouveau_commune}` | Nouvelle commune | text | oui |  |
| `${modif.siege_nouveau_quartier}` | Nouveau quartier du siège | text | oui |  |
| `${modif.siege_justificatif}` | Titre d'occupation du nouveau siège | select (4) |  |  |
| **▸ Augmentation de capital** | | | | |
| `${modif.augmentation_montant}` | Montant de l'augmentation (GNF) | number | oui |  |
| `${modif.augmentation_capital_apres}` | Capital après augmentation (GNF) | number |  |  |
| `${modif.augmentation_modalite}` | Modalité de l'augmentation | select (3) | oui |  |
| `${modif.augmentation_parts_nouvelles}` | Nombre de parts nouvelles | number |  |  |
| `${modif.augmentation_banque}` | Banque de dépôt des fonds | text |  |  |
| `${modif.augmentation_date_versement}` | Date du versement | date |  | `_jma` `_lettres` |
| **▸ Diminution de capital** | | | | |
| `${modif.diminution_montant}` | Montant de la réduction (GNF) | number | oui |  |
| `${modif.diminution_capital_apres}` | Capital après réduction (GNF) | number |  |  |
| `${modif.diminution_motif}` | Motif de la réduction | select (2) | oui |  |
| `${modif.diminution_parts_annulees}` | Nombre de parts annulées | number |  |  |
| **▸ Gérant sortant** | | | | |
| `${gerant_sortant.prenom_nom}` | Gérant sortant | text | oui |  |
| `${gerant_sortant.motif}` | Motif de la cessation | select (4) | oui |  |
| `${gerant_sortant.date_cessation}` | Date de cessation des fonctions | date |  | `_jma` `_lettres` |
| **▸ Gérant entrant** | | | | |
| `${gerant_entrant.civilite}` | Civilité du gérant entrant | select (3) | oui |  |
| `${gerant_entrant.prenom_nom}` | Nom et prénoms | text | oui |  |
| `${gerant_entrant.ne_a}` | Né(e) à | text | oui |  |
| `${gerant_entrant.date_naissance}` | Date de naissance | date | oui | `_jma` `_lettres` |
| `${gerant_entrant.nationalite}` | Nationalité | text |  |  |
| `${gerant_entrant.situation_matrimoniale}` | Situation matrimoniale | select (4) |  |  |
| `${gerant_entrant.demeurant_ville}` | Ville (résidence) | text |  |  |
| `${gerant_entrant.commune}` | Commune (résidence) | text |  |  |
| `${gerant_entrant.quartier}` | Quartier (résidence) | text |  |  |
| `${gerant_entrant.pays}` | Pays de résidence | text |  |  |
| `${gerant_entrant.piece_type}` | Type de pièce d'identité | text | oui |  |
| `${gerant_entrant.piece_numero}` | Numéro de pièce | text | oui |  |
| `${gerant_entrant.piece_delivree_le}` | Pièce délivrée le | date | oui | `_jma` `_lettres` |
| `${gerant_entrant.piece_delivree_a}` | Délivrée à | text | oui |  |
| `${gerant_entrant.piece_expire_le}` | Expire le | date |  | `_jma` `_lettres` |
| `${gerant_entrant.telephone}` | Téléphone | tel |  |  |
| `${gerant_entrant.email}` | Email | email |  |  |
| `${gerant_entrant.duree_mandat}` | Durée du mandat | text |  |  |
| `${gerant_entrant.pouvoirs}` | Pouvoirs conférés | textarea |  |  |
| **▸ Objet social** | | | | |
| `${modif.objet_operation}` | Nature du changement d'objet | select (3) | oui |  |
| `${modif.objet_nouveau}` | Nouvel objet social | textarea | oui |  |
| **▸ Modification de dénomination** | | | | |
| `${modif.denomination_nouvelle}` | Nouvelle dénomination sociale | text | oui |  |
| `${modif.sigle_nouveau}` | Nouveau sigle (facultatif) | text |  |  |
| **▸ Modification de forme juridique** | | | | |
| `${modif.forme_nouvelle}` | Nouvelle forme juridique | select (9) | oui |  |
| **▸ Précisions** | | | | |
| `${objet_modification}` | Précisions complémentaires | textarea |  |  |

### Bloc répétable `modif.cedants` — Cédant(s)

```
${modif.cedants}
   … texte répété, utilisant ${modif.cedants.civilite} …
${/modif.cedants}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${modif.cedants.civilite}` | Civilité | select |  |
| `${modif.cedants.nom}` | Nom et prénoms / Dénomination | text |  |
| `${modif.cedants.type_personne}` | Type | select |  |
| `${modif.cedants.parts_detenues}` | Parts détenues avant cession | number |  |
| `${modif.cedants.parts_cedees}` | Parts cédées | number |  |
| `${modif.cedants.prix_cession}` | Prix de cession (GNF) | number |  |
| `${modif.cedants.forme}` | Forme juridique | text |  |
| `${modif.cedants.rccm}` | Numéro RCCM | text |  |
| `${modif.cedants.representant_legal}` | Représentant légal | text |  |
| `${modif.cedants.ne_a}` | Né(e) à | text |  |
| `${modif.cedants.date_naissance}` | Date de naissance | date | `_jma` `_lettres` |
| `${modif.cedants.nationalite}` | Nationalité / Pays | text |  |
| `${modif.cedants.situation_matrimoniale}` | Situation matrimoniale | select |  |
| `${modif.cedants.regime_matrimonial}` | Régime matrimonial | select |  |
| `${modif.cedants.demeurant_ville}` | Ville (résidence) | text |  |
| `${modif.cedants.commune}` | Commune (résidence) | text |  |
| `${modif.cedants.quartier}` | Quartier (résidence) | text |  |
| `${modif.cedants.pays}` | Pays de résidence | text |  |
| `${modif.cedants.piece_type}` | Type de pièce d'identité | text |  |
| `${modif.cedants.cni}` | N° pièce d'identité | text |  |
| `${modif.cedants.piece_delivree_le}` | Pièce délivrée le | date | `_jma` `_lettres` |
| `${modif.cedants.piece_delivree_a}` | Délivrée à | text |  |
| `${modif.cedants.piece_expire_le}` | Expire le | date | `_jma` `_lettres` |
| `${modif.cedants.telephone}` | Téléphone | tel |  |
| `${modif.cedants.email}` | Email | email |  |

### Bloc répétable `modif.cessionnaires` — Cessionnaire(s)

```
${modif.cessionnaires}
   … texte répété, utilisant ${modif.cessionnaires.civilite} …
${/modif.cessionnaires}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${modif.cessionnaires.civilite}` | Civilité | select |  |
| `${modif.cessionnaires.nom}` | Nom et prénoms / Dénomination | text |  |
| `${modif.cessionnaires.type_personne}` | Type | select |  |
| `${modif.cessionnaires.parts_acquises}` | Parts acquises | number |  |
| `${modif.cessionnaires.prix_paye}` | Prix payé (GNF) | number |  |
| `${modif.cessionnaires.forme}` | Forme juridique | text |  |
| `${modif.cessionnaires.rccm}` | Numéro RCCM | text |  |
| `${modif.cessionnaires.representant_legal}` | Représentant légal | text |  |
| `${modif.cessionnaires.ne_a}` | Né(e) à | text |  |
| `${modif.cessionnaires.date_naissance}` | Date de naissance | date | `_jma` `_lettres` |
| `${modif.cessionnaires.nationalite}` | Nationalité / Pays | text |  |
| `${modif.cessionnaires.situation_matrimoniale}` | Situation matrimoniale | select |  |
| `${modif.cessionnaires.regime_matrimonial}` | Régime matrimonial | select |  |
| `${modif.cessionnaires.demeurant_ville}` | Ville (résidence) | text |  |
| `${modif.cessionnaires.commune}` | Commune (résidence) | text |  |
| `${modif.cessionnaires.quartier}` | Quartier (résidence) | text |  |
| `${modif.cessionnaires.pays}` | Pays de résidence | text |  |
| `${modif.cessionnaires.piece_type}` | Type de pièce d'identité | text |  |
| `${modif.cessionnaires.cni}` | N° pièce d'identité | text |  |
| `${modif.cessionnaires.piece_delivree_le}` | Pièce délivrée le | date | `_jma` `_lettres` |
| `${modif.cessionnaires.piece_delivree_a}` | Délivrée à | text |  |
| `${modif.cessionnaires.piece_expire_le}` | Expire le | date | `_jma` `_lettres` |
| `${modif.cessionnaires.telephone}` | Téléphone | tel |  |
| `${modif.cessionnaires.email}` | Email | email |  |

### Bloc répétable `modif.souscripteurs` — Souscripteurs

```
${modif.souscripteurs}
   … texte répété, utilisant ${modif.souscripteurs.civilite} …
${/modif.souscripteurs}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${modif.souscripteurs.civilite}` | Civilité | select |  |
| `${modif.souscripteurs.nom}` | Nom et prénoms / Dénomination | text |  |
| `${modif.souscripteurs.type_personne}` | Type | select |  |
| `${modif.souscripteurs.parts_souscrites}` | Parts souscrites | number |  |
| `${modif.souscripteurs.montant_souscrit}` | Montant souscrit (GNF) | number |  |
| `${modif.souscripteurs.forme}` | Forme juridique | text |  |
| `${modif.souscripteurs.rccm}` | Numéro RCCM | text |  |
| `${modif.souscripteurs.representant_legal}` | Représentant légal | text |  |
| `${modif.souscripteurs.ne_a}` | Né(e) à | text |  |
| `${modif.souscripteurs.date_naissance}` | Date de naissance | date | `_jma` `_lettres` |
| `${modif.souscripteurs.nationalite}` | Nationalité / Pays | text |  |
| `${modif.souscripteurs.situation_matrimoniale}` | Situation matrimoniale | select |  |
| `${modif.souscripteurs.regime_matrimonial}` | Régime matrimonial | select |  |
| `${modif.souscripteurs.demeurant_ville}` | Ville (résidence) | text |  |
| `${modif.souscripteurs.commune}` | Commune (résidence) | text |  |
| `${modif.souscripteurs.quartier}` | Quartier (résidence) | text |  |
| `${modif.souscripteurs.pays}` | Pays de résidence | text |  |
| `${modif.souscripteurs.piece_type}` | Type de pièce d'identité | text |  |
| `${modif.souscripteurs.cni}` | N° pièce d'identité | text |  |
| `${modif.souscripteurs.piece_delivree_le}` | Pièce délivrée le | date | `_jma` `_lettres` |
| `${modif.souscripteurs.piece_delivree_a}` | Délivrée à | text |  |
| `${modif.souscripteurs.piece_expire_le}` | Expire le | date | `_jma` `_lettres` |
| `${modif.souscripteurs.telephone}` | Téléphone | tel |  |
| `${modif.souscripteurs.email}` | Email | email |  |

### Bloc répétable `modif.repartition_apres` — Répartition du capital après modification

```
${modif.repartition_apres}
   … texte répété, utilisant ${modif.repartition_apres.associe} …
${/modif.repartition_apres}
```

| Balise dans le bloc | Libellé | Type | Dérivées |
|---|---|---|---|
| `${modif.repartition_apres.associe}` | Associé | text |  |
| `${modif.repartition_apres.parts_chiffres}` | Nombre de parts détenues | number | `_lettres` `_formate` |
| `${modif.repartition_apres.pourcentage}` | Pourcentage (%) | number |  |
