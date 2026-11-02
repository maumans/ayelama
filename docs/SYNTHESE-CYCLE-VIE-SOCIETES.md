# Cycle de vie des sociétés — synthèse des deux documents et reste à faire

> Établi le 2026-10-30, à partir de :
>
> 1. `docs/Addvalis_Specifications_Phase2_v1.2_Addendum_Cycle_Vie_Societes.docx`
>    (réf. ADV-NOT-2026-SPECS-PH2 — A1) — la spécification ;
> 2. `docs/Addvalis_Tableau_Validation_Modif_Dissol_Liquid_OK (1).docx` — le tableau de
>    validation **signé par Maître Ayelama Bah le 28 octobre 2026**, qui répond à la
>    spécification point par point.
>
> Les deux ne disent pas la même chose partout. **Le tableau signé l'emporte** : c'est l'étude
> qui parle, la spécification propose.

---

## 1. Ce que le tableau signé tranche

Cinq règles que la spécification laissait « à confirmer » ont désormais une réponse écrite.
Elles sont reportées dans le code, chacune citant sa ligne.

| Ligne | Sujet | Réponse de l'étude | Effet sur le dépôt |
|---|---|---|---|
| 5 | Nomination / révocation d'un gérant **non statutaire** | AGO · statuts non · RCCM oui · **JAL oui** (mention ajoutée à la main) | Répond à la question Q1. La règle passe de « déduite » à **validée** |
| 6 | **Décision de gestion** (consentir une hypothèque…) | AGO · **ni statuts ni RCCM** · « Toutes décisions qui n'entraînent pas la modification des statuts doivent être prises en AGO » | Répond à Q2. Opération ajoutée au dépôt, règle **validée** |
| 17 | **Clôture de liquidation** | AG de clôture · RCCM de liquidation · radiation — **aucune publication citée**, là où la ligne 15 la cite pour la dissolution | La non-publication au JAL devient une réponse, pas un défaut prudent |
| F1 | Capital minimum SARL | « Pas de capital minimum » | Confirme l'état du dépôt. ⚠️ **Rien n'est dit de la SA** : son minimum paramétrable subsiste |
| 18 | Conservation des minutes | **Correction** : « Le notaire garde les originaux appelés minutes et transmet les copies (expéditions) aux clients » | Notre lecture était l'inverse. À reprendre dans le lot GED |

### Deux règles nouvelles, qui n'étaient dans aucun des deux documents

- **Ligne 16** — une société **prorogée au moins un an avant son terme** n'est pas dissoute à
  l'échéance. L'alerte de terme l'ignore aujourd'hui : elle préviendra pour une société
  régulièrement prorogée.
- **F2** — au-delà de trois ans de liquidation, « le ministère public ou tout intéressé peut
  saisir la juridiction compétente du ressort de laquelle est situé le siège de la société ».
  C'est la réponse au `a_verifier` posé sur l'échéance de liquidation.

### Trois lignes restées sans croix

Les lignes **2** (augmentation ou réduction de capital), **3** (cession de parts) et **7**
(changement de forme juridique) n'ont pas été cochées. Quatorze autres le sont : le silence
n'est pas un accord. Les règles correspondantes restent celles que le dépôt applique, mais
l'écran d'administration les signale **non relues** plutôt que validées.

La ligne 7 s'est tout de même précisée : **un rapport du commissaire aux comptes est exigé**
(art. 374 de l'acte uniforme relatif au Droit des Sociétés Commerciales et du GIE, révisé,
en vigueur le 5 mai 2014). Les règles de majorité restent inconnues.

Deux précisions utiles ailleurs :

- **Ligne 2** — « L'attestation est fournie par le client lorsque le dépôt des fonds est
  effectué auprès de la banque. Dans le cas contraire, elle est établie par le notaire. »
  La même ligne réunit **augmentation et réduction** : la réduction de capital, que la
  spécification range « hors périmètre V1 », est bien une opération de l'étude.
- **Ligne 11** — société créée ailleurs : « Il suffit juste qu'une copie des statuts et RCCM
  de la société à modifier ». Exactement les deux pièces marquées obligatoires au dépôt.
- **F4** — réduction de capital, transfert hors de Conakry, changement de forme :
  « Conformément aux règles et prescriptions prévues par l'acte uniforme ». Pas de règle
  locale particulière à inventer.

---

## 2. Où en est le travail

### Fait et vérifié

| Lot | Contenu | Preuve |
|---|---|---|
| **1** | Associés, gérants et historique datés ; état courant et projeté ; cession portée au registre ; fiche société à onglets ; reprise des 15 fiches | `CompositionSocieteTest` (18 tests) ; reprise exécutée : 15 sociétés, 19 associés, 15 gérants, 15 lignes d'historique |
| **2** | Catalogue des opérations réglable, écran d'administration, mode de traitement, société externe, décision de gestion | `OperationSocieteTest` (17), `ParitesVariantesSocieteTest` (6), `ModeTraitementTest` (4) |

Suite complète : **758 tests, 748 passants**, 10 échecs antérieurs à ces travaux et sans
rapport avec eux (mots de passe, inscription, trois vérifications de pièces, brouillon,
page d'accueil, nom de fichier de facture).

### ⚠️ Le blocage qui commande l'ordre de la suite

**Aucune formalité de modification ou de dissolution n'est générée aujourd'hui.** Mesuré ce
jour :

```
SOC-MOD : 12 barèmes, 5 actifs, 0 actif générant une formalité
SOC-DIS : 11 barèmes, 0 actif,  0 actif générant une formalité
```

Et les 9 formalités `SOC-MOD` présentes en base sont chez **l'APIP** — précisément ce que la
ligne 12 du tableau signé exclut : « L'étude traite directement avec le greffe du tribunal de
commerce. L'APIP n'intervient pas dans les modifications. »

**Conséquence** : déplacer aujourd'hui l'application de l'état au retour de la formalité RCCM,
comme la spécification le demande, ferait qu'**aucune modification ni dissolution ne
s'appliquerait jamais** — il n'y aurait pas de formalité pour revenir. Le déplacement doit
suivre la création des formalités, pas la précéder.

---

## 3. Ce qui reste, par lot

### Lot 3 — les formalités, puis le moment d'application

1. Créer l'organisme **JAL** et les formalités `MODIF_RCCM`, `RCCM_DISSOLUTION`,
   `RCCM_LIQUIDATION`, `RADIATION`, avec leurs prérequis.
2. **Retirer les formalités APIP** posées à tort sur `SOC-MOD` / `SOC-DIS`, et le garde-fou
   qui interdit d'en reposer (ligne 12 du tableau signé).
3. Alors seulement, **déplacer l'application de l'état** de l'entrée en Expédition au retour
   de la formalité RCCM. Cela renverse la décision 59 du devbook, qui devra être réécrite.

### Lot 4 — l'assistant d'opération

Les quatre étapes de la spécification, le formulaire « actuel → nouveau », la répartition
projetée à l'écran (le calcul existe depuis le lot 1), la checklist par opération, et la
**déduction du type d'assemblée** : `ag.type` est aujourd'hui un choix libre que rien ne
confronte aux opérations cochées — une cession tenue en AGO passe sans un mot.

### Lot 5 — les actes, et ce que le mode de traitement change

- Gabarits `ACTE_DEPOT` et `PV_CLOTURE_LIQUIDATION`. Les modèles Word de dissolution et de
  liquidation ont été remis **sur papier** et sont en cours de numérisation : squelettes
  marqués « provisoire » en attendant.
- **Câbler le mode de traitement** : en dépôt au rang des minutes, pas de statuts mis à jour
  authentiques (ligne 10), un acte de dépôt, et les pièces `PV_CLIENT` / `STATUTS_CLIENT`.
  Le choix est enregistré depuis le lot 2 ; rien ne le lit encore, et le champ le dit.
- Ajouter le **rapport du commissaire aux comptes** aux documents du changement de forme
  juridique (ligne 7).

### Lot 6 — la GED

`nature_acte` (minute / brevet / certification) et le **numéro de répertoire**, séquence
annuelle sans trou, sous verrou.

⚠️ **À reprendre d'abord** : la ligne 18 corrige notre lecture. L'étude conserve les
**originaux** (les minutes) et remet les **expéditions** au client. Les brevets et
certifications, eux, sont bien remis au client sans conservation d'original (ligne 19). La
conservation est **papier** (F3) ; la durée de 100 ans n'a pas été confirmée.

### Lot 7 — devis et dossier unique

- **Proforma** : « Pas de forfait : une facture proforma est envoyée **après réception du
  questionnaire rempli, daté et signé** » (ligne 14). Le déclencheur est donc précis, et il
  n'est pas celui que la spécification proposait.
- Règle R6 : un seul dossier d'opération en cours par société.

### Hors lot — les deux règles nouvelles du tableau

- **Prorogation** : l'alerte de terme doit tenir compte d'une prorogation décidée au moins un
  an avant l'échéance (ligne 16). L'opération `PROROGATION` figure au catalogue, inactive,
  faute d'implémentation.
- **Échéance de liquidation** : remplacer le `a_verifier` par la sanction écrite en F2.

---

## 4. Écarts assumés, à remonter à Addvalis

1. **Pas de `tenant_id`, pas d'UUID, MySQL et non PostgreSQL.** L'application est mono-étude ;
   la spécification prévoit elle-même la traduction.
2. **`client_id` nullable** sur les associés et gérants, contre la spécification — sans quoi
   la reprise des parties sans fiche client était impossible.
3. **`societe_historique.dossier_id` nullable** — une radiation se constate sans dossier.
4. **Pas de colonne `origine`** : `dossier_id` répond déjà, et ne peut pas se contredire.
5. **Le capital minimum SA subsiste** (paramétrable). La ligne F1 ne répond que pour la SARL.
6. **Les codes d'opération du dépôt restent les siens** (`capital_cession`…), avec une
   correspondance vers ceux de la spécification (`CESSION_PARTS`…), vérifiée dans les deux
   sens par un test.
7. **`DIM_CAPITAL`** : code dérivé par symétrie avec `AUG_CAPITAL`, le § 5 ne nommant pas la
   réduction de capital que la ligne 2 du tableau signé range pourtant avec l'augmentation.
