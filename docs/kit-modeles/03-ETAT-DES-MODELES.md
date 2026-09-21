# 03 — État réel des modèles : ce qui est fait, ce qui reste, ce qui est à corriger

Audit mené le 21/09/2026 en ouvrant chaque fichier `.docx` réellement en service et en confrontant
ses balises aux champs réels des questionnaires. **Ce n'est pas une estimation : chaque ligne a été
mesurée.**

---

## 1. Vue d'ensemble

| Mesure | Valeur |
|---|---|
| Entrées « Modèle d'acte » en base | **69** |
| Dont actives (produisent réellement un acte) | **15** |
| Fichiers `.docx` présents et **balisés** | **17** |
| Fichiers présents mais **sans aucune balise** (copies brutes) | **13** — les 12 courriers de transmission + un gabarit |
| Balises **non résolvables** trouvées dans les modèles en service | **10 occurrences, 6 balises distinctes** |

**Ce que cela signifie** : la mécanique fonctionne de bout en bout — la SARLU est complète et
produit ses sept documents. Le travail restant est un travail de **balisage de documents**, pas de
développement.

---

## 2. Modèles balisés et en service

| Modèle | Rôle | Balises | État |
|---|---|---|---|
| Statuts SARLU | `acte_principal` | 49 | ⚠️ 1 balise fausse |
| Attestation de dépôt du capital SARLU | `attestation` | 9 | ✅ |
| Déclaration sur l'honneur SARLU | `declaration` | 15 | ✅ |
| DNSV SARLU | `dnsv` | 34 | ✅ |
| RCCM SARLU | `rccm` | 20 | ⚠️ 3 balises fausses |
| Attestation de dépôt du capital SARL | `attestation` | 4 | ✅ |
| DNSV SARL | `dnsv` | 24 | ✅ |
| Attestation de dépôt du capital SAS | `attestation` | 4 | ✅ |
| DNSV SAS | `dnsv` | 24 | ✅ |
| Attestation de dépôt du capital SASU | `attestation` | 4 | ✅ |
| DNSV SASU | `dnsv` | 25 | ✅ |
| Page de garde vente immobilière | `page_garde` | 6 | ✅ |
| Page de garde vente sans titre foncier | `page_garde` | 7 | ✅ |
| Bail d'habitation notarié | `acte_principal` | 27 | ⚠️ 3 balises fausses |
| Bail à construction notarié | `acte_principal` | 24 | ⚠️ 3 balises fausses |

Deux modèles balisés sont **inactifs** : *Cession de fonds de commerce* (20 balises, 3 fausses) et
*Copie de RCCM SARLU* (20 balises, 3 fausses).

---

## 3. Les six balises fausses — à corriger en priorité

Ces balises ne correspondent à **aucun champ réel**. Elles produisent un **blanc** dans l'acte, et
seule une ligne de l'historique du dossier le signale.

| Balise écrite | Présente dans | Ce qu'il faut écrire à la place |
|---|---|---|
| `${date_acte_lettres_sans_annee}` | Statuts SARLU, Bail d'habitation, Bail à construction | **`${date_acte_lettres}`** — c'est exactement ce qu'elle fait : jour + mois sans l'année |
| `${ger.nom}` et `${ger.prenom}` | RCCM SARLU, Cession de fonds de commerce, Copie de RCCM SARLU | **`${ger.prenom_nom}`** — le questionnaire ne sépare pas nom et prénom |
| `${soc.date_debut_activite_jma}` | RCCM SARLU, Cession de fonds de commerce, Copie de RCCM SARLU | ⟦à arbitrer⟧ — aucun champ « date de début d'activité » n'existe. Soit l'ajouter au questionnaire, soit se rabattre sur `${date_acte_jma}` |
| `${loc.ne_a}` et `${loc.date_naissance}` | Bail d'habitation, Bail à construction | ⟦à arbitrer⟧ — le bloc locataire ne porte que 8 champs et n'a ni lieu ni date de naissance. Soit les ajouter au questionnaire de bail, soit retirer la mention de l'acte |

Deux autres modèles **pas encore téléversés** portent quatre balises fausses de plus :
`${soc.nombre_parts_lettres}`, `${soc.premier_exercice_annee_lettres}`, `${soc.rccm_numero}`,
`${soc.rccm_date_jma}` (dans `INSERTION_SARLU_balises.docx`).

> **Le piège à comprendre** : `${soc.nombre_parts_lettres}` **paraît** correct, puisque
> `${soc.capital_lettres}` fonctionne. Mais la variante `_lettres` n'est fabriquée que pour un champ
> dont le nom finit par `_chiffres`. `soc.capital_chiffres` en a une ; `soc.nombre_parts`, non.

Les deux modèles de note de frais (`facture-notariale.docx`) portent **13 balises toutes fausses**
(`fac.*`, `ligne.*`) : le bloc facture n'existe pas dans les questionnaires. Ces modèles ne sont
rattachés à aucun type d'acte actif — voir §5.

---

## 4. Ce qui reste à baliser

### Sociétés — les documents fournis par l'étude

| Forme | Statuts | Page de garde | Attestation | Déclaration | DNSV | Insertion | RCCM |
|---|---|---|---|---|---|---|---|
| **SARLU** | ✅ | ⬜ | ✅ | ✅ | ✅ | ⬜ *(balisé, non téléversé)* | ⚠️ |
| **SARL** | ⬜ | ⬜ | ✅ | ⬜ | ✅ | ⬜ | ⬜ |
| **SAS** | ⬜ | ⬜ | ✅ | ⬜ | ✅ | ⬜ | ⬜ |
| **SASU** | ⬜ | ⬜ | ✅ | ⬜ | ✅ | ⬜ | ⬜ |
| **SA** | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ |
| **SNC** | ⬜ | — | — | — | — | ⬜ | ⬜ |
| **GIE** | ⬜ | — | — | — | — | ⬜ | ⬜ |

Les fichiers bruts sont dans `Documents reçus/SARL/`, `/SAS/`, `/SASU/`. **Plusieurs sont en
`.doc`** (`STATUTS.doc`, `RCCM 1.doc`, `INSERTION.doc`, `PAGE DE GARDE.doc`,
`DECLARATION SUR L'HONNEUR -.doc`) : il faut les ré-enregistrer en `.docx` avant tout balisage.

### Ventes et baux

| Document | Source | État |
|---|---|---|
| Contrat de vente avec titre foncier | `Documents reçus/Modèles d'actes de vente avec titre foncier/CONTRAT DE VENTE .doc` | ⬜ |
| Contrat de vente sans titre foncier | `…sans titre foncier/CONTRAT DE VENTE .doc` | ⬜ |
| Tableau de bordereau | `…avec titre foncier/TABLEAU DE BORDEREAU.doc` | ⬜ |
| Contrat de bail à habitation | `Documents reçus/Contrat de bail à habitation/CONTRAT DE BAIL.docx` | ⚠️ en service, 3 balises fausses |
| Bail à construction | `Documents reçus/modele bail a construction/BAIL A CONSTRUCTION.docx` | ⚠️ en service, 3 balises fausses |

### Modification de société — cinq documents, aucun balisé

`acte_cession`, `pv_modification`, `statuts_maj`, `declaration_rccm`, et la
*DNSV augmentation de capital*. Les entrées existent en base, inactives, sans fichier. Le
questionnaire `modification` (72 champs + 4 blocs répétables) est prêt : ces documents sont les
plus rentables à traiter ensuite.

### Courriers — douze documents, aucun balisé

`Documents reçus/Courrier de transmission/` : les douze lettres sont téléversées **telles quelles**,
sans une seule balise. ⚠️ **Attention** : le bloc `cr.*` (destinataire, objet, référence…) du
dictionnaire **n'existe dans aucun questionnaire**. Baliser ces courriers avec `${cr.destinataire_nom}`
ne produirait que des blancs. Il faut d'abord décider d'où viendront ces données.

---

## 5. Les types d'actes sans questionnaire

Seize questionnaires existent. **Ces types d'actes n'en ont aucun** — un modèle balisé pour eux ne
serait rempli que par les constantes d'office et de dossier :

`VTE-FDS` (cession de fonds de commerce) · `VTE-VEH` (vente de véhicule) ·
`SUC-DEC` · `SUC-PAR` (successions) · `DON-SIM` · `DON-PAR` (donations) ·
`MAR-COM` · `MAR-DIV` (mariage, divorce) · `PRO-GEN` · `PRO-SPE` (procurations) ·
`PEC-MIN` · `PEC-ADT` · `PEC-FIN` · `PEC-SCO` (prises en charge) · `TES` (testament).

**Conséquence** : baliser leurs modèles est prématuré. Il faut d'abord créer leur questionnaire.

---

## 6. L'écart entre le dictionnaire et la réalité

`dictionnaire_balises.md` cite **293 balises**. **83 d'entre elles n'ont aucun champ réel.**

Les principales familles concernées :

| Famille | Balises du dictionnaire | Réalité |
|---|---|---|
| **Courrier** | `cr.destinataire_nom`, `cr.objet`, `cr.nref`, `cr.lieu`… | aucun questionnaire ne les porte |
| **Facture** | `fac.total_chiffres`, `fac.assiette_lettres`, `ligne.designation`… | aucun questionnaire ne les porte |
| **Personne morale** | `pm.denomination`, `pm.forme`, `pm.rccm`, `pm.siege` | le type de personne se gère dans les blocs répétables, pas par un préfixe `pm.` |
| **Blocs répétables** | `bloc_associe`, `bloc_tf`, `bloc_ligne`, `associe.prenom_nom`, `cedant.nom`, `souscripteur.nom`, `repartition.associe` | la syntaxe réelle est `${associes}…${/associes}` avec `${associes.nom}` à l'intérieur, et `${modif.cedants}…${/modif.cedants}` avec `${modif.cedants.nom}` |
| **Bien immobilier** | `bien.limites_ne/no/se/so`, `bien.tf_volume`, `bien.tf_folio`, `bien.superficie_m2`, `bien.lot_numero` | les vrais noms sont `bien.limite_nord/sud/est/ouest`, `bien.superficie`, `bien.lot`, `bien.titre_foncier_numero` |
| **Modification** | `modif.augmentation_montant_chiffres`, `modif.valeur_parts_cedees_chiffres`… | les vrais noms **n'ont pas** le suffixe `_chiffres` : `modif.augmentation_montant`, `modif.valeur_parts_cedees`. ⚠️ Et **sans `_chiffres`, il n'y a donc pas de variante `_lettres`** |

> **Règle à retenir** : le dictionnaire décrit une **intention de conception**.
> [`02-VARIABLES-DISPONIBLES.md`](02-VARIABLES-DISPONIBLES.md) décrit le **code réel**. En cas de
> désaccord, c'est le second qui a raison.

---

## 7. Le contrôle à passer avant chaque livraison

```
node tools/generer-balises-resolvables.mjs     # une fois, ou après modification des questionnaires
php tools/verifier-balises.php "MON_MODELE.docx"
```

Le script liste les balises du document et signale celles qui ne se rempliront pas. Il refuse de
tourner si sa liste de référence est plus vieille que `questionnaires.js` — une liste périmée
validerait des balises devenues fausses.
