# Suivi — Retours d'usage de novembre 2026

Liste transmise par l'étude le 2026-11-16 : 23 éléments, numérotés R01 à R23.

Le plan détaillé, par lots, vit ici. Les décisions structurelles qui en sortent rejoignent le tableau des décisions de [devbook.md](../devbook.md) (§8, à partir de #108).

## Légende et règle

| Statut | Sens |
|---|---|
| ⬜ | À faire |
| 🔄 | En cours |
| ❓ | Bloqué par une question à l'étude. La règle provisoire est déclarée à un seul endroit et marquée `a_verifier` |
| ✅ | Fait **et prouvé** |

> **Un élément ne passe à ✅ qu'avec sa preuve** : le nom d'un test vert, ou une vérification réelle datée (page chargée et inspectée, document généré puis ouvert). « La construction passe » n'est pas une preuve (CLAUDE.md, Règle 2).

Ordre d'exécution : Lot 1 → 2 → 4 → 3 → 5 → 6 → 7 → 8 → 9 → 10. Le lot 4 passe avant le lot 3 parce que son défaut est signalé en production.

## Arbitrages déjà rendus (2026-11-16)

- **R01** : la carte « Clients du dossier » est retirée pour **SOC-MOD et SOC-DIS**. Elle reste pour la constitution et les autres actes.
- **R12** : le « bouton d'accès rapide » est le bouton d'en-tête de la fiche dossier, « Voir la certification / Voir les formalités / Voir l'expédition ».
- **R13 / R16** : le séparateur de milliers est le **point, partout dans les documents** (10.000.000). Les écrans ne changent pas.
- **R20** : la proforma est un **statut de la facture**. Elle naît proforma, sans numéro, et l'action « Émettre » lui attribue son numéro `NNN/MAB/AA`.

---

## Tableau de bord

| ID | Retour (énoncé de l'étude) | Lot | Statut | Preuve |
|---|---|---|---|---|
| R01 | Retirer « ajouter le client » dans les modifications | 1 | ✅ | Assistant chargé (2026-11-16) : carte absente en Modification et Dissolution, présente en SARLU. 0 partie à qualité libre en base sur SOC-MOD/SOC-DIS |
| R02 | Contrôle sur la date de l'assemblée et l'heure | 3 | ✅ | `NatureDecisionTest`, `CoherenceDonneesTest` ; assistant chargé (2026-11-16) : champ « Heure » natif (10:30), assemblée du 01/09/2026 sur AFG SARLU (constituée le 29/09) signalée « ne peut pas précéder la constitution » |
| R03 | Champ « Parts présentes ou représentées » à revoir | 3 | ✅ | `CoherenceDonneesTest` (plafond + parité) ; assistant chargé : 100 prérempli, « sur 100 parts (100 %) », 120 signalé « ne peuvent dépasser le nombre de parts » |
| R04 | Cocher « Quorum atteint » par défaut | 1 | ✅ | Assistant chargé (2026-11-16) : case cochée en Modification, rangée dans la carte « Assemblée générale » |
| R05 | La nature de la décision peut-elle être déterminée en fonction de la société ? | 3 | ✅ | `NatureDecisionTest` ; assistant chargé : AFG SARLU rattachée → « Décision de l'associé unique » préremplie |
| R06 | Vérifier les pièces justificatives des personnes ajoutées, « parfois ça passe pas » | 4 | ✅ | 3 causes établies et corrigées (motif non servi, pièces du représentant jamais proposées, lignes de bloc décalées sans leurs pièces) ; `PiecesRequisesParRoleTest` (parité JS↔serveur sur tout rôle × nature × motif), `PiecesCreationDossierTest`, payload rejoué dans la page ; assistant chargé (2026-11-16) : « Procuration » et « CNI du représentant » proposées |
| R07 | Édition des actes : retirer régénération et suppression pour ceux qui n'y ont pas droit | 2 | ✅ | `DocumentDroitsTest` (11 tests verts) ; fiche SOC-2026-0001 chargée (2026-11-16) : l'admin voit Supprimer, le certificateur ne le voit pas ; Courriers : drapeau servi par la policy |
| R08 | Un client ou une partie existante sélectionnée à la création doit apporter ses fichiers | 4 | ✅ | Reprise d'office côté serveur (`PiecesCreationDossierTest`, 5 tests) ; assistant chargé (2026-11-16) : client existant rattaché → « déjà fournie le 05/11/2026 (SOC-2026-0025) · reprise à la création » |
| R09 | Étape Signature : contrôler la date de signature | 5 | ✅ | `SignatureDatesTest` (5 tests). Écran vérifié le 2026-11-16 sur un dossier de test temporaire (supprimé ensuite) : le 17/11 affiche « …ne peut pas être dans le futur », le 15/11 affiche « …ne peut pas précéder la création du dossier (16/11/2026) », et « Enregistrer » reste désactivé ; le 16/11 est enregistré et journalisé |
| R10 | Retirer tous les boutons de régénération | 2 | ✅ | Route `documents.regenerer` supprimée (testé) ; aucun bouton « Régénérer » sur la fiche ; un courrier ne se génère qu'une fois (422 ensuite) |
| R11 | Facturation : date, et personne qui expédie en champ texte (utilisateur connecté par défaut) | 8 | ✅ | `FactureProformaTest` ; écran : modale « En-tête » préremplie (date du jour, « Administrateur »), enregistrée avec « Mariame Sylla », conservée à l'émission, et imprimée « Expédiée par Mariame Sylla » |
| R12 | Retirer le bouton d'accès rapide aux onglets | 1 | ✅ | Fiches SOC-2026-0003, VTE-2026-0001 et SOC-2026-0007 chargées (2026-11-16) : aucun bouton « Voir … » |
| R13 | Documents : séparateur de milliers sur les nombres en chiffres | 6 | ✅ | `FormatNombreTest` (3 tests, dont un acte généré) ; statuts SARLU réels de SOC-2026-0025 générés en fichier temporaire, puis supprimés : « (140.000.000) », « (1.400.000) » ; facture réelle 024/MAB/26 rendue : « 500.000 », « 880.000 GNF » |
| R14 | Bouton d'étape suivante : « Passer à » | 1 | ✅ | Mêmes fiches : « Passer à Signature », « Passer à Expédition », « Passer à la dernière étape Archivé » ; assistant : « Passer aux détails », puis « Passer au récapitulatif » |
| R15 | Création : dénomination en MAJUSCULES et en gras, siège en gras, sigle en gras | 7 | ✅ | `GrasConstitutionTest` (valeur en gras, libellé voisin maigre, taille 22 pts conservée, rien hors constitution) ; 5 gabarits SARLU réels générés pour SOC-2026-0026 en fichiers temporaires : Word valide, dénomination en capitales et en gras à chaque occurrence (2+1+1+3), y compris le RCCM et l'attestation, qui l'avaient en maigre |
| R16 | Création : capital avec séparateur « . » | 6 + 7 | ✅ | Capital des statuts de constitution SARLU : « CENT QUARANTE MILLIONS (140.000.000) », vérifié sur le gabarit actif `statuts-sarlu.docx` |
| R17 | Création : mettre la date de début (d'activité) | 7 | ✅ | Champ ajouté aux questionnaires SARLU, SARL, SAS, SASU et SA, rangé dans la carte « Société » (vérifié sur les modules réels) ; `${soc.date_debut_activite_jma}` retiré des manques connus de `BalisesGabaritsActifsTest`, liste des balises régénérée |
| R18 | Création : renseignements administratifs en tableau, tout sur une page | 7 | ⬜ | |
| R19 | Permettre au certificateur et au créateur de corriger un acte | 2 | ✅ | `DocumentDroitsTest` : matrice étape × rôle, nouvelle version + point périmé + journal ; certificateur connecté sur SOC-2026-0001 : « Déposer une version corrigée » présent |
| R20 | Possibilité de faire une proforma | 8 | ✅ | `FactureProformaTest` (8 tests). Écran vérifié le 2026-11-16 sur un dossier de test temporaire : « Facture proforma », badge « Proforma · numéro attribué à l'émission », paiement désactivé avec sa raison, blocage d'Expédition « émettez-la » ; émise → « N° 025/MAB/26 », paiement activé ; PDF réel téléchargé : 1 page, `facture-025-MAB-26.pdf` |
| R21 | Facture sur une page | 8 | ✅ | Mesuré avant : 21 factures réelles sur 24 sur deux pages. Après : **les 24 sur une page** (dompdf), et le test d'une facture de 10 lignes dont 3 remisées, avec expéditeur, compte 1 page |
| R22 | Notification par mail pour chaque tâche à traiter | 9 | ✅ | `NotificationsParEtapeTest` (4 tests : toutes les étapes parcourues, passage réel Formalités → Expédition avec mail au formaliste, au notaire et au comptable) ; les 3 nouveaux mails rendus sur SOC-2026-0027 (sujet, texte, lien) sans envoi ; les 7 comptes actifs reçoivent le mail |
| R23 | Intégrer la signature du questionnaire (non prioritaire) | 10 | ⬜ | |

---

## Questions ouvertes à l'étude

| # | Élément | Question | Règle provisoire, `a_verifier` |
|---|---|---|---|
| Q1 | R02 | ~~Quelles bornes pour la date d'assemblée ?~~ **Tranché le 2026-11-16** : pas avant la constitution ; une date future reste permise (PV préparé à l'avance, SOC-2026-0027) | — |
| Q2 | R03 | ~~Un nombre de parts ou un pourcentage ?~~ **Tranché par la mesure** : un nombre de parts dans tous les PV saisis | — |
| Q3 | R09 | ~~Quelles bornes pour les dates de signature ?~~ **Tranché le 2026-11-16** : ni future, ni avant la création du dossier. Rien de plus : ni ordre notaire/client (SOC-2026-0013 et 0014 l'auraient enfreint), ni entrée en Signature (SOC-2026-0025 et 0026) | — |
| Q4 | R18 | Quels gabarits sont visés (RCCM ? déclaration ?), et un exemple papier « sur une page » est-il disponible ? | — (bloquant pour R18) |
| Q5 | R20 | Faut-il interdire un paiement sur une proforma ? | Oui |
| Q6 | R22 | Les mails peuvent-ils partir en file d'attente ? Cela exige un worker en production | Envoi synchrone, comme aujourd'hui |
| Q7 | R17 | La date de début d'activité appartient-elle à la fiche société, ou seulement au dossier de constitution ? | Questionnaire seul |

---

## Lot 1 — Retouches d'interface rapides

### R01 — Carte « Clients du dossier » retirée pour SOC-MOD / SOC-DIS · ✅
- **Existant** : `resources/js/Pages/Dossiers/Create.jsx:1764-1802`. La carte est générique, affichée pour tous les types. Bouton `addDossierClient` (l.979), état `dossierClients` (l.399). Elle devient une `Partie` au rôle libre si une qualité est saisie (l.1185-1187).
- **À faire** :
  - masquer la carte selon le code de procédure, jamais selon un libellé ;
  - garder `poolClients` alimenté par `addClientToPool` et `importerPersonneConnue` ;
  - ignorer proprement les `dossierClients` d'un brouillon.
- **Mesure préalable** : nombre de dossiers SOC-MOD et SOC-DIS qui portent une partie au rôle libre.
- **Preuve attendue** : page chargée pour SOC-MOD, SOC-DIS et SOC-SARLU ; payload inspecté.
- **Fait (2026-11-16)** : `carteClientsDossier` dans `Create.jsx`, dérivé de `societeGroupe()`. Il masque la carte, son récapitulatif, et les parties à qualité libre du payload. Le pool `dossierClients` est conservé pour la sélection rapide dans les rôles. La carte n'existe que dans `Create.jsx`, vérifié par recherche.
- **Mesuré (2026-11-16)** : les parties des dossiers SOC-MOD/SOC-DIS n'ont que les rôles `souscripteur` (6), `gerant_entrant` (3), `liquidateur` (2) et `associe_unique` (2). Aucune partie à qualité libre, donc rien n'est perdu.

### R04 — « Quorum atteint » coché par défaut · ✅
- **Existant** : `ag.quorum_atteint`, une checkbox sans défaut (`resources/js/data/questionnaires.js:1337`).
- **À faire** : défaut `true`, posé de la même façon par les trois moteurs (Create, modal de Show, Intake), sans écraser une valeur déjà saisie.
- **Preuve attendue** : nouveau dossier SOC-MOD avec la case cochée ; dossier existant décoché resté décoché.
- **Fait (2026-11-16)** : propriété `defaut: true` sur le champ, lue par `initRepeatableFields()` **à la création seulement**. Voir la décision #108 : le modal et le formulaire public ouvrent un instantané et n'appliquent pas le défaut. `ag.quorum_atteint` n'existe que dans SOC-MOD.

### R12 — Bouton « Voir … » retiré · ✅
- **Existant** : `actionContextuel` dans `resources/js/Pages/Dossiers/Show.jsx` (~5085-5108), rendu à ~5498-5523.
- **À faire** : supprimer l'objet et son rendu. Les blocants gardent leur bouton « Corriger » (`allerAuBlocage`).
- **Fait (2026-11-16)** : l'objet et son rendu sont retirés, et un commentaire explique pourquoi. Aucune autre référence.

### R14 — « Passer à … » · ✅
- **Existant** :
  - fiche dossier : bouton collant `Show.jsx` ~5331-5367, avec l'étiquette « Étape suivante » au-dessus de la cible ;
  - assistant : `Create.jsx:2258`, libellé « Suivant ».
- **À faire** : « Passer à » suivi de la cible (« Passer à : Certification ») ; dans l'assistant, « Passer au récapitulatif ».
- **Fait (2026-11-16)** : la fiche dossier affiche l'étiquette « Passer à » au-dessus de la cible, ou « Passer à la dernière étape ». L'assistant affiche « Passer aux détails » puis « Passer au récapitulatif ».

---

## Lot 2 — Actes : régénération, suppression, correction

### R10 — Toute régénération manuelle retirée · ✅
- **Existant** :
  - bouton « Régénérer » dans `DocumentsTab` (`Show.jsx:3012`, `handleRegenerer` ~2651) ;
  - route `POST /documents/{id}/regenerer` (`routes/web.php:92`) → `DocumentController::regenerer` (l.83) ;
  - courriers : « Régénérer » dans `ExpeditionTab` (`Show.jsx:3776`), et chaque appel crée un nouveau `Courrier`.
- **À faire** :
  - retirer le bouton, la route et la méthode ;
  - pour les courriers, garder « Générer » tant qu'aucun courrier n'existe, et refuser côté serveur une seconde génération du même modèle.
- **Reste en place** :
  - la régénération automatique après modification du questionnaire (`ClientProjectionService::regenererDocumentsNonVerrouilles`) ;
  - « Produire les actes manquants ».
- **Fait (2026-11-16)** :
  - route, méthode et bouton `regenerer` retirés. `update()` n'accepte plus de fichier : la seule voie de remplacement est `corriger()` ;
  - courriers : « Générer » disparaît une fois le courrier produit, et `genererDepuisModele` répond 422 à une seconde demande ;
  - le message de journal au retour d'une formalité disait « régénérez-les ». Il était déjà inapplicable à cette étape, il est reformulé ;
  - les libellés « Régénéré depuis un renvoi » deviennent « Modifié depuis un renvoi », car une correction ou une restauration périme aussi le point.

### R07 — Suppression et restauration réservées aux ayants droit · ✅
- **Existant** :
  - les boutons sont visibles sous `can.genererDocuments`, un droit global ;
  - **défaut** : `PartiePiecesList` (`Show.jsx:1249`) affiche « Supprimer » sous `can.gererPieces`, mais `DocumentController::destroy` exige `genererDocuments`. Résultat : une erreur 403 à l'Initialisation ;
  - « Restaurer une version » n'a aucun contrôle côté front.
- **À faire** :
  - une ability `supprimerDocument` qui distingue une pièce de partie d'un acte ;
  - un drapeau `peut_supprimer` / `peut_restaurer` par document, calculé par la policy.
- **Fait (2026-11-16)** :
  - `DocumentFichierPolicy` décide par famille de document : pièce de partie → `gererPieces`, formalité → `gererFormalites`, acte → `genererDocuments`. Le `match` n'a pas de branche permissive ;
  - les drapeaux sont servis par `documentFichierToArray`, aussi pour les pièces de partie ;
  - **même défaut trouvé sur l'écran Courriers** : le bouton Supprimer s'y affichait à tous, y compris au comptable et sur un courrier signé. Il est maintenant gardé par `peut_supprimer`. Au passage, le chargement anticipé du dossier n'incluait pas les colonnes d'assignation, ce qui aurait fait refuser tout non-admin.

### R19 — Correction d'un acte par le certificateur et le créateur · ✅
- **Existant** :
  - le créateur est `redacteur_id` (`DossierController:421`) ; le certificateur est `reviseur_id`, qui n'a aujourd'hui aucun droit sur les actes ;
  - `DocumentController::update` (l.57) accepte un `fichier`, mais **aucune interface ne l'appelle**.
- **À faire** :
  - ability `corrigerActe` : étapes Édition et Révision, document non verrouillé ; ouverte au rédacteur, au certificateur et à l'administrateur ;
  - action « Déposer une version corrigée » : nouvelle version de source `upload`, entrée au journal ;
  - le `RevisionPoint` devient périmé.
- **Preuve attendue** : `DocumentDroitsTest`, une matrice rôle × étape couvrant supprimer, corriger et restaurer.
- **Fait (2026-11-16)** :
  - route `POST /documents/{id}/corriger` et bouton par acte ;
  - péremption factorisée dans `RevisionPoint::perimerPour()`, partagée avec la régénération. La restauration d'une version périme aussi le point.
- **Tranché (2026-11-16)** : le **notaire n'est pas ajouté**. La correction reste au rédacteur (créateur), au certificateur et à l'administrateur, comme l'étude l'a demandé.

---

## Lot 4 — Pièces justificatives

### R06 — Pièces des personnes ajoutées · ✅
- **Existant** :
  - mise en attente dans `stagedPieces` (`Create.jsx:438`) ;
  - envoi par `buildPartiesPayload`, qui a deux branches, sections et blocs répétables ;
  - validation par `StoreDossierRequest::partiesRules()`, qui écarte en silence ce qu'elle ne valide pas ;
  - enregistrement par `DossierController::enregistrerPiecesPartie()` (l.660).
- **À faire d'abord** : **mesurer** les parties créées depuis l'assistant qui ont une pièce exigée manquante. Les ventiler par origine :
  - section ;
  - bloc répétable ;
  - personne importée du registre ;
  - rôle rattaché d'office ;
  - `type_personne` à NULL.

  Lancer ensuite `node tools/verifier-sections-questionnaire.mjs`.
- **Puis** : corriger la cause, et ajouter un test Feature par chemin.
- **Mesuré (2026-11-16)** :
  - 20 parties ont une pièce exigée manquante. La plupart datent de juillet, avant que les pièces ne bloquent l'Initialisation ;
  - aucune pièce n'est rangée sous une mauvaise catégorie ;
  - aucun dossier n'approche la limite PHP `max_file_uploads = 20` : le plus chargé exige 12 pièces ;
  - les cas récents (SOC-2026-0017, SOC-2026-0024) correspondent aux causes ci-dessous.
- **Causes établies, et corrigées** :
  1. **Le motif de représentation n'était pas servi à l'écran.** Une personne représentée doit aussi le titre de son représentant (procuration…). Le serveur l'exigeait, l'assistant ne le demandait jamais : c'est le cas de la partie #78 de SOC-2026-0017, à qui il ne manque que la procuration.
     - `Partie::reglesPiecesRequises()` sert désormais `parMotif` et `roleRepresentant` ;
     - le retrait de `cni_representant` est passé dans `MotifRepresentation::piecesRetirees()` ;
     - `piecesRequisesPour()` (JS) compose comme le serveur.
  2. **Aucun écran ne proposait les pièces d'un représentant**, alors que le payload les lisait (groupe `mandataire:{rôle}` pour une section, `{bloc}#repr` pour une ligne, ce dernier n'étant jamais écrit). C'est la partie #77 de SOC-2026-0017 : un mandataire sans CNI. Elles sont proposées dans les deux cas, et les pièces de brouillon du représentant d'une ligne sont aussi transmises.
  3. **Retirer une ligne de bloc décalait les personnes, pas leurs pièces** (clés `{ligne}:{catégorie}`). Reproduit en rejouant `buildPartiesPayload()` dans la page : après retrait du premier souscripteur, B recevait la CNI de A, C celle de B, et celle de C se perdait. La correction, dans `lib/piecesStagees.js`, est partagée par l'assistant et par la modale de modification.
- **Défauts voisins, corrigés dans la même passe** :
  - les pièces choisies survivaient à un changement de type d'acte, et à un changement de fiche sur un rôle ou une ligne : elles se rattachaient à la personne suivante ;
  - la modale « Modifier le questionnaire » appelait `usePage()` dans son JSX, comme l'assistant (ordre des hooks variable).
- **Hors champ, et pourquoi** : les parties incomplètes déjà en base ne sont pas complétées d'office. Leurs pièces se déposent, ou se reprennent, depuis la fiche dossier.

### R08 — Reprise des fichiers d'un client existant dès la création · ✅
- **Existant** : la reprise n'est possible **qu'après** création, depuis la fiche dossier, via `Partie::piecesReprenables()` (l.493) et `reprendrePiece()` (l.567). L'assistant n'en propose aucune.
- **À faire** :
  - endpoint `GET /clients/{client}/pieces-reprenables?role=` ;
  - dans l'assistant, « Déjà fournie le JJ/MM/AAAA, reprise automatiquement », avec la possibilité de la remplacer ;
  - envoi de `pieces_reprises` par les **deux** branches du payload, avec sa validation dans `partiesRules()` ;
  - `blocantsEtape.js` compte la pièce comme fournie.
- **Hors champ** : les pièces constitutives d'une société restent sur la `Societe`.
- **Fait (2026-11-16)**, d'une façon plus simple que prévu : **le serveur reprend d'office**.
  - `DossierController::reprendrePiecesConnues()` parcourt, après création, chaque personne liée à une fiche. Il copie les pièces encore manquantes avec `Partie::reprendrePiece()`, version `source = 'reprise'`, comme le bouton existant.
  - Aucun identifiant de pièce ne transite par le navigateur.
  - Un fichier choisi à l'écran l'emporte.
  - La procuration n'est jamais reprise.
  - L'écran annonce la reprise avec sa date et son dossier d'origine, grâce à `GET /clients/{id}/pieces-reprenables` (lecture seule, sans fichier ni URL) et au hook `useReprenables`.
  - `Partie::piecesReprenables()` fonctionne désormais pour une partie non enregistrée : `id != NULL` ne retenait aucune ligne.

---

## Lot 3 — Questionnaire d'assemblée

### R02 — Date et heure d'assemblée · ✅
- **Existant** :
  - `ag.heure` est un champ texte libre (placeholder « 10h00 ») ;
  - aucune contrainte sur `ag.date`, `dissolution.date_assemblee` ni `cloture.date_assemblee` ;
  - il n'existe aucun type de champ « heure ».
- **À faire** :
  - type `time` dans `ChampQuestionnaire.jsx`, pour les trois moteurs ;
  - `ag.heure` obligatoire ;
  - balise `ag.heure_lettres` ;
  - contrainte de date déclarée dans `CONTRAINTES_DATES` **et** `CoherenceDonneesService::CONTRAINTES`, sous le test de parité `CoherenceDonneesTest`.
- **Mesure préalable** : la forme des valeurs `ag.heure` déjà saisies. Elles sont normalisées à la lecture seulement : un questionnaire est un instantané.
- **Mesuré (2026-11-16)** : six graphies sur six PV (« 10H », « 10h00 », « 10H30 », « 10h30 », « 10H00 »…). Une date d'assemblée **future** existe (SOC-2026-0027, assemblée du 20/11) : la règle « non future » l'aurait bloquée.
- **Fait (2026-11-16)** :
  - type `time` dans `ChampQuestionnaire`, donc dans les trois écrans. `ag.heure` passe en `time` et devient obligatoire ;
  - les anciennes valeurs sont **lues** sous forme canonique, sans réécriture (`lib/heures.js` et `App\Support\Heure`). Une valeur illisible reste affichée en clair sous le champ, au lieu de paraître vide puis d'être effacée ;
  - balises dérivées `ag.heure_lettres` (« DIX HEURES TRENTE », au féminin) et `ag.heure_formate` (« 10 h 30 »). La liste des balises a été régénérée ;
  - dates : `ag.date`, `dissolution.date_assemblee` et `cloture.date_assemblee` ne peuvent pas précéder `soc.date_constitution`. Déclaré dans `CONTRAINTES_DATES` **et** `CoherenceDonneesService::CONTRAINTES`. La parité compare désormais le champ, le genre **et la borne**, et non plus le champ seul.
- ⚠️ **Effet sur une donnée existante** : SOC-2026-0022 porte une assemblée de dissolution au 01/10/2026, antérieure à sa constitution du 05/10/2026. Son prochain enregistrement du questionnaire sera refusé tant que la date n'est pas corrigée.

### R03 — Parts présentes ou représentées · ✅
- **Existant** : `ag.parts_representees`, un nombre sans défaut ni contrôle, qui n'est consommé que par les balises.
- **À faire** :
  - préremplir avec `soc.nombre_parts` ;
  - afficher « / N parts (x %) » ;
  - cohérence ≤ total, en JS et en PHP.
- **Fait (2026-11-16)** :
  - `surTotal` affiche « sur N parts (x %) » sous le champ, dans le moteur partagé ;
  - `defautDepuis` préremplit avec `soc.nombre_parts` à la création ;
  - le plafond est déclaré dans `PLAFONDS` (JS) et `CoherenceDonneesService::PLAFONDS`, avec un test de parité.

### R05 — Nature de la décision déduite · ✅
- **Existant** :
  - `ag.type` est un select saisi à la main ;
  - la déduction existe côté serveur, mais n'est pas branchée : `TypeModificationStatutaire::assemblee()`, `TypeAssemblee::laPlusExigeante()`, `FormeSociete::exigeAssocieUnique()` ;
  - `Societe.php:686` code la règle en dur, avec `['SARLU','SASU']`.
- **À faire** :
  - préremplissage, toujours modifiable :
    - forme unipersonnelle → « Décision de l'associé unique » ;
    - sinon, opération la plus exigeante → AGE ou AGO ;
  - un test de parité PHP/JS.
- **Mesuré (2026-11-16)** : 6 PV de SARLU sur 6 portent « AGE » ou « AGO ». SOC-2026-0024 porte une AGO pour un changement de gérant statutaire et une augmentation de capital. **Règle arbitrée par l'étude** : forme unipersonnelle → décision de l'associé unique.
- **Fait (2026-11-16)** :
  - `TypeAssemblee::reglesNatureDecision()` est servi à l'assistant (prop `natureDecision`), et `natureDeduite()` compose à partir des mêmes données ;
  - `lib/natureDecision.js` ne porte aucun libellé ni aucune forme (testé) ;
  - les options de `ag.type` sont vérifiées égales aux libellés servis ;
  - préremplissage **à la création seulement**, sans jamais écraser un choix : seulement un champ vide, ou qui porte encore la dernière proposition.

---

## Lot 5 — Signature

### R09 — Contrôle des dates de signature · ✅
- **Existant** : `DossierController::enregistrerSignatures` (l.1106) ne valide que `nullable|date`, et `DateField` n'a aucune borne.
- **À faire** :
  - non future, et pas avant l'entrée en Signature, côté serveur **et** front (`min`/`max`) ;
  - un blocant énuméré dans `getStepBlockers`.
- **Preuve attendue** : `SignatureDatesTest`.
- **Mesuré (2026-11-16)** sur les 12 dossiers signés : 2 ont le notaire signant la veille du client, et 2 ont des signatures antérieures à l'entrée en étape Signature. Les deux règles envisagées les auraient rendus incohérents. L'étude a retenu **futur + création** seulement.
- **Fait (2026-11-16)** :
  - `Dossier::bornesDateSignature()` est le détenteur unique des bornes. La validation de `enregistrerSignatures()` (messages en français) et l'écran (`min`/`max` du champ, message sous le champ, enregistrement désactivé hors bornes) le lisent tous deux ;
  - `RestrictionsEtapesTest` signait à une date fixe du 01/08/2026, antérieure à la création de son dossier de test : il signe désormais à la date du jour.

---

## Lot 6 — Séparateur de milliers « . »

### R13 — Tous les documents · ✅
- **Existant** : trois séparateurs coexistent :
  - espace fine insécable dans les actes (`ActesGeneratorService:1066`) ;
  - espace ordinaire dans la facture, le reçu et la fiche de recueil ;
  - **aucun** séparateur sur les balises brutes `soc.capital_chiffres`, `soc.nombre_parts`, etc.
- **À faire** :
  - helper unique `App\Support\FormatNombre::milliers()` ;
  - balises numériques des actes injectées formatées, avec la liste des exclusions (années, durées, téléphones) déclarée une seule fois ;
  - corriger au passage `_lettres` des blocs répétables, qui ajoute « Francs Guinéens » à un nombre de parts.
- **Preuve attendue** : `FormatNombreTest`, et un acte généré qui contient « 10.000.000 ».
- **Mesuré (2026-11-16)** :
  - dans les modèles actifs, `${soc.capital_chiffres}` (17 occurrences) et `${soc.nombre_parts}` (12) étaient brutes, sans séparateur ;
  - aucune balise de bloc n'est employée ;
  - les 108 valeurs numériques en base sont des entiers nus ;
  - aucune clé `_chiffres` ne porte une année ou un téléphone.
- **Fait (2026-11-16)** :
  - `App\Support\FormatNombre` est le seul détenteur de la règle. `milliers()` sépare par des points et garde jusqu'à deux décimales ; une valeur déjà écrite « 1.000.000 » est relue telle quelle, et un texte est rendu intact. `doitFormater()` retient les clés `_chiffres` plus une liste fermée de 9 clés (`nombre_parts`, `superficie`, `prix_cession`…) ;
  - actes : balise brute **et** `_formate`, à la racine comme dans les blocs. Le `_lettres` de bloc n'ajoute plus « Francs Guinéens » à un nombre de parts ;
  - facture (PDF et ancien gabarit Word), reçu, fiche de recueil et assiette imprimée dans les désignations de lignes passent par le helper ;
  - les messages d'écran et du journal gardent l'espace : la demande porte sur les documents.

### R16 — Capital · ✅
- Couvert par R13. Le contrôle visuel se fait sur l'acte de constitution, au lot 7.

---

## Lot 7 — Actes de constitution

### R15 — Dénomination, siège, sigle · ✅
- **Existant** : la mise en forme est **entièrement dans les gabarits** `.docx` : le code ne fait que `setValue`.
- **À faire** :
  - `soc.denomination` en `mb_strtoupper` à l'injection ;
  - le gras réglé dans les gabarits.
- **Mesuré (2026-11-16)** sur les 11 gabarits actifs de constitution : le gras était incohérent. La DNSV et les attestations SARL/SAS/SASU l'avaient, les statuts SARLU à moitié, le RCCM et l'attestation SARLU pas du tout. Aucun n'appliquait les majuscules.
- **Fait autrement que prévu (2026-11-16)** : **à la génération, pas dans les fichiers**. `App\Support\GabaritWord` (sous-classe de `TemplateProcessor`) met en gras les balises dénomination, sigle et siège. Il découpe un run qui mêle libellé et balise, et **clone les propriétés du run** (police, taille), ce que `setComplexValue()` aurait perdu. La dénomination est mise en majuscules dans l'acte seulement : la fiche et le questionnaire gardent la saisie. Le tout est limité aux dossiers de constitution. Les gabarits ne sont pas modifiés, et un nouveau gabarit en hérite d'office.

### R17 — Date de début d'activité · ✅ (Q7 encore ouverte)
- **Existant** : la balise `soc.date_debut_activite_jma` est attendue par `rccm-sarlu-balises.docx`, mais **aucun champ** ne la remplit. Elle est listée comme manque connu dans `BalisesGabaritsActifsTest:51-54`.
- **À faire** :
  - champ date dans les questionnaires SARLU, SARL, SAS, SASU et SA ;
  - retirer la balise des manques connus.
- **Fait (2026-11-16)** : champ `date`, facultatif, dans `SOC_BASE` (SARLU, SARL, SAS, SASU) et le bloc SA. La balise `_jma` est dérivée d'office. `03-ETAT-DES-MODELES.md` est mis à jour. **Q7 reste ouverte** : la date vit dans le questionnaire seul, sans colonne sur la fiche société.

### R18 — Renseignements administratifs en tableau, sur une page · ❓ Q4
- **Existant** : l'expression est introuvable. Le formulaire RCCM porte des `FORMTEXT` non balisés. Seul `statuts-sarlu.docx` est actif, les autres gabarits sont dans `_a_baliser/`.
- **À faire** : retravail des gabarits dans Word, une fois l'exemple de l'étude reçu.

---

## Lot 8 — Facturation

### R20 — Proforma · ✅ (Q5 : paiement refusé sur proforma, provisoire)
- **Existant** :
  - aucune notion de statut ;
  - chaque dossier consomme un numéro dès sa création (`Facture::genererNumero()`), sans verrou ni contrainte d'unicité.
- **À faire** :
  - colonnes `statut` (`proforma` ou `emise`) et `emise_at`, plus un index unique sur `note_numero` ;
  - les factures existantes passent en `emise` ;
  - action « Émettre la facture » sous `lockForUpdate` ;
  - PDF titré « FACTURE PROFORMA ».
- **Mesuré (2026-11-16)** : 24 factures, toutes numérotées, sans doublon ; 8 ont reçu un paiement.
- **Fait (2026-11-16)** :
  - migration `ajouter_statut_emission_expediteur_aux_factures` (colonnes `statut`, `emise_at`, `expediteur`, plus un index unique sur `note_numero`). Les factures existantes passent en émises ;
  - enum `StatutFacture` ;
  - `Facture::emettre()` fonctionne sous verrou, dans une transaction. Il refuse une facture déjà émise, ou sans montant ;
  - **`genererNumero()` retient la plus haute séquence de l'année**, et non la dernière ligne par id. Avec la proforma, l'ordre d'émission diffère de l'ordre de création, et l'ancienne lecture redonnait un numéro déjà attribué (le test le reproduit) ;
  - régénérer une facture émise est refusé, pour ne pas laisser de trou dans la numérotation ;
  - le blocage de sortie d'Expédition, côté serveur comme écran, dit « émettez-la » quand la facture est une proforma ;
  - le fichier d'une proforma se nomme `proforma-{dossier}.pdf` ;
  - la liste Facturation affiche « Proforma » ou le numéro de la facture.

### R11 — Date et expéditeur · ✅
- **Existant** : l'en-tête n'est pas modifiable : `note_date` est en lecture seule, et il n'existe aucun champ expéditeur.
- **À faire** :
  - colonne `expediteur` (texte) ;
  - modale « Modifier l'en-tête » avec la date et l'expéditeur, prérempli avec l'utilisateur connecté ;
  - route `PATCH factures/{facture}/entete` sous `gererFacturation` ;
  - affichage dans le PDF.
- **Fait (2026-11-16)** : route `PATCH factures/{id}/entete` sous `gererFacturation`, avec une entrée au journal. Modale « En-tête » dans l'onglet Facturation, préremplie avec l'utilisateur connecté. L'expéditeur est fixé à l'émission s'il n'est pas déjà saisi, et imprimé « Expédiée par … » sous la signature.

### R21 — Facture sur une page · ✅
- **Existant** : `resources/views/factures/pdf.blade.php` (dompdf). Le débordement a plusieurs causes :
  - la liste des prestations est répétée sous l'objet (l.150-152) ;
  - les lignes sont hautes ;
  - le bloc signature est épais.
- **À faire** : supprimer la répétition, compacter, ajouter `page-break-inside: avoid`.
- **Preuve attendue** : un test qui vérifie que dompdf compte 1 page pour 10 lignes.
- **Fait autrement que prévu (2026-11-16)** : le rappel sous l'objet est **conservé**, car il vient de l'exemplaire de l'étude, et ce n'était pas lui qui débordait (une facture de 4 lignes faisait déjà deux pages). Ce sont les marges et les espacements qui ont été resserrés. La place de la signature passe de 52 à 40px. Le bloc « arrêté, courtoisie, signature » ne se coupe plus d'une page à l'autre.

---

## Lot 9 — Notifications

### R22 — Un mail pour chaque tâche à traiter · ✅ (Q6 encore ouverte : envoi synchrone)
- **Existant** :
  - notifications envoyées en base, en broadcast, et par mail si `notifications_email` est actif ;
  - elles couvrent l'assignation, la Révision, la Signature, les Formalités, le renvoi, les demandes et les échéances ;
  - **les entrées en Édition, Expédition et Archivé ne notifient personne.**
- **À faire** :
  - compléter dans `DossierStepService` ;
  - ajouter un test qui parcourt **tous** les cas de `EtapeDossier`, par un `match` exhaustif ;
  - mesurer combien d'utilisateurs ont désactivé le mail.
- **Mesuré (2026-11-16)** : les 7 comptes actifs ont une adresse et le mail activé. Le comptable n'est assigné à aucun dossier : seul son pool peut le joindre.
- **Fait (2026-11-16)** :
  - `DossierStepService::tachesALEntree()` est un `match` **exhaustif, sans branche par défaut**, qui dit qui reçoit une tâche à l'entrée de chaque étape. Les blocs `if` épars sont supprimés ; une étape ajoutée à l'enum fera échouer ce code ;
  - **Édition** → rédacteur (`ActesAProduireNotification`) ;
  - **Expédition** → formaliste et notaire (`ExpeditionAFaireNotification`), et le **comptable** si la facture n'est pas soldée (`FactureAEncaisserNotification`). Il est joint par son pool, pas par le repli habituel, qui aurait doublé le notaire ;
  - Initialisation et Archivé sont déclarées sans tâche, avec leur motif : création notifiée par `notifierAssignations()`, étape terminale ;
  - les notifications partent **après** les effets de l'entrée (révision créée, lettres générées).
- **Hors champ** : les liens des mails reprennent `APP_URL`, qui vaut `http://localhost` en développement, comme pour toutes les notifications existantes. Il faudra la vraie adresse en production. **Q6** reste ouverte : les mails partent en synchrone, sans file.

---

## Lot 10 — Signature du questionnaire (non prioritaire)

### R23 · ⬜
- **Existant** : l'accord du client est collecté **sur papier** : la fiche de recueil est imprimée, signée, puis téléversée (`AccordClientCard.jsx`). `Intake/Show.jsx` ne contient ni consentement ni signature.
- **Esquisse** : case de consentement et pavé de signature dans le formulaire public. Le résultat est stocké en `accord_client`, verrouillé, et satisfait `AccordsInitialisation`. **À concevoir avec l'étude avant toute ligne de code.**

---

## Journal du suivi

| Date | Élément | Changement |
|---|---|---|
| 2026-11-16 | — | Création du suivi : 23 éléments, 10 lots, 4 arbitrages rendus, 7 questions ouvertes |
| 2026-11-16 | R01 R04 R12 R14 | Lot 1 codé. Preuves : `vite build` OK (sortie en scratch, `public/build` intact), 43 tests verts (`PariteRenduQuestionnaireTest`, `CoherenceDonneesTest`, `ListesMatrimonialesTest`, `PiecesRequisesParRoleTest`), `verifier-sections-questionnaire.mjs` A-H OK. **Pages non chargées : MySQL arrêté** — passage à ✅ après chargement réel |
| 2026-11-16 | R22 | Lot 9 fait. Preuves : `NotificationsParEtapeTest`, 820/831 avec **les mêmes 10 échecs préexistants**, mails rendus sans envoi (Mailtrap non sollicité) |
| 2026-11-16 | R20 R11 | **Lot 8 vérifié à l'écran** → ✅. Dossier `TST-R20-0001` et sa facture, créés avec l'accord de l'utilisateur, puis supprimés définitivement (1 ligne, 1 facture, 2 entrées de journal). Le prochain numéro redevient 025/MAB/26 : **aucun trou** dans la numérotation de développement. Relevé à l'écran : l'en-tête de la carte facture passe désormais à la ligne (`flex-wrap`), filet de sécurité pour un écran étroit avec cinq boutons |
| 2026-11-16 | R20 R11 R21 | Lot 8 codé. Preuves : `FactureProformaTest` (8 tests), les 24 factures réelles sur une page, 817/827 avec **les mêmes 10 échecs préexistants**. Quatre tests créaient des factures numérotées sans statut : ils les déclarent désormais émises. Migration appliquée sur la base de développement (la seule en attente). Écran à voir : volet masqué |
| 2026-11-16 | R15 R17 | Lot 7 (partie) fait. Preuves : `GrasConstitutionTest`, génération réelle de 5 gabarits SARLU en fichiers temporaires, puis supprimés. 809/819 avec **les mêmes 10 échecs préexistants**. **R18 reste bloqué par Q4** : il faut un exemple de l'étude |
| 2026-11-16 | R13 R16 | Lot 6 fait. Preuves : `FormatNombreTest`, 806/817 avant ajustement, puis `TelechargementFactureTest` mis à jour (il attendait « 300 000 » : changement voulu). Il reste **les mêmes 10 échecs préexistants**. Vérifié sur des données réelles sans rien écrire en base : statuts SARLU de SOC-2026-0025 et facture 024/MAB/26 |
| 2026-11-16 | R09 | **Lot 5 vérifié à l'écran** → ✅. Dossier `TST-R09-0001`, créé pour l'occasion avec l'accord de l'utilisateur, puis supprimé définitivement avec son entrée de journal. Aucune autre trace (questionnaire, parties, facture, formalités, notifications : 0) |
| 2026-11-16 | R09 | Lot 5 codé. Q3 tranchée. Preuves : `SignatureDatesTest`, et 804/814 avec **les mêmes 10 échecs préexistants**. Modale à voir : aucun dossier en Signature |
| 2026-11-16 | — | **Corrigé** : `blocantsDates()` range désormais chaque blocage dans la section de sa carte, par `groupFieldsBySection()` comme les champs obligatoires. Vérifié dans l'assistant : « Date de l'assemblée » et « Parts présentes » sous ASSEMBLÉE GÉNÉRALE, et badge « 2 à compléter » sur cette carte. Vaut pour tous les contrôles de dates du questionnaire |
| 2026-11-16 | R02 R03 R05 | **Lot 3 vérifié à l'écran** → ✅. Relevé en passant : les blocages de dates et de plafond se rangeaient sous « Informations générales » au lieu de la section réelle du champ, parce que `blocantsDates()` lisait `field.section`, que seul le premier champ d'une section porte (défaut antérieur à ce lot) |
| 2026-11-16 | R02 R03 R05 | Lot 3 codé. Q1 et Q2 tranchées. Preuves : `NatureDecisionTest` (6 tests), `CoherenceDonneesTest` (règles nouvelles et parités renforcées), 799/809 avec **les mêmes 10 échecs préexistants**, règles servies rejouées dans la page. **Rendu à voir** : le volet du navigateur était masqué, et l'animation d'étape ne monte pas sans affichage |
| 2026-11-16 | R06 R08 | Lot 4 fait. Preuves : `PiecesCreationDossierTest` (5 tests) et `PiecesRequisesParRoleTest` (nouvelle parité) verts, `verifier-sections-questionnaire.mjs` A-H OK après ajout de `piecesStagees.js` à ses copies, `vite build` OK, assistant chargé et inspecté. Suite complète : 790/800, avec **les mêmes 10 échecs préexistants** |
| 2026-11-16 | R19 | Décision : le notaire n'est pas ajouté aux personnes qui peuvent corriger un acte |
| 2026-11-16 | R07 R10 R19 | Lot 2 fait. Preuves : `DocumentDroitsTest` vert, `vite build` OK, fiche et Courriers chargées en admin et en certificateur. Suite complète : 783/794. Les **10 échecs restants préexistent** et ne passent par aucun fichier touché (voir le journal du devbook). Le 11e venait de la liste `balises-resolvables.txt`, devenue plus ancienne que `questionnaires.js` : elle a été régénérée, sans changement de contenu |
| 2026-11-16 | R01 R04 R12 R14 | **Lot 1 vérifié sur les pages réelles** (MySQL redémarré) → ✅. Défaut préexistant corrigé au passage : `usePage()` appelé dans le JSX des sections de rôle de `Create.jsx`. React signalait un changement d'ordre des hooks au choix d'une procédure ; plus d'avertissement après correction |
