# Plan d'implémentation — Gestion comptable de l'étude

*Établi le 2026-11-20, après lecture du code et mesure de la base de développement.*

> **Périmètre arrêté par l'étude le 2026-11-23 : la comptabilité des dossiers (niveau A)
> seulement.** La comptabilité générale (niveau B : journaux, grand livre, balance, exercices,
> états SYSCOHADA, dépenses de l'office) **n'est pas demandée** et n'est pas à faire. Livré
> (décisions #125 à #131 du devbook) : nature honoraire/débours des lignes, moyens de paiement en
> liste fermée, comptes bancaires de l'étude sur la facture, registre de numérotation unique,
> facture complémentaire des droits d'enregistrement, versements aux organismes, situation
> financière par dossier et vue d'ensemble `/comptabilite` avec export. Ce qui suit reste comme
> mémoire de conception.

---

## 0. Ce que demande vraiment « une gestion complète de la comptabilité »

La formule recouvre deux choses très différentes, et le plan les sépare :

| Niveau | Contenu | Qui s'en sert |
|---|---|---|
| **A. Comptabilité des dossiers** (gestion financière notariale) | ce que chaque client doit, a versé, ce que l'étude a avancé ou reversé aux administrations, la provision restante | comptable, notaire — au quotidien |
| **B. Comptabilité générale** (SYSCOHADA révisé, la Guinée étant membre de l'OHADA) | journaux, grand livre, balance, dépenses de l'office, trésorerie, exercices, états financiers | comptable, expert-comptable, fisc |

Le niveau A est la fondation du niveau B : une comptabilité générale qui serait saisie à part
recopierait à la main ce que l'application sait déjà. **Les écritures doivent naître des
événements métier**, pas d'une seconde saisie.

---

## 1. Existant (mesuré, pas supposé)

### Ce qui marche
- `Facture` / `LigneFacture` générées depuis les barèmes (`FacturationService`), proforma puis
  émise avec numéro `NNN/MAB/AA` sous verrou (décision 115).
- `Paiement` plafonné au total facturé, sous verrou de ligne (décision 32), `Recu` PDF.
- Remises, réservées aux honoraires (décision 64).
- `Formalite.montant_calcule` / `montant_paye` : ce que l'étude doit verser aux organismes.
- Journal d'activité (`journal_activites`), rôle **Comptable** transversal.

### Ce qui manque ou est fragile
| Constat | Mesure | Conséquence comptable |
|---|---|---|
| Honoraires et débours ne sont distingués **que** par `baremes.organisme = 'Notaire'` | 26 lignes sur 146 **sans barème** (5 220 000 GNF) : JAL ×14, RCCM ×7, DNSV ×4, « Debours » ×1 | impossible de savoir si ces lignes sont du chiffre d'affaires ou de l'argent de tiers |
| Le versement aux organismes n'est qu'**un nombre** (`formalites.montant_paye`) : ni date, ni moyen, ni pièce, ni auteur | 41 formalités sur 68 renseignées, 11 100 000 GNF | aucune trace de **sortie de caisse** ; pas de journal de caisse possible |
| L'étude avance des débours avant d'être payée | **7 dossiers sur 33** : versé aux organismes > encaissé du client | avance de trésorerie invisible aujourd'hui |
| Droits dus aux organismes sans contrepartie facturée | `VTE-2026-0001` : 65 450 000 GNF de formalités calculées, **0** débours facturé ; `HYP-2026-0001` : 6 000 000 versés, 0 facturé | perte sèche possible si rien ne le signale |
| Un paiement se **supprime** physiquement (`destroyPaiement` → `delete()`) | 0 suppression à ce jour (journal) | interdit en comptabilité : une écriture se contre-passe, elle ne s'efface pas |
| Pas d'avoir : une facture émise ne se modifie ni ne se régénère | — | aucune voie légale pour corriger une facture émise |
| Moyens de paiement en chaîne libre (`max:30`), liste seulement côté JS | 9 « espèces », 1 NULL | pas de rattachement à une caisse ou une banque |
| Aucune dépense de l'office (loyer, salaires, fournitures…) | — | le niveau B est absent |
| Montants en `decimal(…,2)` alors que le GNF n'a pas de subdivision en usage | — | à trancher une fois pour le grand livre |

---

## 2. Principes (règle 2 du CLAUDE.md appliquée à la compta)

1. **Grand livre en ajout seul.** Une écriture validée n'est jamais modifiée ni supprimée ; on
   la contre-passe. Garde-fou exécutable, pas convention.
2. **Toute écriture est équilibrée** (Σ débit = Σ crédit), vérifié en base *et* par test.
3. **Un seul générateur d'écritures** : `ComptabilisationService`, piloté par un enum
   `NatureOperation` et un `match` **exhaustif sans branche par défaut**. Un événement financier
   ajouté sans comptabilisation fait échouer le code.
4. **Le plan de comptes et la correspondance événement → comptes sont déclarés à un seul
   endroit**, marqués `a_verifier` tant que l'expert-comptable de l'étude ne les a pas validés.
   On ne devine pas le traitement fiscal guinéen.
5. **Numérotation chronologique continue** par journal et par exercice (exigence OHADA),
   attribuée sous verrou, comme `Facture::emettre()`.
6. **Période close = période figée**, à l'image de `DossierPolicy::estFige()`.
7. **Rapprochement exécutable** : Σ solde du compte clients = Σ soldes des factures émises ;
   Σ compte de tiers débours = débours facturés − débours reversés. Un test et une commande
   Artisan de contrôle.

---

## 3. Modèle de données cible

```
exercices            id, libelle, debut, fin, statut (ouvert|cloture), cloture_par, cloture_at
comptes              id, numero (SYSCOHADA), libelle, classe, type (bilan|gestion),
                     lettrable, actif, a_verifier
journaux             id, code (VTE, CAI, BQ1, OD, ACH, AN), libelle, compte_tresorerie_id?
comptes_tresorerie   id, type (caisse|banque|mobile_money), libelle, compte_id, iban/numero,
                     actif
ecritures            id, exercice_id, journal_id, numero (continu), date, libelle,
                     nature (NatureOperation), source_type/source_id (morph : facture,
                     paiement, decaissement, depense…), dossier_id?, contrepasse_id?,
                     valide_par, valide_at
lignes_ecriture      id, ecriture_id, compte_id, debit, credit, tiers_type/tiers_id
                     (client, société, fournisseur, organisme), dossier_id?, lettrage
decaissements        id, formalite_id?, dossier_id, organisme, date, montant,
                     compte_tresorerie_id, reference_quittance, piece (DocumentFichier),
                     enregistre_par_id, annule_par_decaissement_id?
avoirs               id, facture_id, numero (NNN/AV/AA), date, motif, montant, lignes…
fournisseurs         id, nom, nif, contact…
depenses             id, fournisseur_id?, categorie, compte_id, date, montant, tva?,
                     piece, statut (a_payer|payee), reglee_par…
arretes_caisse       id, compte_tresorerie_id, date, solde_theorique, solde_compte,
                     ecart, motif_ecart, arrete_par
releves_bancaires    id, compte_tresorerie_id, date, libelle, montant, ecriture_id? (rapproché)
```

Ajouts aux tables existantes :
- `baremes.nature` et `lignes_factures.nature` — enum `NatureLigne` : `honoraire | debours`
  (peut-être `frais` — question ouverte Q3). Copiée sur la ligne à la génération, comme
  `remise_autorisee` (décision 64). Remplace l'inférence par `organisme = 'Notaire'`.
- `paiements.compte_tresorerie_id`, `paiements.annule_at` / `annule_par_id` (annulation au lieu de
  suppression) ; `moyen_paiement` devient un enum PHP **avec miroir JS et test de parité**.
- `formalites.montant_paye` devient **dérivé** de `decaissements` (ou supprimé après migration).

Montants : `bigInteger` en GNF entiers pour `lignes_ecriture` est recommandé (pas d'erreurs
d'arrondi sur les sommes de contrôle) — à acter en décision ; sinon garder `decimal(15,2)` partout
par cohérence.

---

## 4. Correspondance événement → écriture (proposition, `a_verifier` en bloc)

| `NatureOperation` | Déclencheur existant / nouveau | Débit | Crédit |
|---|---|---|---|
| `EmissionFacture` | `Facture::emettre()` | 411 Clients (total) | 706 Honoraires (net de remise) · 4xx Compte de tiers débours · 443 TVA si applicable |
| `Encaissement` | `FactureController::enregistrerPaiement()` | 571 Caisse / 521 Banque | 411 Clients |
| `AnnulationEncaissement` | remplace `destroyPaiement` | contre-passation | |
| `DecaissementDebours` | nouveau, depuis une formalité | 4xx Compte de tiers débours | 571 / 521 |
| `Avoir` | nouveau | 706 / 4xx (selon nature des lignes) | 411 |
| `Depense` | nouveau | 6xx Charge (+ 445 TVA récupérable ?) | 401 Fournisseurs |
| `ReglementFournisseur` | nouveau | 401 | 571 / 521 |
| `VirementInterne` | caisse → banque | 521 | 571 |
| `EcartCaisse` | arrêté de caisse | 658 / 758 | 571 |
| `ANouveaux` | ouverture d'exercice / reprise | soldes d'ouverture | |

Les débours sont proposés en **compte de tiers** et non en chiffre d'affaires : l'étude ne fait
que transiter l'argent de l'État et des organismes (règle déjà écrite au manuel, § 6 bis).
Le numéro exact (467, 4711…) est à faire valider.

---

## 5. Lots d'implémentation

Chaque lot est livrable seul, testé, et rejoint le devbook (tableau des décisions).

### Lot 0 — Cadrage avec l'étude (bloquant, pas de code)
Réponses aux questions du § 7. Sans Q1 et Q2, les lots 3+ ne doivent pas commencer.

### Lot 1 — Nature des lignes et fiabilisation des encaissements *(fondation, ~1 semaine)*
- Enum `NatureLigne`, colonnes `baremes.nature` / `lignes_factures.nature`, migration :
  `organisme = 'Notaire'` → honoraire, le reste → débours ; les **26 lignes manuelles** classées
  par libellé là où c'est sûr (JAL, RCCM, DNSV → débours), sinon `NULL` + écran de classement.
  Une facture ne s'émet pas avec une ligne sans nature (blocage énuméré).
- Formulaire « ligne manuelle » : nature obligatoire.
- Enum `MoyenPaiement` + miroir JS + `ParitéMoyensPaiementTest` ; le NULL existant reste lisible.
- Suppression de paiement → **annulation** (motif obligatoire, reçu marqué « annulé »).
- Facture : sous-totaux honoraires / débours sur l'écran et le PDF (à valider avec l'étude :
  le modèle actuel ne les sépare pas).
- Tests : nature copiée et figée, émission refusée sans nature, annulation au lieu de suppression.

### Lot 2 — Débours : de la formalité à la sortie de caisse *(~1 semaine)*
- Table `decaissements`, action « Payer l'organisme » sur la formalité (date, montant, moyen,
  quittance jointe — le reçu de l'organisme devient une pièce GED).
- Migration des 41 `montant_paye` existants en décaissements historiques, date = `depose_at` ou
  `retour_at`, marqués `reprise` (la date réelle est inconnue : le dire, pas l'inventer).
- **Compte du dossier** (onglet Facturation → « Situation financière ») : facturé honoraires /
  débours, encaissé, débours reversés, provision disponible, avance de l'étude.
- Signaux (blocants ou alertes, à décider Q6) :
  - débours à verser > débours facturés (cas `VTE-2026-0001`, `HYP-2026-0001`) ;
  - décaissement supérieur à la provision encaissée (7 dossiers aujourd'hui) ;
  - Clôture du dossier : rubrique « finances soldées » dans l'inventaire de clôture
    (`RubriqueCloture` — `match` exhaustif, donc à étendre en même temps).

### Lot 3 — Trésorerie *(~1,5 semaine)*
- `comptes_tresorerie` (caisse principale, banques, mobile money) dans Paramètres.
- Paiement et décaissement rattachés à un compte de trésorerie.
- **Brouillard de caisse** du jour + **arrêté de caisse** (solde compté vs théorique, écart motivé).
- Virements internes caisse ↔ banque.
- Import de relevé bancaire (CSV) et **rapprochement** semi-automatique (montant + date ± n jours).
- Écran Trésorerie : soldes par compte, mouvements, export.

### Lot 4 — Moteur comptable (comptabilité générale) *(~2 semaines)*
- Tables `exercices`, `comptes` (plan SYSCOHADA réduit, seedé, `a_verifier`), `journaux`,
  `ecritures`, `lignes_ecriture`.
- `ComptabilisationService` + `NatureOperation` (`match` exhaustif) ; appel **dans la même
  transaction** que l'événement métier (émission, paiement, décaissement…) : pas d'événement sans
  écriture, pas d'écriture orpheline.
- Numérotation continue sous verrou ; contre-passation ; interdiction d'UPDATE/DELETE sur une
  écriture validée (modèle + policy + test).
- **Reprise** : commande `compta:reprendre` qui génère les écritures des 25 factures, 10
  paiements et des décaissements historiques dans un exercice d'ouverture, idempotente, avec
  rapport de contrôle (totaux avant/après).
- Commande `compta:verifier` : équilibre, continuité de numérotation, rapprochement 411 ↔ factures,
  tiers ↔ débours.
- Saisie manuelle d'écritures d'OD (réservée comptable/admin), équilibre imposé.

### Lot 5 — Dépenses de l'office et fournisseurs *(~1 semaine)*
- `fournisseurs`, `depenses` (catégories → comptes de charge), pièce justificative obligatoire,
  statut à payer / payée, règlement depuis un compte de trésorerie.
- Charges récurrentes (loyer, abonnements) en modèle.
- Salaires : **hors périmètre** sauf réponse contraire à Q5 (on comptabilise le virement global,
  pas la paie).

### Lot 6 — Avoirs *(~0,5 semaine)*
- Avoir total ou partiel sur facture émise, numérotation propre, PDF, écriture inverse ;
  remboursement client = décaissement. Résout l'impasse actuelle « facture émise non corrigible ».

### Lot 7 — États et exports *(~1,5 semaine)*
- Journaux, grand livre, balance générale, balance âgée clients (relances), état des débours
  en attente de versement, tableau de bord (CA honoraires du mois, encaissements, trésorerie,
  impayés, avances de l'étude).
- Exports Excel/CSV + **export au format du logiciel de l'expert-comptable** (Q4).
- PDF via dompdf, comme les reçus et notes de frais (décision 47).

### Lot 8 — Clôture d'exercice *(~1 semaine)*
- Verrouillage de période, contrôle préalable (`compta:verifier` vert, caisse arrêtée, banques
  rapprochées), report à nouveau, ouverture de l'exercice suivant.
- États financiers SYSCOHADA (bilan, compte de résultat) : **seulement si Q4 dit que l'étude ne
  passe pas par un expert-comptable** — sinon l'export suffit et on évite de refaire un logiciel
  comptable certifié.

**Total indicatif : 9 à 11 semaines** pour A + B complets. Les lots 1–3 (≈ 3,5 semaines) apportent
déjà l'essentiel de la valeur quotidienne et ne dépendent pas des réponses fiscales.

---

## 6. Garde-fous à écrire (tests bloquants)

| Test | Ce qu'il interdit |
|---|---|
| `EquilibreEcrituresTest` | une écriture déséquilibrée, quel que soit l'événement |
| `ImmutabiliteEcrituresTest` | modifier/supprimer une écriture validée ou une ligne d'une période close |
| `CouvertureComptabilisationTest` | un cas de `NatureOperation` sans comptabilisation ; un paiement/décaissement/émission sans écriture |
| `NumerotationContinueTest` | un trou ou un doublon dans un journal (y compris en concurrence) |
| `RapprochementClientsTest` | Σ 411 ≠ Σ soldes des factures émises |
| `ParitéNaturesEtMoyensTest` | divergence enum PHP ↔ miroir JS (`NatureLigne`, `MoyenPaiement`) |
| `NatureLigneFigeeTest` | changer la nature d'un barème modifie une facture émise |
| `ProvisionDossierTest` | calcul de la situation financière du dossier |

Plus les contrôles transverses habituels : isolement par rôle (`gererComptabilite` distinct de
`gererFacturation`), journal d'activité sur chaque action, dossier clôturé figé.

---

## 7. Questions à poser à l'étude (avant le lot 3)

| # | Question | Règle provisoire `a_verifier` |
|---|---|---|
| Q1 | Périmètre : comptabilité des dossiers seule, ou comptabilité générale complète de l'office ? | A puis B, par lots |
| Q2 | L'étude a-t-elle un expert-comptable, et quel logiciel (Sage, Ciel, Excel…) ? Format d'import ? | export CSV générique |
| Q3 | Les honoraires sont-ils soumis à la TVA (taux) ? Existe-t-il d'autres natures que honoraire / débours (frais de dossier, copies, déplacements) ? | pas de TVA, deux natures — comme les notes actuelles |
| Q4 | Les états financiers (bilan, compte de résultat) doivent-ils sortir de l'application ? | non, l'expert-comptable les établit |
| Q5 | Dépenses de l'office et salaires : à gérer ici ? | dépenses oui, paie non |
| Q6 | Avancer des débours sans provision : interdit, alerte, ou toléré ? Le paiement d'un organisme exige-t-il l'encaissement préalable ? | alerte non bloquante |
| Q7 | Les fonds des clients transitent-ils par un **compte séquestre** distinct ? Obligations de la Chambre des notaires de Guinée ? | un compte de tiers distinct dans le plan |
| Q8 | Combien de caisses et de comptes bancaires ? Mobile money (Orange Money…) ? | une caisse + banques paramétrables |
| Q9 | Date de démarrage et soldes d'ouverture (reprise de la comptabilité antérieure) ? | exercice civil, soldes saisis en à-nouveaux |
| Q10 | Paiements partiels d'un client répartis sur plusieurs dossiers ? Devises autres que le GNF ? | un paiement = une facture, GNF seul |
| Q11 | Faut-il un circuit de validation (le comptable saisit, le notaire valide) ? | validation directe par le comptable |

---

## 8. Ce qui est délibérément laissé de côté
- La paie, les immobilisations et amortissements détaillés (export vers l'expert-comptable).
- Le paiement en ligne par le client.
- La déclaration fiscale électronique.
