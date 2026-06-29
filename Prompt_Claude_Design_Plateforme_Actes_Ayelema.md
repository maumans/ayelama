# Prompt de design — Plateforme de gestion des actes notariés (Cabinet Ayelema)

> **À copier-coller dans Claude Design.** Tu peux le donner tel quel, ou ne garder que les sections 4 à 6 si tu veux aller droit aux écrans. Les sections 1 à 3 servent à donner le contexte métier ; les sections 4 à 9 sont les consignes de design proprement dites.

---

## 0. Rôle & cadre attendu

Tu es un designer produit senior spécialisé en SaaS B2B pour professions réglementées (legaltech). Conçois une application web **moderne, sobre et résolument professionnelle** pour un **office notarial**. Le rendu doit inspirer **rigueur, confiance et autorité** sans être austère : pense « Linear / Notion / Stripe Dashboard » en niveau de finition, mais avec une **gravité notariale** (typographie élégante, palette feutrée, densité maîtrisée).

Stack de destination (à respecter dans les choix de composants et de styles) : **React + Inertia (Laravel) + shadcn/ui + Tailwind CSS + Framer Motion + lucide-react**. N'utilise donc que des patterns réalisables avec shadcn/ui et Tailwind (pas de composants exotiques non reproductibles).

Langue de l'interface : **français** (libellés, dates au format JJ/MM/AAAA, montants en GNF/€).

---

## 1. Contexte & objectif produit

L'office notarial **Ayelema** traite quotidiennement des **dossiers d'actes** de natures variées. Aujourd'hui le traitement est largement manuel, sans étape de contrôle formalisée, ce qui crée un **risque d'erreur non détectée** entre la rédaction et la signature. L'application doit **digitaliser et fiabiliser tout le cycle de vie d'un dossier d'acte**, de l'ouverture jusqu'à l'expédition, en imposant les bonnes étapes au bon moment.

Objectifs produits prioritaires :
1. **Piloter** l'ensemble des dossiers en cours (vue d'ensemble + file de travail par rôle).
2. **Standardiser le workflow** d'un dossier selon sa catégorie, avec génération des actes depuis des modèles.
3. **Imposer une étape de révision** (grille de contrôle) entre l'édition des actes et leur signature — fonctionnalité phare.
4. **Suivre les formalités administratives** (APIP, Impôts, Conservation foncière, CNSS) avec leurs délais, taux, chèques et pièces.
5. **Tracer** chaque dossier (journal d'activité, qui a fait quoi, quand) pour l'auditabilité.

---

## 2. Utilisateurs & rôles

Conçois pour 4 profils. Chaque écran doit s'adapter au rôle connecté (file de travail, actions disponibles, badges).

- **Clerc / Rédacteur** — ouvre les dossiers, remplit les questionnaires, génère et rédige les actes.
- **Réviseur / Responsable** — valide la grille de contrôle avant signature (peut renvoyer en correction).
- **Notaire (« Maître »)** — signe les actes après révision, supervise.
- **Formaliste** — exécute les démarches administratives (dépôts, paiements, retours).
- (Optionnel) **Administrateur** — gère utilisateurs, modèles d'actes, barèmes/taux, types d'actes.

---

## 3. Le workflow central (modèle mental à matérialiser visuellement)

Chaque dossier suit un **pipeline en étapes** (à représenter par un *stepper / timeline* horizontal en haut de la fiche dossier). Une étape ne peut être franchie que si la précédente est validée :

1. **Initialisation** — création + fiche questionnaire renseignée.
2. **Édition des actes** — génération des documents depuis les modèles selon le type d'acte.
3. **Révision / Contrôle** ⭐ — grille de contrôle validée par un réviseur (étape qui *bloque* la signature tant qu'elle n'est pas validée).
4. **Signature client** — signatures des parties (certaines pièces exclues, ex. RCCM non signé par le client).
5. **Signature du notaire**.
6. **Formalités administratives** — dépôts auprès des organismes, paiements, attente des retours (délais).
7. **Expédition** — copies authentiques, restitution éventuelle, courrier de transmission.
8. **Clôturé**.

Les catégories de dossiers (à utiliser comme typologie/filtres et comme variantes de questionnaire/actes) :
**Société** (Création / Modification / Dissolution), **Vente d'immeubles**, **Contrat d'hypothèque**, **Baux** (à construire / habitation / professionnel), **Donations**, **Successions** (partage amiable / judiciaire), **Promesse de vente & cession**, **Contrat de mariage**, **Testament**, **Procuration**, **Courrier**, **Prise en charge**.

---

## 4. Identité visuelle & direction artistique

### Positionnement
« Premium, feutré, précis ». Beaucoup de **blanc**, une **hiérarchie typographique forte**, des **accents rares mais marquants**. Aucune surcharge, aucune couleur criarde. La crédibilité passe par la retenue.

### Palette (tokens — propose-les en variables CSS / config Tailwind)
- **Fond application** : blanc cassé chaud, type papier — `#FBFAF7`. Cartes en blanc pur `#FFFFFF`.
- **Primaire « Encre / Bleu Notaire »** (navigation, en-têtes, boutons primaires) : `#15263F` (profond), variantes `#1F3A5F`, `#2C4A75`.
- **Accent « Sceau »** (laiton/or feutré, pour éléments officiels, focus, sélections actives) : `#B0863C` / hover `#9A7331`. À utiliser avec parcimonie (liserés, étape active, badge « validé notaire »).
- **Neutres** : échelle slate/stone (`#0F172A` texte fort → `#64748B` texte secondaire → `#E2E8F0` bordures → `#F1F5F9` surfaces).
- **Sémantiques** : succès `#15803D`, attention/échéance `#B45309`, danger/rejet `#B91C1C`, info `#1D4ED8`. Toujours en versions douces (fond pâle + texte foncé) pour les badges.

> Tu peux proposer une **variante bordeaux** de l'accent (`#7A2E3A`) en alternative à l'or, pour le côté « cachet officiel ». Laisse l'or par défaut.

### Typographie
- **Titres / display** : un serif élégant et contemporain — **« Newsreader »** ou **« Source Serif 4 »** (au choix). Donne du caractère « juridique » sans être daté.
- **Interface / corps / labels** : **« Inter »** (ou « Geist »).
- **Données techniques** (références de dossier, montants, taux, numéros) : un **mono** discret (**« Geist Mono »** ou « JetBrains Mono ») pour fiabilité de lecture.
- Hiérarchie nette : grands titres sereins, sous-titres en gris, généreux *line-height*.

### Système visuel
- **Rayons** : `rounded-lg` (~0.5rem), cohérents. Boutons et inputs un peu plus serrés que les cartes.
- **Ombres** : très subtiles, en couches (`shadow-sm` sur cartes, élévation au survol). Pas d'ombres dures.
- **Bordures** : 1px `#E2E8F0`, omniprésentes pour structurer sans bruit.
- **Espacements** : grille 4/8 px, respiration généreuse, mais **tableaux denses** (les notaires manipulent beaucoup de lignes).
- **Iconographie** : `lucide-react`, trait fin, taille homogène (16–18px en UI).
- **Animations (Framer Motion)** : discrètes et fonctionnelles — apparition douce des cartes (fade + 8px up), transitions d'onglets, progression du stepper, ouverture des *dialogs/sheets*. Jamais de rebond ludique : on reste sobre.

### Composants shadcn/ui à mobiliser
Sidebar, Card, Table (avec tri/pagination), Badge, Tabs, Breadcrumb, Stepper/Timeline (custom), Dialog & Sheet, Command palette (`⌘K` pour recherche globale de dossiers), Tooltip, Dropdown menu, Progress, Checkbox (grille de contrôle), Select/Combobox, Date picker, Avatar, Toast, Skeleton, Empty state.

---

## 5. Architecture de l'information (navigation)

**Layout global** : barre latérale gauche fixe + barre supérieure.

- **Sidebar** (logo « Ayelema » + sceau discret en haut) :
  - Tableau de bord
  - Dossiers
  - Révisions (file de contrôle) — avec compteur
  - Formalités — avec compteur
  - Modèles d'actes
  - Répertoire (clients & parties)
  - Courriers
  - Paramètres (rôles/barèmes/types d'actes)
  - Bas de sidebar : profil utilisateur + rôle.
- **Topbar** : fil d'Ariane, **recherche globale `⌘K`**, bouton primaire « **Nouveau dossier** », cloche de notifications (échéances), avatar.

---

## 6. Spécification écran par écran

> Conçois en priorité, avec un niveau de finition élevé, les écrans **6.1 à 6.5** (ils racontent le produit). Les autres peuvent être esquissés.

### 6.1 Tableau de bord
- Bandeau de **KPIs** (cards) : Dossiers en cours · En attente de révision · En attente de signature · Formalités en cours · **Échéances < 72h** (mettre en évidence, lien direct).
- **« Ma file de travail »** : liste contextualisée au rôle (ex. réviseur → dossiers à contrôler).
- **Répartition par catégorie** (petit graphique en barres ou donut sobre, couleurs neutres + accent).
- **Alertes** : encart visible pour les dossiers *édités mais non révisés* (rappel de la faille d'audit) et **échéances dépassées**.
- **Activité récente** (journal : « Mme Tafsir a généré les statuts du dossier #2026-0142 »).

### 6.2 Liste des dossiers
- Tableau dense, triable, paginé. Colonnes : **Réf** (mono, ex. `SOC-2026-0142`), **Client / Dénomination**, **Catégorie** (badge + icône), **Type d'acte**, **Étape** (badge de statut coloré selon l'étape du pipeline), **Responsable** (avatar), **Échéance** (avec pastille si proche/dépassée), menu d'actions.
- Barre de **filtres** : catégorie, étape, responsable, plage de dates, recherche. Filtres actifs affichés en *chips*.
- Vue alternative optionnelle : **kanban par étape** du pipeline (colonnes = étapes du workflow).
- États : loading (skeleton), vide (empty state élégant avec CTA « Créer un dossier »).

### 6.3 Fiche dossier (écran central — soigne-le particulièrement)
- **En-tête** : Réf + dénomination/client, catégorie + type d'acte, responsable, **bouton d'action principal contextuel** selon l'étape (« Soumettre à révision », « Valider la révision », « Marquer signé », « Enregistrer la formalité »…).
- **Stepper / timeline horizontal** du workflow (section 3) : étape courante en accent or, étapes franchies en succès, étapes à venir en gris ; clic sur une étape = affiche son détail.
- **Onglets** :
  - **Informations** — données du questionnaire (varie selon catégorie : pour SARL → dénomination, capital, gérant(s) ; pour vente → bien, vendeur, acquéreur, titre foncier ; pour hypothèque → banque, montant, lettre de crédit…).
  - **Actes & documents** — liste des documents à produire selon le type, chacun avec statut (À éditer / Édité / Signé client / Signé notaire), bouton « Générer depuis le modèle », aperçu, téléchargement. *Ex. SARL : Statuts, DNSV, Déclaration sur l'honneur / Casier judiciaire, RCCM (non signé par le client).*
  - **Révision** — la grille de contrôle (voir 6.4).
  - **Formalités** — suivi des démarches (voir 6.5).
  - **Parties** — clients, gérants, héritiers, banque… avec coordonnées et pièces (CNI, certificat de résidence, photo).
  - **Journal** — historique horodaté + signé (auditabilité).
- **Panneau latéral droit** : échéances et rappels (ex. « Retour APIP attendu sous 72h »), pièces requises manquantes, raccourcis.

### 6.4 Étape de révision — Grille de contrôle ⭐ (différenciateur, à designer avec soin)
Écran/onglet qui matérialise la nouvelle exigence d'audit.
- En-tête : « Contrôle qualité avant signature » + nom du réviseur + état global (En attente / En cours / Validé / Renvoyé en correction).
- **Grille = liste de points de contrôle** (checklist) groupés par thème, adaptée au type d'acte. Chaque point : libellé, case **Conforme / Non conforme / N.A.**, champ commentaire optionnel, et indicateur de la pièce concernée.
  - *Exemples de points pour une SARL :* « Dénomination cohérente entre statuts et RCCM », « Capital identique statuts / DNSV », « Identité et pièces du/des gérant(s) complètes », « Mentions obligatoires des statuts présentes », « Montant en lettres = montant en chiffres »…
- **Barre de progression** de la conformité (ex. 7/9 contrôlés).
- Actions : **« Valider la révision »** (débloque la signature, transition d'étape animée) ou **« Renvoyer en correction »** (rouvre l'étape Édition avec les commentaires, notifie le rédacteur).
- Visuellement : c'est l'écran « sérieux » par excellence — clair, structuré, rassurant. Une validation réussie déclenche un feedback discret (toast + sceau or « Révision validée »).

### 6.5 Suivi des formalités
- Vue d'un dossier *ou* file globale des formalités en cours.
- Par organisme, une **carte de démarche** : **APIP**, **Impôts**, **Conservation foncière**, **CNSS** — chacune avec :
  - Statut (À déposer / Déposé / En attente de retour / Retour reçu / Clôturé) ;
  - **Pièces transmises** (cochables : RCCM, attestation de dépôt de capital, statuts, certificat de résidence, pièces d'identité, photo gérant…) ;
  - **Montants/Taux** automatiques selon le type (ex. **Conservation foncière : 1,5 % du montant de l'hypothèque** ; **Impôts hypothèque : 0,10 %** ; **Vente — Impôts : 2 % de la transaction**) avec chèque / ordre de paiement associé et justificatif ;
  - **Délai / échéance** (ex. **APIP ≈ 72 h** : afficher un compte à rebours discret) ;
  - Retours attendus (RCCM signé, quittance, acte enregistré, accusé de réception).
- Mets en avant visuellement les **échéances proches/dépassées** (pastilles ambre/rouge).

### 6.6 Création de dossier — Assistant (wizard)
- Parcours en étapes : **1) Catégorie** (cartes cliquables avec icônes : Société, Vente, Hypothèque, Bail, Donation, Succession…) → **2) Type précis** (ex. Société → SARL / SA / SAS, Création / Modification / Dissolution) → **3) Questionnaire** dynamique adapté → **4) Récapitulatif** qui affiche **la liste des actes à produire** déduite du type choisi.
- Le questionnaire de **Modification** doit rendre la **« fiche de modification » obligatoire** (rappel de la faille d'audit : la procédure écrite l'exige).

### 6.7 Bibliothèque de modèles d'actes
- Liste/galerie des modèles par forme et type (statuts SARL, DNSV, PV d'AGE, acte de cession de parts, acte de vente, contrat d'hypothèque, bail à construire, acte de partage…). Aperçu, version, dernière mise à jour, bouton « Utiliser ».

### 6.8 Écrans secondaires (esquisses)
- **Répertoire** (clients/parties), **Courriers** (modèles de courrier de transmission / accusé de réception), **Paramètres** (utilisateurs & rôles, barèmes/taux, types d'actes & checklists de contrôle).

---

## 7. États, responsive, accessibilité

- **Tous les états** : chargement (skeletons), vide (illustrations légères + CTA), erreur, succès. Les tableaux ont une pagination et un tri visibles.
- **Responsive** : optimisé desktop d'abord (outil de bureau), mais sidebar repliable et tableaux scrollables sur tablette.
- **Accessibilité** : contrastes AA, focus visibles (liseré or), tailles de police lisibles, navigation clavier (`⌘K`), libellés explicites.
- **Cohérence** : un seul style de badge par sémantique, une seule façon d'afficher montants/dates, icône unique par catégorie d'acte (réutilisée partout).

---

## 8. Données d'exemple réalistes (pour peupler les maquettes)

Utilise des données plausibles et cohérentes :
- Dossiers : `SOC-2026-0142` — *SARL « Faya Distribution »*, capital 50 000 000 GNF, gérant *M. Diallo*, étape **Révision** ; `VEN-2026-0098` — *Vente immeuble Kaloum*, 2 % impôts, étape **Formalités** ; `HYP-2026-0075` — *Hypothèque BIG-Guinée*, 1,5 % conservation, **Signature notaire** ; `SUC-2026-0061` — *Partage judiciaire — géomètre mandaté*.
- Utilisateurs : *Nène Aïssata Kanté* (clerc), *Mme Tafsir* (réviseuse), *Maître Fofana* (notaire), *M. Diallo* (formaliste).
- Échéances : « Retour APIP attendu sous 72 h », « Quittance impôts en attente ».
- KPIs : 38 dossiers en cours, 6 en attente de révision, 4 en attente de signature, 9 formalités en cours, 3 échéances < 72 h.

---

## 9. Livrables attendus de Claude Design

1. **Le système de design** (palette en tokens, typographies, échelles, états des composants).
2. Les écrans en haute fidélité, par ordre de priorité : **Tableau de bord → Liste des dossiers → Fiche dossier (avec stepper) → Grille de contrôle → Suivi des formalités → Assistant de création**.
3. Les **variantes d'états** clés (vide, chargement, validation de révision réussie, échéance dépassée).
4. Cohérence stricte sidebar + topbar sur tous les écrans.

**Mot d'ordre final :** sobriété premium, hiérarchie typographique forte, accents or rares, densité maîtrisée. Le design doit donner l'impression d'un outil **fiable, officiel et agréable à utiliser au quotidien** par des professionnels du droit.
