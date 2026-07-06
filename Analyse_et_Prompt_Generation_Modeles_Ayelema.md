# Génération automatique des modèles documentaires — Office Notarial Ayelama BAH

Ce document contient (A) l'analyse globale des modèles fournis, (B) le dictionnaire de données unifié, (C) le mapping modèle → champs, (D) les barèmes de facturation, (E) l'approche technique recommandée, et (F) **le prompt final** à utiliser pour implémenter la génération des documents à partir d'un formulaire.

---

## A. Analyse globale

### A.1 Constat structurant
Les modèles fournis **ne contiennent aucun champ de fusion**. Les emplacements à remplir sont matérialisés de **trois façons hétérogènes** :
1. des **pointillés** : `…………`, `....`, `J/M/A` ;
2. des **mots en MAJUSCULES** servant de marqueurs : `NOM DE LA SOCIETE`, `PRENOM ET NOM`, `MONTANT DU CAPITAL EN LETTRE (MONTANT DU CAPITAL EN CHIFFRE)`, `QUARTIER, COMMUNE, VILLE` ;
3. des **instructions entre parenthèses** : `(JOUR/MOIS/AN)`, `(Type de garantie à préciser)`, `(Description du bien vendu)`.

**Conséquence directe :** avant toute génération automatique, chaque modèle doit être **normalisé** une seule fois, en remplaçant ces marqueurs par des balises uniformes `${nom_du_champ}` (compatibles PHPWord `TemplateProcessor`). C'est l'étape préalable n°1 du projet. Le dictionnaire en section B fournit la nomenclature cible.

### A.2 Inventaire (47 modèles Word, 5 questionnaires, 11 factures)

**Constantes de l'Office** (présentes dans presque tous les actes, donc *jamais* dans le formulaire — à stocker une fois en configuration) : Maître **Ayelama BAH**, Notaire, charge **n°21**, résidence **Ratoma**, **Nongo, 3ᵉ étage, Immeuble VISTA BANK**, **BP 2668/2868**, Commune de Ratoma/Lambanyi, tél **622 49 69 44 / 664 20 96 07 / 655 61 38 38**, email **ayelama.bah@notaire-guinee.com**. Cadre juridique : **OHADA — Guinée** (Acte Uniforme sociétés, Code Foncier et Domanial, GNF).

**Packs disponibles par forme de société** — SARL, SARLU, SAS, SASU, chacun avec : `PAGE DE GARDE`, `STATUTS`, `DNSV`, `ATTESTATION DE DÉPÔT DE CAPITAL`, `DÉCLARATION SUR L'HONNEUR`, `RCCM` (formulaire), `INSERTION` (avis JAL), `FACTURE`.
→ **SA et GIE** : seuls les **questionnaires** existent (pas encore de pack d'actes) — à compléter ultérieurement.

**Vente d'immeuble** — deux variantes : **avec titre foncier** (`CONTRAT DE VENTE`, `PAGE DE GARDE`, `TABLEAU DE BORDEREAU`, `FACTURE`, `FACTURE PLUS-VALUE`) et **sans titre foncier** (`CONTRAT DE VENTE`, `PAGE DE GARDE`, `FACTURE`, `FACTURE PLUS-VALUE`).

**Baux** — `CONTRAT DE BAIL À HABITATION` (+ facture) et `BAIL À CONSTRUCTION` (+ facture). Le bail professionnel suit le même gabarit que l'habitation (catégorie « Bail Louer » du CR).

**Courriers de transmission (13)** — transmission minute, transmission copie authentique société, transmission vente immeuble, transmission modification société, transmission modification avec acte de dépôt du PV, transmission mainlevée d'hypothèque, réquisition conservation foncière, accusé de réception banque, accusé de réception société, accusé de réception contrat de prêt immobilier, contrat de prêt à la banque, mise en place de crédit.

**Questionnaires (5)** — SARL, SAS, SA, GIE, Fiche de réception vente. Ils définissent **exactement** les champs des formulaires de saisie (voir section B).

### A.3 Blocs de données réutilisables (le cœur du modèle)
La quasi-totalité des actes se compose des **mêmes briques** répétées. On modélise donc des **blocs réutilisables** plutôt que des champs par document :
`Office` (constant) · `Personne physique` · `Personne morale` · `Société` · `Bien immobilier` · `Banque` · `Bail` · `Courrier` · `Facture`. Un acte = une **combinaison de blocs**. C'est ce qui permet de saisir une donnée **une seule fois** et de la diffuser dans tous les documents du dossier.

---

## B. Dictionnaire de données unifié (nomenclature des balises `${}`)

> Convention : `snake_case`, minuscules. Tout montant/quantité existe en **deux variantes** : `_chiffres` et `_lettres` (la conversion chiffres→lettres est automatique, voir E.4). Les dates sont fournies en `_jma` (JJ/MM/AAAA) et, si l'acte l'exige, en toutes lettres `_lettres`.

### Bloc PERSONNE PHYSIQUE (associé, gérant, vendeur, acquéreur, bailleur, preneur, emprunteur…)
`civilite` (M./Mme) · `prenom_nom` · `demeurant_ville` · `quartier` · `commune` · `pays` (déf. République de Guinée) · `ne_a` · `date_naissance` · `nationalite` (déf. Guinéenne) · `piece_type` (Passeport / CNI CEDEAO) · `piece_numero` · `piece_delivree_le` · `piece_delivree_a` · `piece_expire_le` · `situation_matrimoniale` · `regime_matrimonial` (séparation/communauté — vente) · `telephone` · `email`
Pièces jointes attendues : `cni`, `certificat_residence`, `photos` (2), `casier_judiciaire`.

### Bloc PERSONNE MORALE (associé personne morale)
`denomination` · `forme` · `rccm` · `representant_legal` · `siege` · (pièces : statuts, déclaration immatriculation RCCM).

### Bloc SOCIÉTÉ
`denomination` · `forme` (SARL/SARLU/SAS/SASU/SA/GIE) · `sigle` · `capital_chiffres` · `capital_lettres` · `nombre_parts` (ou `nombre_actions`) · `valeur_nominale_chiffres` · `valeur_nominale_lettres` · `siege_quartier` · `siege_commune` · `siege_ville` · `email_societe` · `telephone_societe` · `objet_social` (liste de secteurs) · `duree` (déf. 99 ans) · `exercice_social` (1ᵉʳ janv → 31 déc) · `date_acte_jma` · `annee_lettres` (« DEUX MILLE VINGT-SIX ») · **direction selon la forme** : `gerant(s)` (SARL/SARLU), `president` + `directeur_general` + `dga` (SAS/SASU), `pca` + `administrateurs[]` + `directeur_general` (SA), `administrateurs[]` (GIE) · `commissaire_titulaire` · `commissaire_suppleant` (facultatifs) · `repartition_capital[]` (associé → nombre de parts/actions → montant) · `rccm_numero` · `nif` · `jal_journal`.
Associés : **liste** `associes[]` (chaque entrée = bloc Personne physique **ou** Personne morale).

### Bloc BIEN IMMOBILIER
`parcelle_numero` · `lot_numero` · `lieu_de` · `nature_terrain` (urbain bâti / nu) · `usage` (habitation / commercial / mixte) · `superficie_m2` · `pcp` (plan de codification parcellaire) · `titre_foncier_numero` · `tf_date_jma` · `livre_foncier_ville` · `tf_volume` · `tf_folio` · `tf_annee` · `limites_ne` · `limites_so` · `limites_se` · `limites_no` (bail à construction) · `origine_propriete` · `prix_vente_chiffres` · `prix_vente_lettres`.

### Bloc BANQUE / HYPOTHÈQUE
`banque_denomination` · `banque_forme` · `banque_quartier` · `banque_commune` · `banque_ville` · `montant_credit_chiffres` · `montant_credit_lettres` · `type_garantie` (affectation hypothécaire…) · `rang_hypothecaire` (1ᵉʳ…) · TF garanti : réutilise le bloc Bien immobilier (`titre_foncier_numero`, etc.).

### Bloc BAIL
`type_bail` (habitation / professionnel / à construire) · `duree_bail` · `date_prise_effet` · `loyer_chiffres` · `loyer_lettres` · `periodicite_loyer` · `destination_bien` · `engagement_construction` (R+n, description — bail à construire).

### Bloc COURRIER (transmission / réquisition / accusé)
`courrier_date_jma` · `courrier_lieu` (Conakry/Kindia…) · `destinataire_civilite` · `destinataire_nom` · `destinataire_fonction` (Directeur Général, Conservateur Foncier…) · `destinataire_organisation` · `destinataire_adresse` (quartier/commune/ville) · `nref` (`AB/N/CD/N°…/année`) · `objet` · `dossier_intitule` · `pieces_transmises[]` (liste cochable) · `liste_tf[]` (réquisition : plusieurs titres fonciers).

### Bloc FACTURE / NOTE DE FRAIS
`note_numero` (`…/MAB/26`) · `note_date_jma` · `compte_numero` · `objet` · `assiette_chiffres` (prix de vente / montant des loyers / capital) · `lignes[]` (désignation, quantité, montant) · `total_chiffres` · `total_lettres`. Barèmes : section D.

---

## C. Mapping modèle → blocs/champs consommés

| Catégorie | Modèle | Blocs / champs principaux |
|---|---|---|
| **Société (×4 formes)** | PAGE DE GARDE | Société (denomination, forme, capital, siège) |
| | STATUTS | Société + `associes[]` + objet + direction + date_acte |
| | DNSV | Société + `associes[]` + capital/parts (chiffres & lettres) + versement |
| | ATTESTATION DE DÉPÔT | Société (denomination, forme) + `capital_chiffres/lettres` + date |
| | DÉCLARATION SUR L'HONNEUR | Personne physique (gérant/associé) |
| | RCCM (formulaire) | Société (denomination, siège, forme, capital, durée, activités) + dirigeants |
| | INSERTION (avis JAL) | Société complète + gérance + RCCM + tribunal |
| | FACTURE SOCIÉTÉ | Facture (forfait + APIP/RCCM + JAL + enregistrement) |
| **Vente avec TF** | CONTRAT DE VENTE | Vendeur + Acquéreur (Pers. phys.) + Bien (avec TF) + prix |
| | PAGE DE GARDE | TF + livre foncier + vendeur + acquéreur |
| | TABLEAU DE BORDEREAU | Conservation + TF + vendeur + acquéreur + bien + prix |
| | FACTURE / FACTURE PLUS-VALUE | Facture vente (5%/2%/2%) ; plus-value (15%) |
| **Vente sans TF** | CONTRAT DE VENTE | Vendeur + Acquéreur + Bien (parcelle/lot, sans TF) + origine propriété |
| | PAGE DE GARDE / FACTURES | idem variante TF, sans données TF |
| **Bail habitation / pro** | CONTRAT DE BAIL | Bailleur + Preneur + Bien + Bail (durée, loyer, destination) |
| | FACTURE | Facture bail (2% honoraires + 2% enregistrement sur loyers) |
| **Bail à construction** | BAIL À CONSTRUCTION | Bailleur + Preneur + Bien (+ limites) + engagement construction |
| **Courriers (×13)** | tous | Bloc Courrier + (Société / Banque / Bien / Personne) selon l'objet |

---

## D. Barèmes de facturation (extraits des factures — à valider par Maître)

- **Vente d'immeuble** : Honoraires **5 %** du prix · Droits d'enregistrement **2 %** · Frais de mutation Conservation Foncière **2 %** · Frais de virement **500 000 GNF** · Timbres fiscaux & rôles selon nombre de pages.
- **Plus-value immobilière** : Taxe **15 %** du montant de la plus-value.
- **Bail** : Honoraires **2 %** et Droits d'enregistrement **2 %** du montant total des loyers (sur la durée).
- **Société (SARL témoin)** : Honoraires **forfaitaires** (ex. 4 500 000) · **Frais APIP + RCCM 390 000** · **Insertion JAL 250 000** · Droits d'enregistrement Statuts + DNSV · Timbres & rôles.
- **Hypothèque** (issu du compte-rendu, pas des factures) : Conservation Foncière **1,5 %** du montant · Impôts **0,10 %**.

> ⚠️ Points à confirmer : le taux Conservation Foncière en **vente** vaut **2 %** dans la facture alors que le compte-rendu le laissait « à vérifier » ; les **honoraires société** sont forfaitaires et varient selon le capital. Centraliser ces taux dans une **table de barèmes paramétrable** (et non en dur).

---

## E. Approche technique recommandée (Laravel + React/Inertia)

**E.1 Normalisation des modèles (préalable, une fois).** Convertir chaque `.doc` en `.docx`, puis remplacer chaque marqueur par une balise `${champ}` issue du dictionnaire B. Les répétitions (listes d'associés, pièces transmises, lignes de facture, titres fonciers multiples) deviennent des **blocs clonables** PHPWord : `${bloc_associe}…${/bloc_associe}`.

**E.2 Moteur de génération.** Utiliser **PhpOffice\PhpWord `TemplateProcessor`** :
`setValue()` pour les champs simples, `cloneBlock()` / `cloneRowAndSetValues()` pour les listes, `saveAs()` pour produire le `.docx`. Conversion `.docx → .pdf` optionnelle via LibreOffice headless.

**E.3 Modèle de données (tables).** `clients` (personnes physiques/morales) · `dossiers` (catégorie, type d'acte, statut workflow) · `societes` · `biens_immobiliers` · `banques` · `parties` (pivot dossier↔client avec rôle : vendeur/acquéreur/associé/gérant…) · `documents` (modèle utilisé, statut, fichier généré) · `modeles` (fichier `.docx` normalisé + liste des champs requis) · `baremes` (taux paramétrables) · `factures` + `lignes_facture`.

**E.4 Conversion montants en lettres.** Indispensable (capital, prix, total facture, années). Prévoir un service `NombreEnLettres` (français, accord « francs guinéens »), alimentant systématiquement les champs `_lettres`.

**E.5 Formulaires dynamiques (React/Inertia + shadcn).** Un schéma de formulaire **par type d'acte**, dérivé des questionnaires PDF (section B). Saisie des blocs réutilisables une seule fois ; le système en déduit la **liste des documents à générer** et les remplit tous d'un coup (« pack du dossier »).

---

## F. PROMPT FINAL — à copier dans ton outil de build (Claude Code / Claude)

> Colle ce bloc tel quel. Il suppose que tu disposes des modèles `.docx` du dossier « Documents reçus » et du présent dictionnaire de données.

```
CONTEXTE
Je développe une application de gestion d'actes pour l'Office Notarial Maître Ayelama BAH (OHADA – Guinée, GNF).
Stack : Laravel 13, React + Inertia, shadcn/ui, Tailwind, Framer Motion, lucide-react.
Objectif : générer automatiquement les documents notariés (.docx, et PDF en option) d'un dossier
en remplissant UN formulaire dynamique pendant le traitement du dossier. Une donnée saisie une fois
doit alimenter tous les documents concernés.

DONNÉES D'ENTRÉE
- Des modèles Word (.docx) existants, regroupés par catégorie : Sociétés (SARL, SARLU, SAS, SASU :
  Page de garde, Statuts, DNSV, Attestation de dépôt, Déclaration sur l'honneur, RCCM, Insertion JAL, Facture),
  Vente avec/sans titre foncier (Contrat de vente, Page de garde, Tableau de bordereau, Factures),
  Baux (habitation/professionnel, à construction + factures), et 13 Courriers de transmission.
- Ces modèles n'ont PAS de champs de fusion : les trous sont des pointillés « … », des mots en
  MAJUSCULES (NOM DE LA SOCIETE, PRENOM ET NOM, MONTANT DU CAPITAL EN LETTRE/CHIFFRE…) et des
  instructions entre parenthèses ((JOUR/MOIS/AN), (Type de garantie à préciser)…).
- Les constantes de l'Office (notaire, charge n°21, adresse Ratoma/Nongo/VISTA BANK, BP, tél, email)
  ne sont JAMAIS saisies : elles viennent d'une config unique.

CE QUE JE VEUX QUE TU FASSES
1) NORMALISER les modèles : remplacer chaque marqueur par une balise ${snake_case} compatible
   PhpOffice\PhpWord TemplateProcessor, en suivant le DICTIONNAIRE ci-dessous. Transformer toute
   répétition (associés, pièces transmises, lignes de facture, titres fonciers multiples) en blocs
   clonables ${bloc_x}…${/bloc_x}. Livrer les modèles normalisés + la liste des champs requis par modèle.

2) MODÈLE DE DONNÉES (migrations Laravel) :
   clients (personne physique|morale), dossiers (catégorie, type_acte, statut_workflow),
   societes, biens_immobiliers, banques, parties (pivot dossier↔client + rôle), documents
   (modele_id, statut, chemin_fichier), modeles (fichier_docx, champs_requis[]), baremes (taux paramétrables),
   factures + lignes_facture. Relations et seeders inclus.

3) MOTEUR DE GÉNÉRATION (service Laravel) basé sur PhpWord\TemplateProcessor :
   setValue / cloneBlock / cloneRowAndSetValues / saveAs ; conversion .docx→.pdf via LibreOffice (option).
   Inclure un service NombreEnLettres (français + « francs guinéens ») pour remplir tous les champs _lettres
   (capital, prix, loyers, total facture, année de l'acte).

4) FORMULAIRES DYNAMIQUES (React/Inertia + shadcn) : un schéma par type d'acte, dérivé des questionnaires
   (champs ci-dessous). Saisie des blocs réutilisables (personne, société, bien, banque, bail, facture).
   À la validation, l'app DÉDUIT la liste des documents à produire selon la catégorie/type, les génère tous
   (« pack du dossier ») et les attache au dossier avec leur statut.

5) FACTURATION : calculer les lignes via une table de barèmes PARAMÉTRABLE (pas de taux en dur) :
   Vente → honoraires 5%, enregistrement 2%, mutation conservation 2%, virement 500 000, plus-value 15% ;
   Bail → honoraires 2% + enregistrement 2% sur total des loyers ;
   Société → honoraires forfaitaires (selon capital), APIP+RCCM 390 000, JAL 250 000, enregistrement statuts+DNSV ;
   Hypothèque → conservation 1,5%, impôts 0,10%. (Tous ces taux modifiables en base.)

DICTIONNAIRE DES BALISES (blocs réutilisables)
- PERSONNE PHYSIQUE : ${pp.civilite} ${pp.prenom_nom} ${pp.demeurant_ville} ${pp.quartier} ${pp.commune}
  ${pp.pays} ${pp.ne_a} ${pp.date_naissance} ${pp.nationalite} ${pp.piece_type} ${pp.piece_numero}
  ${pp.piece_delivree_le} ${pp.piece_delivree_a} ${pp.piece_expire_le} ${pp.situation_matrimoniale}
  ${pp.regime_matrimonial} ${pp.telephone} ${pp.email}
- PERSONNE MORALE : ${pm.denomination} ${pm.forme} ${pm.rccm} ${pm.representant_legal} ${pm.siege}
- SOCIÉTÉ : ${soc.denomination} ${soc.forme} ${soc.sigle} ${soc.capital_chiffres} ${soc.capital_lettres}
  ${soc.nombre_parts} ${soc.valeur_nominale_chiffres} ${soc.valeur_nominale_lettres} ${soc.siege_quartier}
  ${soc.siege_commune} ${soc.siege_ville} ${soc.email_societe} ${soc.objet_social} ${soc.duree}
  ${soc.exercice_social} ${soc.date_acte_jma} ${soc.annee_lettres} ${soc.rccm_numero} ${soc.nif} ${soc.jal_journal}
  + direction selon forme (gerants[] / president, directeur_general, dga / pca, administrateurs[], directeur_general)
  + ${soc.commissaire_titulaire} ${soc.commissaire_suppleant} + bloc clonable associes[] + repartition_capital[]
- BIEN IMMOBILIER : ${bien.parcelle_numero} ${bien.lot_numero} ${bien.lieu_de} ${bien.nature_terrain}
  ${bien.usage} ${bien.superficie_m2} ${bien.pcp} ${bien.titre_foncier_numero} ${bien.tf_date_jma}
  ${bien.livre_foncier_ville} ${bien.tf_volume} ${bien.tf_folio} ${bien.tf_annee}
  ${bien.limites_ne} ${bien.limites_so} ${bien.limites_se} ${bien.limites_no} ${bien.origine_propriete}
  ${bien.prix_vente_chiffres} ${bien.prix_vente_lettres}
- BANQUE/HYPOTHÈQUE : ${bq.denomination} ${bq.forme} ${bq.quartier} ${bq.commune} ${bq.ville}
  ${bq.montant_credit_chiffres} ${bq.montant_credit_lettres} ${bq.type_garantie} ${bq.rang_hypothecaire} (+ bloc Bien pour le TF garanti)
- BAIL : ${bail.type} ${bail.duree} ${bail.date_prise_effet} ${bail.loyer_chiffres} ${bail.loyer_lettres}
  ${bail.periodicite} ${bail.destination_bien} ${bail.engagement_construction}
- COURRIER : ${cr.date_jma} ${cr.lieu} ${cr.destinataire_civilite} ${cr.destinataire_nom}
  ${cr.destinataire_fonction} ${cr.destinataire_organisation} ${cr.destinataire_adresse} ${cr.nref}
  ${cr.objet} ${cr.dossier_intitule} + blocs clonables pieces_transmises[] et liste_tf[]
- FACTURE : ${fac.note_numero} ${fac.note_date_jma} ${fac.compte_numero} ${fac.objet}
  ${fac.assiette_chiffres} + bloc clonable lignes[] (designation, quantite, montant) + ${fac.total_chiffres} ${fac.total_lettres}

CHAMPS DES FORMULAIRES (dérivés des questionnaires de l'Office)
- SOCIÉTÉ (SARL/SARLU/SAS/SASU/SA/GIE) : dénomination, siège, email, capital, répartition entre associés,
  objet social (secteurs), gérant(s)/président/DG/administrateurs selon forme, commissaire(s) aux comptes
  (facultatif, OBLIGATOIRE en SAS si >50% détenu par des sociétés ; SA: min capital 140 000 000, libération 1/4),
  pièces par associé (CNI/passeport, certificat de résidence, 2 photos, situation matrimoniale, tél, email ;
  casier judiciaire pour dirigeants), JAL.
- VENTE (fiche de réception vente) : entretien (date/canal), vendeur(s), acquéreur(s) (identité, adresse, email, tél),
  objet, bien (titre de propriété/TF, quittances, bail éventuel), prix de vente, provision, taxe plus-value.
- BAIL : bailleur, preneur, bien, type de bail, durée, date de prise d'effet, loyer, destination (+ construction pour bail à construire).
- HYPOTHÈQUE/COURRIERS : banque, emprunteur, type de garantie, rang, TF garanti, objet et pièces du courrier.

EXIGENCES
- Code propre, typé, testé (au moins le moteur de génération et NombreEnLettres).
- Aucune donnée de l'Office en dur dans les modèles autre que via config.
- Génération « pack » : un clic produit tous les documents requis du dossier.
- Prévoir SA et GIE comme formes existantes mais SANS pack d'actes complet (à compléter), avec un message clair.
Commence par (1) la normalisation d'un pack société (SARL) et de la vente avec TF comme preuves de concept,
puis généralise.
```

---

## G. Étapes suivantes que je peux réaliser pour toi
1. **Normaliser réellement** les modèles `.docx` (remplacer les `…`/MAJUSCULES par les balises `${}`) et te livrer les fichiers prêts pour PhpWord.
2. Générer les **migrations + seeders Laravel** correspondant au modèle de données.
3. Écrire le **service de génération** (PhpWord + NombreEnLettres) avec un exemple de bout en bout sur un dossier SARL.
