# Devbook — Ayelema Office Notarial

> Journal de développement vivant. Mis à jour à chaque session. Référence unique pour l'état du projet, les décisions techniques et la roadmap.

---

## Sommaire

1. [Vision produit](#1-vision-produit)
2. [Stack technique](#2-stack-technique)
3. [Architecture & structure](#3-architecture--structure)
4. [Design system](#4-design-system)
5. [État d'avancement](#5-état-davancement)
6. [Modules à développer](#6-modules-à-développer)
7. [Données & modèles](#7-données--modèles)
8. [Décisions techniques](#8-décisions-techniques)
9. [Problèmes connus & solutions](#9-problèmes-connus--solutions)

---

## 1. Vision produit

**Ayelema** est une application web de gestion des actes notariaux pour l'office notarial Ayelema (Guinée). Elle digitalise et fiabilise le cycle de vie complet d'un dossier d'acte.

### Objectifs prioritaires
1. **Piloter** l'ensemble des dossiers en cours (dashboard + file de travail par rôle)
2. **Standardiser le workflow** selon la catégorie d'acte (génération depuis modèles)
3. **Révision obligatoire** ⭐ — grille de contrôle bloquante avant toute signature
4. **Formalités** — suivi des démarches APIP / Impôts / Conservation / CNSS avec délais et taux
5. **Traçabilité** — journal d'activité horodaté sur chaque dossier

### Rôles utilisateurs
| Rôle | Accès & responsabilités |
|------|------------------------|
| **Clerc / Rédacteur** | Ouvre les dossiers, remplit les questionnaires, génère les actes |
| **Réviseur / Responsable** | Valide la grille de contrôle — peut renvoyer en correction |
| **Notaire (Maître)** | Signe les actes après révision validée, supervise, peut aussi créer des dossiers |
| **Formaliste** | Exécute les démarches administratives (dépôts, paiements, retours) |
| **Administrateur** | Gère utilisateurs, modèles, barèmes, types d'actes |

### Workflow central (8 étapes séquentielles)
```
Initialisation → Édition actes → Révision ⭐ → Signature client
→ Signature notaire → Formalités → Expédition → Clôturé
```
Chaque étape est bloquante : impossible de passer à la suivante sans valider la précédente.

---

## 2. Stack technique

| Couche | Technologie | Version |
|--------|-------------|---------|
| Backend | Laravel | ^13.8 |
| Auth | Laravel Breeze | ^2.4 |
| Bridge SPA | Inertia.js | ^2.0 |
| Frontend | React | ^18.2 |
| Build | Vite | ^8.0 (rolldown) |
| CSS | Tailwind CSS | ^3.2 |
| Composants | shadcn/ui (manuel) | — |
| Animations | Framer Motion | ^12.x |
| Icônes | lucide-react | — |
| UI primitifs | Radix UI | — |
| Utilitaires CSS | clsx + tailwind-merge + cva | — |
| API HTTP | Axios | — |
| Génération .docx | phpoffice/phpword | ^1.3 |

### Dépendances installées (npm)
```
framer-motion, lucide-react, clsx, tailwind-merge, class-variance-authority,
@radix-ui/react-{dialog, dropdown-menu, tabs, tooltip, checkbox, progress,
select, separator, avatar, label, slot, popover}, axios
```

### Dépendances installées (composer)
```
phpoffice/phpword — génération / remplissage de fichiers .docx (TemplateProcessor)
```

> ⚠️ **Windows** : Vite 8 (rolldown) nécessite `@rolldown/binding-win32-x64-msvc@1.1.2`
> installé manuellement — déjà fait dans ce projet.

> ⚠️ **Windows** : `php artisan pail` nécessite l'extension `pcntl` absente sur Windows.
> Supprimé du script `composer run dev` — utiliser `composer run dev` directement.

---

## 3. Architecture & structure

```
resources/js/
├── app.jsx                         # Entrée Inertia
├── bootstrap.js                    # Axios global
├── lib/
│   └── utils.js                    # cn() = clsx + tailwind-merge
├── data/
│   └── questionnaires.js           # Config partagée : QUESTIONNAIRES + TYPE_ACTE_CODE_MAP
├── Components/                     # (capital C — Windows insensible à la casse)
│   ├── GlobalSearch.jsx            # Palette ⌘K (fetch JSON /search)
│   └── ui/                         # Composants shadcn/ui adaptés
│       ├── button.jsx              # Variantes: default, seal, outline, ghost, success, warning, destructive
│       ├── badge.jsx
│       ├── card.jsx
│       ├── avatar.jsx
│       ├── input.jsx
│       ├── label.jsx
│       ├── select.jsx
│       ├── checkbox.jsx
│       ├── dialog.jsx
│       ├── dropdown-menu.jsx
│       ├── tabs.jsx
│       ├── tooltip.jsx
│       ├── progress.jsx
│       ├── separator.jsx
│       └── switch.jsx
├── Layouts/
│   └── AppLayout.jsx               # Layout global (sidebar + topbar, collapse, mobile)
└── Pages/
    ├── Dashboard.jsx               # KPIs réels, file d'attente, alertes, activité, catégories
    ├── Welcome.jsx
    ├── Auth/                       # Login, Register, etc. (Breeze)
    ├── Profile/                    # Profil utilisateur (Breeze)
    ├── Dossiers/
    │   ├── Index.jsx               # Liste paginée, recherche, filtres étape/catégorie
    │   ├── Show.jsx                # Fiche dossier (stepper + 6 onglets + 2 modals édition)
    │   ├── Create.jsx              # Wizard 4 étapes → POST /dossiers (questionnaire sectionné)
    │   └── Revision.jsx            # Grille de contrôle → PUT /dossiers/{ref}/revision
    ├── Revisions/
    │   └── Index.jsx               # File révisions en attente
    ├── Formalites/
    │   └── Index.jsx               # Cartes accordéon par dossier, actions PATCH
    ├── Repertoire/
    │   └── Index.jsx               # Répertoire parties/clients avec filtres
    ├── Modeles/
    │   └── Index.jsx               # CRUD modèles .docx — upload, activer/désactiver, générer
    ├── Courriers/
    │   └── Index.jsx               # Placeholder (module futur)
    └── Parametres/
        ├── Index.jsx               # Dashboard admin
        ├── Utilisateurs.jsx        # CRUD utilisateurs + modal création
        ├── TypesActes.jsx          # Liste types d'actes + toggle actif
        └── Baremes.jsx             # Placeholder (module 6 à venir)
```

```
app/
├── Enums/
│   ├── RoleUtilisateur.php         # clerc, reviseur, notaire, formaliste, administrateur
│   ├── EtapeDossier.php            # 8 étapes + label(), suivante(), precedente(), ordre(), ordered()
│   ├── CategorieActe.php           # societe, vente, hypotheque, bail, donation, succession, procuration, courrier
│   ├── StatutRevision.php          # en_attente, en_cours, valide, renvoye
│   └── StatutFormalite.php         # a_deposer, depose, en_attente, retour_recu, cloture
├── Models/
│   ├── User.php                    # role (cast enum), initiales, actif
│   ├── Dossier.php                 # SoftDeletes, scopes: enCours(), enRevision(), echeanceUrgente()
│   ├── TypeActe.php                # categorie cast CategorieActe::class, grilleActive(), scopeActif()
│   ├── Revision.php                # estValidable(), valider(), renvoyer()
│   ├── RevisionPoint.php           # point_id (string), etat, commentaire
│   ├── RevisionGrille.php          # groupes() → array par groupe
│   ├── Formalite.php               # estUrgente(), estDepassee(), heuresRestantes(), labelOrganisme()
│   ├── FormalitePiece.php
│   ├── Questionnaire.php           # donnees (json)
│   ├── Document.php
│   ├── Partie.php                  # initiales (accessor calculé depuis nom)
│   ├── JournalActivite.php         # enregistrer() static
│   ├── ModeleActe.php
│   ├── Facture.php
│   └── LigneFacture.php            # $table = 'lignes_factures' (explicite)
├── Policies/
│   ├── DossierPolicy.php           # viewAny, view, create (Clerc+Notaire+Admin), update, delete, avancer, reviser, gererFormalites
│   └── RevisionPolicy.php          # view, update, valider, renvoyer
├── Services/
│   ├── DossierStepService.php      # avancer(), reculer(), verifierPrerequis()
│   ├── ActesGeneratorService.php   # genererDocument() — PhpWord TemplateProcessor
│   └── NombreEnLettres.php         # convertir(float, devise) → majuscules FR (milliers, millions, milliards)
├── Http/
│   ├── Controllers/
│   │   ├── DashboardController.php
│   │   ├── DossierController.php   # index, create, store, show, update, destroy, avancer, updateQuestionnaire
│   │   ├── RevisionController.php  # index, show, update, valider, renvoyer
│   │   ├── FormaliteController.php # index, store, update
│   │   ├── ModeleActeController.php# index, store, update, destroy — upload .docx avec storeAs()
│   │   ├── DocumentController.php  # download, preview — téléchargement avec extension correcte
│   │   ├── SearchController.php    # index → JSON {results:[]}
│   │   ├── RepertoireController.php# index, autocomplete → JSON
│   │   ├── ParametresController.php# index, utilisateurs, storeUtilisateur, updateUtilisateur, typesActes, updateTypeActe
│   │   └── ProfileController.php
│   ├── Middleware/
│   │   ├── HandleInertiaRequests.php  # partage auth.user.can, notifications
│   │   └── RoleMiddleware.php         # alias 'role:' dans bootstrap/app.php
│   └── Requests/
│       ├── StoreDossierRequest.php    # authorize via DossierPolicy::create
│       └── UpdateDossierRequest.php   # objet, valeur, echeance, notaire_id, reviseur_id, formaliste_id
└── Notifications/
    ├── EcheanceDossierNotification.php
    └── RevisionEnAttenteNotification.php
```

```
routes/web.php                      # Toutes les routes Inertia + actions workflow
database/
├── migrations/                     # 13 migrations (toutes les tables)
└── seeders/
    ├── UserSeeder.php              # 5 utilisateurs (1 par rôle + admin)
    ├── TypeActeSeeder.php          # 19 types d'actes répartis en 8 catégories
    └── DossierSeeder.php           # 5 dossiers réalistes à différentes étapes
dictionnaire_balises.md             # Référence complète des variables ${...} des modèles .docx
```

---

## 4. Design system

### Palette de couleurs (tokens Tailwind)
| Token | Valeur | Usage |
|-------|--------|-------|
| `ink` | `#15263F` | Navigation, en-têtes, bouton primaire |
| `ink-medium` | `#1F3A5F` | Hover sidebar, surfaces secondaires |
| `ink-light` | `#2C4A75` | Variante claire |
| `seal` | `#B0863C` | Accent or — étape active, focus, badge validé |
| `seal-hover` | `#9A7331` | Hover accent |
| `seal-light` | `#F5EDD8` | Fond accent pâle |
| `app-bg` | `#FBFAF7` | Fond application (blanc cassé chaud) |
| `success` | `#15803D` / bg `#F0FDF4` / text `#166534` | Validé, conforme |
| `warning` | `#B45309` / bg `#FFFBEB` / text `#92400E` | Attention, échéance proche |
| `danger` | `#B91C1C` / bg `#FEF2F2` / text `#991B1B` | Rejet, dépassé, non conforme |

### Classes CSS custom (`resources/css/app.css`)
- `.font-ref` — monospace discret pour références dossiers, montants, CNI
- `.table-notarial` — table dense notariale (th/td/hover)
- `.badge-step-active/done/pending` — badges d'étapes workflow

### Typographie
- **Titres** : `font-serif` → Source Serif 4
- **Interface** : `font-sans` → Inter
- **Données** : `font-ref` → Geist Mono

### Tailles de texte custom
`text-display-lg`, `text-display`, `text-heading`, `text-subheading` (définies dans tailwind.config.js)

### Composants clés
- **Button** : variantes `seal`, `warning`, `success`, `destructive`, sizes `sm/lg/xl/icon/icon-sm`
- **Stepper** : workflow 8 étapes — fait (✓ vert) / courant (or) / futur (gris)
- **WorkflowStepper** : composant inline dans `Dossiers/Show.jsx`
- **Animations** : Framer Motion — fade+slide 6-8px, stagger 0.04-0.08s sur les listes

---

## 5. État d'avancement

### ✅ Complété — Infrastructure

- [x] Configuration Tailwind avec design tokens complets
- [x] Variables CSS (`app.css`) + classes custom (`font-ref`, `table-notarial`)
- [x] Polices Google Fonts (Inter + Source Serif 4)
- [x] 15 composants shadcn/ui (button, badge, card, avatar, input, label, select, checkbox, dialog, dropdown-menu, tabs, tooltip, progress, separator, switch)
- [x] `AppLayout` — sidebar collapsible + topbar + mobile overlay + GlobalSearch ⌘K
- [x] `GlobalSearch.jsx` — palette ⌘K, debounce 250ms, navigation clavier ↑↓↵
- [x] Routes `web.php` entièrement câblées

### ✅ Complété — Module 1 : DB & Modèles

- [x] 13 migrations (users, dossiers, types_actes, questionnaires, documents, revision_grilles, revisions, revision_points, formalites, formalite_pieces, parties, journal_activites, modeles_actes)
- [x] 5 Enums PHP (RoleUtilisateur, EtapeDossier, CategorieActe, StatutRevision, StatutFormalite)
- [x] 15 modèles Eloquent avec relations complètes et casts (+ Facture, LigneFacture)
- [x] 3 Seeders (5 utilisateurs, 19 types d'actes, 5 dossiers réalistes)

### ✅ Complété — Module 2 : Rôles & Permissions

- [x] `RoleMiddleware` (alias `role:` dans bootstrap/app.php)
- [x] `DossierPolicy` — 8 méthodes (viewAny, view, create, update, delete, avancer, reviser, gererFormalites)
- [x] `RevisionPolicy` — 4 méthodes (view, update, valider, renvoyer)
- [x] `HandleInertiaRequests` — partage `auth.user.can` + badges notifications
- [x] Navigation sidebar filtrée par rôle (revisions/formalites cachés si pas le bon rôle)

### ✅ Complété — Module 3 : Controllers + CRUD + Workflow

- [x] `DossierController` (index, create, store, show, update, destroy, avancer, **updateQuestionnaire**)
- [x] `RevisionController` (index, show, update, valider, renvoyer)
- [x] `FormaliteController` (index, store, update)
- [x] `DashboardController` (stats, file d'attente, alertes, activité, répartition catégories)
- [x] `DossierStepService` (avancer avec prérequis, reculer)
- [x] `StoreDossierRequest` / `UpdateDossierRequest` (objet, valeur, echéance, notaire_id, reviseur_id, formaliste_id)
- [x] Génération automatique référence format `{PREFIXE}-{ANNÉE}-{XXXX}`
- [x] Création automatique `Revision` quand dossier passe en étape Révision

### ✅ Complété — Module 4 : Génération de documents

- [x] `phpoffice/phpword` installé — `TemplateProcessor` pour remplissage de variables `${...}` dans `.docx`
- [x] `ActesGeneratorService::genererDocument()` — génère un `.docx` depuis un modèle ou un placeholder si modèle absent
- [x] Variables automatiques remplies : `${office.*}`, `${dossier.reference}`, `${dossier.objet}`, `${date_acte_jma}`, `${annee_lettres}`, `${date_acte_lettres}`
- [x] Variables questionnaire remplies automatiquement depuis `donnees` JSON — tous les préfixes `soc.*`, `pp.*`, `ger.*`, etc.
- [x] Auto-génération `*_lettres` depuis `*_chiffres` via `NombreEnLettres::convertir()`
- [x] `NombreEnLettres` service — conversion montants en lettres FR majuscules (jusqu'aux milliards, Francs Guinéens par défaut)
- [x] `datEnLettres()` — date en lettres notariale : "PREMIER JUILLET DEUX MILLE VINGT-SIX" (jour 1 = "PREMIER", pas "UN")
- [x] `ModeleActeController` — CRUD complet, upload `.docx` avec `storeAs()` dans `storage/app/private/modeles/`
- [x] `DocumentController` — téléchargement et prévisualisation avec extension `.docx` correcte
- [x] `Modeles/Index.jsx` — page fonctionnelle (upload, liste, activer/désactiver, générer par dossier)
- [x] `dictionnaire_balises.md` — référence complète de toutes les variables `${...}` utilisables dans les modèles
- [x] Fichier partagé `resources/js/data/questionnaires.js` — `QUESTIONNAIRES` (config champs) + `TYPE_ACTE_CODE_MAP` (code DB → clé questionnaire)

### ✅ Complété — Module 4b : Édition dossier

- [x] `ModalEditDossier` dans `Show.jsx` — modifier objet, valeur, échéance, notaire, réviseur, formaliste
- [x] `ModalEditQuestionnaire` dans `Show.jsx` — modifier tous les champs du questionnaire par sections
- [x] `InformationsTab` — affichage questionnaire groupé par sections avec labels lisibles, valeurs vides masquées
- [x] Route `PATCH /dossiers/{ref}/questionnaire` → `DossierController::updateQuestionnaire()`
- [x] `useEffect` sur `open` pour reset des formulaires modals à l'ouverture
- [x] `reviseurs`, `formalistes`, `notaires` passés en props Inertia sur `DossierController::show()`

### ✅ Complété — Pages connectées aux vraies données

- [x] **Dashboard** — stats réelles (enCours, enRevision, echeancesProches, formalitesUrgentes), file d'attente, alertes urgentes, activité récente, répartition par catégorie
- [x] **Dossiers/Index** — liste paginée (25/page), recherche texte, filtres étape + catégorie, pagination avec `prev_page_url` / `next_page_url`
- [x] **Dossiers/Show** — en-tête dossier, stepper workflow, 6 onglets (informations, documents, révision, formalités, parties, journal) tous avec données réelles, panneau latéral droit, 2 modals d'édition (dossier + questionnaire)
- [x] **Dossiers/Create** — wizard 4 étapes, `findTypeActeId()` pour mapper vers la DB, `router.post('/dossiers', {...})`, champs objet + notaire + réviseur + formaliste, questionnaire affiché par sections (Société / Associé unique / Gérant)
- [x] **Dossiers/Revision** — grille de contrôle, sauvegarde partielle (`PUT`), valider (`POST`), renvoyer avec motif (`POST`), dialog de confirmation, DEFAULT_GROUPES si pas de grille en DB
- [x] **Formalites/Index** — groupé par dossier, `PATCH` pour marquer déposé/retour reçu/toggle pièce
- [x] **Revisions/Index** — file révisions en attente avec liens directs
- [x] **Modeles/Index** — CRUD modèles .docx, upload, activation, génération de documents par dossier
- [x] **Courriers/Index** — placeholder (module futur)
- [x] **Parametres/Baremes** — placeholder (module 6 à venir)
- [x] **Parametres/Index, Utilisateurs, TypesActes** — fonctionnel (admin only)
- [x] **Repertoire/Index** — grille parties, recherche, filtres

### ✅ Complété — Module 7 : Recherche globale ⌘K

- [x] `SearchController` — recherche dossiers + parties, JSON `{results:[]}`
- [x] `GlobalSearch.jsx` — palette React avec AbortController (annule requêtes obsolètes)
- [x] Intégré dans AppLayout, déclenché par bouton topbar ou ⌘K/Ctrl+K

### ✅ Complété — Module 8 : Notifications & Alertes

- [x] `EcheanceDossierNotification`, `RevisionEnAttenteNotification` (database channel)
- [x] `AlerterEcheances` Artisan command
- [x] Planificateur hourly dans `routes/console.php`
- [x] Compteurs urgentes/révision dans topbar via `HandleInertiaRequests`

### ✅ Complété — Module 9 : Répertoire clients

- [x] `RepertoireController` (index + autocomplete JSON)
- [x] `Repertoire/Index.jsx`
- [x] Route `/repertoire/autocomplete` pour auto-complétion formulaires

### ✅ Complété — Module 10 : Paramètres & Administration

- [x] `ParametresController` — index, utilisateurs CRUD, types d'actes
- [x] Pages Paramètres/Index, Utilisateurs, TypesActes
- [x] Routes admin protégées par `middleware('role:administrateur')`

---

### 🔲 Reste à faire

#### Module 4 — Génération de documents (suite)
- [ ] Prévisualisation PDF dans le navigateur (conversion .docx → PDF côté serveur)
- [ ] Gestion des versions de modèles (historique, rollback)
- [ ] Signature électronique intégrée (module futur)

#### Module 5 — Grilles de révision dynamiques
- [ ] Interface admin pour configurer les grilles par type d'acte (table `revision_grilles`)
- [ ] La page `Revision.jsx` utilise `DEFAULT_GROUPES` si aucune grille en DB — à terme : grilles personnalisées par type d'acte
- [ ] Notification au rédacteur en cas de renvoi en correction

#### Module 6 — Barèmes & formalités avancées
- [ ] Page `Parametres/Baremes` à implémenter (actuellement placeholder)
- [ ] Taux configurables par type d'acte
- [ ] Génération des bordereaux de paiement

#### Fonctionnalités transverses
- [ ] Upload pièces jointes (CNI, photos parties)
- [ ] Export PDF d'un dossier
- [ ] Page `Courriers/Index` à implémenter (actuellement placeholder)
- [ ] `php artisan migrate:fresh --seed` — à exécuter pour initialiser la DB

---

## 6. Modules à développer (détail)

### Module 4 — Génération de documents (noyau complété)

Le noyau est opérationnel :
- Modèles Word (`.docx`) uploadés via `ModeleActeController`, stockés dans `storage/app/private/modeles/`
- `ActesGeneratorService::genererDocument()` — `TemplateProcessor` remplace toutes les variables `${...}`
- Variables disponibles documentées dans `dictionnaire_balises.md` (office, dossier, date, questionnaire)
- `NombreEnLettres::convertir()` + `datEnLettres()` — montants et dates en lettres notariales

Reste à implémenter :
- Prévisualisation PDF (LibreOffice headless ou service tiers)
- Versionnage des modèles

### Module 5 — Révision (grille de contrôle dynamique)
- Table `revision_grilles` par type d'acte (configurable admin) — **déjà en DB**
- Interface admin pour créer/éditer les grilles
- La page `Revision.jsx` utilise déjà `grille` si fourni par le serveur, sinon `DEFAULT_GROUPES`
- Verrouillage signature si révision non validée — **déjà implémenté** (`DossierStepService::verifierPrerequis`)

### Module 6 — Formalités avec calculs automatiques
- Taux configurables par type d'acte dans les paramètres
- Calcul automatique des montants (base × taux) — **`calculerMontant()` déjà dans `Formalite`**
- Génération des bordereaux de paiement
- Page `Parametres/Baremes` à implémenter

---

## 7. Données & modèles

### Tables (toutes créées et migrées)

```
users — id, name, email, password, role(enum), initiales, telephone, avatar, actif, timestamps

dossiers — id, reference(unique), type_acte_id, etape(enum), redacteur_id, reviseur_id,
           notaire_id, formaliste_id, objet, valeur, echeance, notes,
           etape_changed_at, deleted_at, timestamps

types_actes — id, code, label, categorie(enum), prefixe_reference, delai_jours,
              description, actes_requis(json), fiche_modification_obligatoire,
              actif, ordre, timestamps

questionnaires — id, dossier_id, donnees(json), timestamps

documents — id, dossier_id, nom, version, statut, timestamps

revision_grilles — id, type_acte_id, points(json), version, est_active, timestamps

revisions — id, dossier_id, reviseur_id, statut(enum), commentaire,
            valide_at, renvoye_at, timestamps

revision_points — id, revision_id, point_id(string), etat(string), commentaire, timestamps

formalites — id, dossier_id, organisme, statut(enum), taux, montant_base,
             montant_calcule, type_impot, retour_attendu, delai_heures,
             depose_at, retour_at, echeance_at, timestamps

formalite_pieces — id, formalite_id, label, est_fourni, fourni_at, timestamps

parties — id, dossier_id, nom, role, cni, telephone, adresse, email,
          photo_chemin, pieces(json), timestamps

journal_activites — id, dossier_id, user_id, action, type, meta(json), created_at

modeles_actes — id, type_acte_id, nom, chemin_fichier, version, est_actif, updated_by, timestamps

notifications — (table standard Laravel notifications)
```

### Relations clés
- `Dossier` BelongsTo `User` ×4 (redacteur, reviseur, notaire, formaliste)
- `Dossier` BelongsTo `TypeActe`
- `Dossier` HasMany `Document`, `Formalite`, `Partie`, `JournalActivite`
- `Dossier` HasOne `Revision`, `Questionnaire`
- `Revision` HasMany `RevisionPoint`
- `TypeActe` HasOne `RevisionGrille` (grilleActive = est_active=true)
- `Formalite` HasMany `FormalitePiece`

### Scopes importants (`Dossier`)
```php
scopeEnCours()          // whereNotIn('etape', ['cloture'])
scopeEnRevision()       // where('etape', 'revision')
scopeEcheanceUrgente()  // echeance <= now()+72h, non clôturé
```

### Conventions questionnaire
Les clés du champ `donnees` (JSON) sont préfixées par entité :
- `soc.*` — données société (ex. `soc.denomination`, `soc.capital_chiffres`, `soc.siege`)
- `pp.*` — personne physique / associé unique (ex. `pp.nom_complet`, `pp.cni`)
- `ger.*` — gérant (ex. `ger.nom_complet`, `ger.adresse`)

Les clés suffixées `_chiffres` génèrent automatiquement la variante `_lettres` via `NombreEnLettres::convertir()`.

---

## 8. Décisions techniques

| # | Décision | Raison |
|---|----------|--------|
| 1 | shadcn/ui créé manuellement (sans CLI) | Laravel + Inertia ne suit pas la structure Next.js attendue par le CLI |
| 2 | Tailwind v3 (pas v4) | `@tailwindcss/vite` v4 est dans package.json mais non utilisé ; vite.config.js utilise `@vitejs/plugin-react` standard |
| 3 | Vite 8 (rolldown) | Version imposée par le package.json initial. Sur Windows, nécessite `@rolldown/binding-win32-x64-msvc` installé manuellement |
| 4 | `AppLayout` remplace `AuthenticatedLayout` | Design notarial spécifique incompatible avec le layout Breeze générique |
| 5 | Framer Motion pour les animations | Spécifié dans le brief design — transitions sobres et fonctionnelles |
| 6 | `font-ref` pour les données techniques | Classe CSS custom — références dossiers, montants, CNI en mono discret |
| 7 | Route model binding via `{dossier:reference}` | `Route::resource(...)->parameters(['dossiers' => 'dossier:reference'])` — le paramètre s'appelle `dossier`, la clé de binding est `reference`. Les controllers DOIVENT avoir `Dossier $dossier` (pas `string $reference`) |
| 8 | `auth.user.can` (pas `auth.can`) | `HandleInertiaRequests` niche les permissions dans `auth.user.can`. Dans les pages React : `const can = auth?.user?.can ?? {}` |
| 9 | `RevisionPolicy::update` utilise `$revision->dossier` | Quand `$revision` est `new Revision()` sans dossier, la policy retourne false. Contournement : utiliser `DossierPolicy::reviser` dans `RevisionController::show` pour `can.update` |
| 10 | `DEFAULT_GROUPES` dans `Revision.jsx` | Si aucune `RevisionGrille` n'est configurée pour ce type d'acte, la page utilise 3 groupes / 7 points par défaut. Les IDs sont `p1`…`p7` — cohérents avec ce que le contrôleur sauvegarde |
| 11 | pail supprimé du script dev | `php artisan pail` requiert l'extension `pcntl` absente sous Windows. Le script `composer run dev` lance maintenant uniquement : server, queue, vite |
| 12 | `DossierPolicy::create` inclut Notaire | `RoleUtilisateur::peutOuvrir()` n'inclut PAS Notaire, mais la policy oui. `HandleInertiaRequests` utilise `$user->can('create', Dossier::class)` pour être cohérent |
| 13 | `TemplateProcessor` via `DIRECTORY_SEPARATOR` | Sur Windows, PhpWord échoue à écrire si le répertoire de sortie n'existe pas. `mkdir()` natif avec `DIRECTORY_SEPARATOR` résout le problème (pas `Storage::makeDirectory()`) |
| 14 | `Storage::disk('local')->path()` pour les modèles | Les modèles `.docx` sont dans `storage/app/private/` (disque `local`). `public_path()` ou `storage_path('app/public/')` pointent ailleurs — utiliser `Storage::disk('local')->path($chemin)` |
| 15 | `TYPE_ACTE_CODE_MAP` dans `questionnaires.js` | Les codes DB (`SOC-SARL`, `VTE-IMM`…) ne correspondent pas aux clés frontend (`creation_sarl`, `vente_immeuble`…). La map sert de pont sans modifier la DB ni les modèles |
| 16 | `NombreEnLettres::convertir(montant, '')` | Passer une chaîne vide comme devise produit le nombre en lettres sans suffixe, utile pour les dates (années, jours). Passer `'Francs Guinéens'` (défaut) pour les montants |
| 17 | `datEnLettres()` — jour 1 = "PREMIER" | Convention notariale française : le 1er du mois s'écrit "PREMIER", pas "UN". Les autres jours passent par `NombreEnLettres::convertir()` |
| 18 | Champs questionnaire préfixés (`soc.*`, `ger.*`) | Les variables dans les modèles `.docx` utilisent la notation pointée `${soc.denomination}`. Les IDs des champs React DOIVENT correspondre exactement pour que `TemplateProcessor::setValue()` les remplace |

---

## 9. Problèmes connus & solutions

### ✅ Résolus

| Problème | Solution |
|----------|----------|
| `npm install` échoue — conflit peer deps | `npm install --legacy-peer-deps` |
| `vite build` échoue — rolldown binding manquant | `npm install --legacy-peer-deps "@rolldown/binding-win32-x64-msvc@1.1.2"` |
| `./bootstrap` introuvable au build | Créer `resources/js/bootstrap.js` |
| `composer run dev` crashe à cause de pail (pcntl manquant Windows) | Supprimé `php artisan pail --timeout=0` du script `dev` dans `composer.json` |
| 404 sur `/dossiers/{ref}` et `/dossiers/{ref}/revision` | `show(string $reference)` ne correspond pas au paramètre de route `dossier` — corrigé en `show(Dossier $dossier)` |
| 404 sur `/dossiers/nouveau` | URL incorrecte — la route est `/dossiers/create`. Corrigé dans AppLayout, Dashboard, Dossiers/Index |
| 404 sur `/revisions`, `/modeles`, `/courriers`, `/parametres/baremes` | Pages React manquantes — créées |
| `authorize()` undefined sur Controller | `DossierController` n'héritait pas du trait `AuthorizesRequests`. Ajouté `use \Illuminate\Foundation\Auth\Access\AuthorizesRequests` |
| `groupBy('categorie')` → TypeError sur enum comme clé | `TypeActe.categorie` casté en enum → `groupBy(fn($t) => $t->categorie->value)` |
| `StoreDossierRequest::authorize()` excluait Notaire | `peutOuvrir()` → remplacé par `$this->user()?->can('create', Dossier::class)` |
| `can.update` toujours false sur grille de révision (nouveau dossier) | `new Revision()` sans dossier → policy retourne false. Corrigé : `can('reviser', $dossier)` via `DossierPolicy::reviser` |
| `creerDossier` false pour Notaire dans la sidebar | `peutOuvrir()` excluait Notaire → `HandleInertiaRequests` utilise désormais `$user->can('create', Dossier::class)` |
| Sauvegarde grille partielle → 422 | `etat` validé comme `required` mais les points non évalués ont `null` → changé en `nullable` + `continue` si null |
| Toutes les pages affichaient des données factices | Réécriture complète de 6 pages pour utiliser `usePage().props` |
| Boutons d'action sans handler (Create, Revision, Formalites) | Ajout de `router.post/put/patch` dans les composants |
| PhpWord `RuntimeException: Failed to create` sur Windows | `mkdir()` natif PHP avec `DIRECTORY_SEPARATOR` au lieu de `Storage::makeDirectory()` — PhpWord requiert un chemin absolu avec séparateurs natifs |
| `SQLSTATE: Table 'lignes_factures' doesn't exist` | `LigneFacture` model utilisait la convention `ligne_factures` — ajouté `protected $table = 'lignes_factures'` explicitement |
| Téléchargement `.docx` renvoie un fichier `.htm` | Double préfixe `public/public/` dans le chemin — corrigé en utilisant `storage_path('app/public/' . $doc->chemin)` |
| Téléchargement sans extension de fichier | `response()->download()` avec paramètre `$filename` explicite incluant `.docx` |
| `TemplateProcessor` "File not found" | Modèle cherché dans `public/` au lieu de `storage/app/private/` — corrigé avec `Storage::disk('local')->path($storagePath)` |
| Variables `${soc.denomination}` non remplacées | Champs questionnaire utilisaient des IDs courts (`denomination`) sans préfixe (`soc.denomination`) — mis à jour dans `questionnaires.js` |
| `${date_acte_lettres}` non remplacée dans les modèles | Variable non générée dans `ActesGeneratorService` — ajout de `datEnLettres()` private method + appel dans `remplirInfosDossier()` |
| Sections Société/Associé/Gérant absentes dans Create.jsx | Boucle `.map((field) =>` sans gestion du prop `section` — corrigé avec `React.Fragment` + détection `field.section` par comparaison d'index |

### ⚠️ À surveiller

| Sujet | Détail |
|-------|--------|
| DB non initialisée | Exécuter `php artisan migrate:fresh --seed` avant le premier démarrage |
| `DEFAULT_GROUPES` dans Revision | 3 groupes statiques utilisés si pas de `RevisionGrille` en DB pour le type d'acte. Normal pour l'instant |
| Pages Auth (Login/Register) | Utilisent encore `GuestLayout` de Breeze — design non unifié, fonctionnel |
| `recharts` installé | Non encore utilisé — prévu pour graphiques dashboard (module futur) |
| Importation `@/components/*` vs `@/Components/*` | Windows insensible à la casse : les deux fonctionnent. Sur Linux (déploiement) : vérifier la cohérence de la casse |
| PDF preview non implémentée | Génération `.docx` OK, mais pas de prévisualisation navigateur. Nécessite LibreOffice headless ou service tiers |

---

*Dernière mise à jour : 01/07/2026 — Modules 1–4, 4b, 7–10 complétés. Génération de documents .docx opérationnelle (PhpWord TemplateProcessor). Édition dossier + questionnaire depuis Show.jsx. Dictionnaire des balises documenté.*
