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
| **Comptable** | Facturation et encaissements — transversal à tous les dossiers (pas de `comptable_id`) |
| **Administrateur** | Gère utilisateurs, modèles, barèmes, types d'actes |

> ⚠️ **Un utilisateur peut porter plusieurs rôles** depuis le 2026-07-06 : table `role_user` (modèle `UserRole`), colonne `users.role` **supprimée**. Utiliser `$user->hasRole()` / `hasAnyRole()` / `User::withRole()`, jamais `$user->role`.

### Workflow central (7 étapes séquentielles)
```
Initialisation → Édition actes → Certification ⭐ → Signature → Formalités → Expédition → Clôturé
```
Chaque étape est bloquante : impossible de passer à la suivante sans valider la précédente.
`EtapeDossier` est la source de vérité (`initialisation`, `edition`, `revision`, `signature`, `formalites`, `expedition`, `cloture`) — son `ordre()` pilote le stepper, `suivante()`/`precedente()` les transitions.

**Ce que porte chaque étape** (les deux premières ont été séparées le 2026-08-04) :

| Étape | Métier | Prérequis pour en sortir |
|---|---|---|
| **Initialisation** | Constituer le dossier : questionnaire, personnes et leurs pièces d'identité, **accord signé du client** | objet, notaire, certificateur, toutes les pièces fournies, accord signé (`verifierInitialisation()`) |
| **Édition actes** | Produire et corriger les actes, générés à l'entrée depuis les `ModeleActe` | au moins un acte produit (`verifierEdition()`) |
| Certification ⭐ | Grille de contrôle bloquante | révision validée |
| Signature | Constater les deux dates de signature | les deux dates renseignées |
| Formalités | Démarches auprès des organismes | tout retour reçu |
| Expédition | Lettres de transmission | inventaire de clôture entièrement vérifié |
| Clôturé | — | étape terminale, dossier **figé** ([décision #37](#8-décisions-techniques)) |

> ⚠️ **Changement du 2026-07-03** : les étapes séparées « Signature client » et « Signature notaire » ont été supprimées. La migration `2026_07_03_184712_migrate_signature_etapes_to_formalites` bascule tout dossier qui s'y trouvait vers `formalites` (pas de rollback possible — voir [décision #19](#8-décisions-techniques)).

> ⚠️ **Réintroduction du 2026-08-04** : l'étape `Initialisation` **existe à nouveau**, en tête du workflow. Elle avait été supprimée, la création déposant directement en `Édition` — mais `verifierEdition()` y cumulait alors deux métiers, et les actes étaient générés dès la création, donc sur un questionnaire que le client n'avait pas encore validé. Le libellé de l'étape `revision` reste « Certification des actes » côté UI alors que la valeur en base est `revision` (les modèles/tables gardent le nom `Revision`).

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
├── lib/
│   └── blocantsEtape.js           # (2026-08-12) Ce qui manque pour avancer dans l'assistant —
│                                   #   énuméré, jamais résumé en un booléen. blocantsEtape(),
│                                   #   blocantsParSection(), compterParSection(), allerAuBlocant()
│                                   #   ⚠️ passe par groupFieldsBySection : seul le 1er champ
│                                   #      d'une section porte `section`, les autres l'héritent
├── Components/                     # (capital C — Windows insensible à la casse)
│   ├── GlobalSearch.jsx            # Palette ⌘K (fetch JSON /search)
│   ├── PasswordRequirements.jsx    # Checklist live (12 car., maj/min, chiffre, spécial) — Register/Reset/Profil/Utilisateurs
│   ├── NotificationDropdown.jsx    # Vraie liste de notifications (remplace l'ancien Tooltip de la cloche)
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
│   └── AppLayout.jsx               # Layout global (sidebar + topbar, collapse, mobile, NotificationDropdown)
├── hooks/
│   └── useRealtimeNotifications.js # Écoute Echo/Pusher (canal privé App.Models.User.{id}) → badge + notif navigateur
└── Pages/
    ├── Dashboard.jsx               # KPIs réels, file d'attente, alertes, activité, catégories
    ├── Welcome.jsx
    ├── Auth/                       # Login, Register, etc. (Breeze) + VerifyOtp.jsx (challenge 2FA)
    ├── Profile/                    # Profil utilisateur (Breeze) + Partials/TrustedDevicesForm.jsx (appareils de confiance)
    ├── Dossiers/
    │   ├── Index.jsx               # Liste paginée, recherche, filtres étape/catégorie
    │   ├── Show.jsx                # Fiche dossier (stepper + onglets contextuels + dialog Historique + 2 modals édition)
    │   ├── Create.jsx               # Wizard 4 étapes → POST /dossiers (questionnaire sectionné)
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
    │   └── Index.jsx               # CRUD complet — KPIs, répartition par type, recherche, modal ModalCourrier
    └── Parametres/
        ├── Index.jsx               # Dashboard admin
        ├── Utilisateurs.jsx        # CRUD utilisateurs + modal création
        ├── TypesActes.jsx          # Liste types d'actes + toggle actif
        └── Baremes.jsx             # CRUD complet — accordéon par type d'acte, légende organismes, toggle actif
```

```
app/
├── Enums/
│   ├── RoleUtilisateur.php         # clerc, reviseur, notaire, formaliste, administrateur
│   ├── EtapeDossier.php            # 6 étapes (initialisation, edition, revision, formalites, expedition, cloture) + label(), suivante(), precedente(), ordre(), ordered()
│   ├── CategorieActe.php           # societe, vente, hypotheque, bail, donation, succession, procuration, courrier
│   ├── StatutRevision.php          # en_attente, en_cours, valide, renvoye
│   └── StatutFormalite.php         # a_deposer, depose, en_attente, retour_recu, rejete
│                                   #   ⚠️ `cloture` supprimé : `retour_recu` est l'état TERMINAL.
│                                   #   estTerminee() (match exhaustif), exigeCorrection(),
│                                   #   valeursTerminees() — voir décision #35
├── Models/
│   ├── User.php                    # role (cast enum), initiales, actif
│   ├── Dossier.php                 # SoftDeletes, scopes: enCours(), enRevision(), echeanceUrgente()
│   ├── TypeActe.php                # categorie cast CategorieActe::class, grilleActive(), scopeActif()
│   ├── Revision.php                # estValidable(), valider(), renvoyer()
│   ├── RevisionPoint.php           # point_id (string), etat, commentaire
│   ├── RevisionGrille.php          # groupes() → array par groupe
│   ├── Formalite.php               # estUrgente(), estDepassee(), heuresRestantes(), labelOrganisme(), calculerMontant() (mécanisme séparé de Bareme, non unifié) ; pieces() → DocumentFichier polymorphe
│   ├── Questionnaire.php           # donnees (json)
│   ├── DocumentFichier.php         # (ajouté 2026-07-24) polymorphe Dossier|Formalite|Partie|Societe —
│   │                               #   nouvelleVersion(), restaurerVersion(), dossierGouvernant(),
│   │                               #   sujetAutorisation()/estPieceDeRegistre() (2026-08-11) —
│   │                               #   remplace Document + FormalitePiece
│   │                               #   ⚠️ `Societe` est le seul rattachement SANS dossier gouvernant
│   ├── DocumentVersion.php         # (ajouté 2026-07-24) historique des versions d'un DocumentFichier
│   ├── Partie.php                  # initiales (accessor), client_id nullable → Client ; pieces() → DocumentFichier polymorphe
│   │                               #   + (2026-08-03) donnees_prefixe/bloc/index : emplacement dans le questionnaire,
│   │                               #   estProjetable(), scopeProjetables() — voir décision #33
│   ├── JournalActivite.php         # enregistrer() static
│   ├── ModeleActe.php
│   ├── Facture.php
│   ├── LigneFacture.php            # $table = 'lignes_factures' (explicite)
│   ├── DossierBrouillon.php        # (2026-08-03) saisie inachevée de l'assistant (etat json + pièces sur
│   │                               #   le disque privé) — pas un Dossier, voir décision #34
│   ├── Client.php                  # personne physique|morale réutilisable, hasMany(Partie), estPersonnePhysique(), nomComplet()
│   │                               #   Source de vérité de l'identité depuis le 2026-08-03 (décision #33) —
│   │                               #   `donnees` du questionnaire n'en est qu'une projection dérivée
│   ├── Societe.php                 # **Registre réutilisable** depuis le 2026-08-11 — source de vérité de la
│   │                               #   personne morale, symétrique de Client pour les personnes physiques.
│   │                               #   depuisQuestionnaire()/versQuestionnaire() (les 2 sens du mapping soc.*),
│   │                               #   normaliserDenomination() (partagée avec la règle 4), associesConnus(),
│   │                               #   formeEnum()/formeLabel() (délègue à FormeSociete), scopeRecherche()
│   │                               #   + (2026-08-11) piecesConstitutives() (morphMany DocumentFichier),
│   │                               #     PIECES_CONSTITUTIVES, exigePiecesConstitutives() (= dossier_id null),
│   │                               #     piecesConstitutivesChecklist(), gabaritStatutsDocx()
│   │                               #   + (2026-08-12) gerantActuel() : direction['gerant'] puis repli sur
│   │                               #     les parties du dossier d'origine (ROLES_DIRECTION couvre les
│   │                               #     9 formes : gerant/president/pca/dg/administrateur/associe_unique)
│   │                               #   ⚠️ dossier() = dossier de CONSTITUTION ; Dossier::societe() est l'autre sens
│   ├── BienImmobilier.php          # belongsTo(Dossier), champs vente ET champs bail dans la même table, loyerTotalBail()
│   ├── Banque.php                  # belongsTo(Dossier) — bloc hypothèque/crédit
│   ├── Bareme.php                  # belongsTo(TypeActe), scope actif(), calculerMontant(valeurActe) — taux% ou montant_fixe
│   ├── Courrier.php                # reference, dossier(), redacteur()→User, scopes brouillon()/envoye(), typeLabel()
│   ├── Setting.php                 # clé/valeur (PK string 'key'), get/set/setMany/all statiques
│   ├── UserOtpCode.php             # code OTP (hash sha256), attempts, expires_at, consumed_at — table dédiée éphémère
│   └── UserTrustedDevice.php       # appareil de confiance (30j), token_hash, scope actif() — révocable depuis le profil
├── Policies/
│   ├── DossierPolicy.php           # viewAny, view, create, update, reassigner, delete, avancer, reviser,
│   │                               #   genererDocuments, gererPieces (2026-08-11 : Initialisation+Édition,
│   │                               #     dépôt des pièces des personnes — genererDocuments excluait l'étape
│   │                               #     qui les exige), modifierQuestionnaire, enregistrerSignatures,
│   │                               #   gererFormalites, gererFacturation, cloturerDocuments, genererCourriers
│   │                               #   ⚠️ Ordre d'évaluation : gel → étape → rôle → assignation (décision #38)
│   │                               #   estFige() : un dossier clôturé n'accepte plus rien (décision #37)
│   ├── RevisionPolicy.php          # view, update, valider, renvoyer — modèle du bon ordre
│   ├── CourrierPolicy.php          # create, view, update, delete — cloisonne par assignation au dossier
│   ├── ClientPolicy.php            # (2026-08-04) viewAny, view, create, update — création/modification
│   │                               #   réservées aux rôles pouvant ouvrir un dossier : `update` régénère
│   │                               #   les actes de tous les dossiers liés (décision #33)
│   ├── SocietePolicy.php           # (2026-08-11) viewAny, view, create, update — même raisonnement que
│   │                               #   ClientPolicy : restriction par RÔLE et non par dossier (une société est
│   │                               #   liée aux dossiers de plusieurs clercs). Pas de delete
│   └── DemandePolicy.php           # viewAny, view, create, update
├── Services/
│   ├── NotificationService.php     # (2026-08-03) routage unique : destinataires(), pool(), envoyer(), notifierRoles()
│   ├── ClientProjectionService.php # (2026-08-03) projette l'identité des fiches Client dans questionnaires.donnees
│   │                               #   reprojeter(), reprojeterDossiersDuClient(), regenererDocumentsNonVerrouilles()
│   ├── SocieteMutationService.php  # (2026-08-11) applique une modification statutaire à la fiche du registre
│   │                               #   (siège, capital, parts, objet, gérance) — appelé à l'entrée en Expédition,
│   │                               #   quand les retours de formalités RCCM rendent la modification opposable.
│   │                               #   Un JournalActivite par application, détaillant chaque champ modifié
│   ├── DossierStepService.php      # avancer(), reculer(), verifierPrerequis() — adapté aux 6 étapes
│   ├── ActesGeneratorService.php   # genererDocument() — PhpWord TemplateProcessor, moteur générique clé/valeur (gère déjà bien./bq./bail. sans code dédié)
│   ├── FacturationService.php      # genererFacture(), simuler() — génère Facture+LigneFacture depuis les Bareme actifs du type d'acte, deduireAssiette()
│   ├── NombreEnLettres.php         # convertir(float, devise) → majuscules FR (milliers, millions, milliards)
│   └── TwoFactorAuthenticationService.php  # generateAndSend(), verify(), resend(), rememberDevice(), isDeviceTrusted()
├── Console/Commands/
│   ├── AlerterEcheances.php        # ayelema:alerter-echeances — hourly, anti-doublon 12h
│   ├── AlerterFormalites.php       # ayelema:alerter-formalites — hourly, même logique anti-doublon
│   ├── NotificationsDiagnostic.php # (2026-08-03) ayelema:notifications-diagnostic [--mail=adresse]
│   │                               #   transport, queue/scheduler, destinataires à risque, test SMTP réel
│   ├── BackfillSocietes.php        # (2026-08-11) ayelema:societes-backfill [--appliquer]
│   │                               #   dry-run par défaut — crée les fiches du registre depuis les
│   │                               #   questionnaires existants ; les doublons de dénomination sont
│   │                               #   signalés, JAMAIS fusionnés
│   └── PurgerBrouillons.php        # (2026-08-03) ayelema:brouillons-purger [--jours=60] [--supprimer]
│                                   #   dry-run par défaut — voir décision #34
├── Http/
│   ├── Controllers/
│   │   ├── DashboardController.php
│   │   ├── DossierController.php   # index, create, store, show, update, destroy, avancer, updateQuestionnaire
│   │   ├── RevisionController.php  # index, show, update, valider, renvoyer
│   │   ├── FormaliteController.php # index, store, update
│   │   ├── ModeleActeController.php# index, store, update, destroy — upload .docx avec storeAs()
│   │   ├── DocumentController.php  # download, preview, versions, restaurerVersion — opère sur DocumentFichier (module GED unifié)
│   │   ├── SearchController.php    # index → JSON {results:[]}
│   │   ├── RepertoireController.php# index, autocomplete → JSON
│   │   ├── CourrierController.php  # index (filtres/stats/recherche), store, update (gère envoye_at), destroy
│   │   ├── ParametresController.php# index, utilisateurs, storeUtilisateur, updateUtilisateur, typesActes, updateTypeActe, baremes, storeBareme, updateBareme, destroyBareme, securite, updateSecurite
│   │   ├── NotificationController.php # index, markAsRead, markAllAsRead — vraie liste (table notifications), pas seulement les compteurs
│   │   ├── ProfileController.php   # edit/update/destroy + revokeTrustedDevice (appareils de confiance)
│   │   └── Auth/
│   │       ├── AuthenticatedSessionController.php  # store() : login différé si otp_enabled + appareil non fiable
│   │       └── TwoFactorChallengeController.php    # create/store/resend — challenge OTP (routes guest, session non authentifiée)
│   ├── Middleware/
│   │   ├── HandleInertiaRequests.php  # partage auth.user.can, notifications (dont unreadNotificationsCount)
│   │   └── RoleMiddleware.php         # alias 'role:' dans bootstrap/app.php
│   └── Requests/
│       ├── StoreDossierRequest.php    # authorize via DossierPolicy::create
│       ├── UpdateDossierRequest.php   # objet, valeur, echeance, notaire_id, reviseur_id, formaliste_id
│       └── Auth/LoginRequest.php      # authenticate() : Auth::validate() (ne connecte pas), retourne le User pour le flux OTP
└── Notifications/
    ├── Concerns/CanauxNotification.php      # (2026-08-03) trait partagé : via() database+broadcast+mail conditionnel
    │                                        #   + toBroadcast() en onConnection('sync') — voir décision #31
    ├── EcheanceDossierNotification.php      # déclenchée par AlerterEcheances (échéance < 72h)
    ├── FormaliteUrgenteNotification.php     # déclenchée par AlerterFormalites (formalité urgente/dépassée)
    ├── RevisionEnAttenteNotification.php    # certificateur seul (plus les 4 ayants droit)
    ├── CertificationValideeNotification.php # (2026-08-03) notaire + rédacteur
    ├── SignatureClientEnAttenteNotification.php # notaire (repli pool Notaire)
    ├── FormalitesAFaireNotification.php     # (2026-08-03) formaliste — entrée en étape Formalités
    ├── DossierRenvoyeNotification.php       # (2026-08-03) rédacteur, avec le motif du renvoi
    ├── DossierAssigneNotification.php       # (2026-08-03) nouvel assigné (certificateur/notaire/formaliste)
    ├── NouvelleDemandeNotification.php      # pool Notaire + auteur du lien d'intake
    ├── DemandeConvertieNotification.php     # auteur de la demande
    └── TwoFactorCodeNotification.php        # mail seul, n'utilise pas le trait (voir décision #28)
```

> ⚠️ **Aucune notification n'est `ShouldQueue`** — `database` et `broadcast` partent en synchrone, `mail` aussi. Voir décisions #28 et #31.
> `SignatureNotaireEnAttenteNotification` a été **supprimée** le 2026-08-03 : elle n'était jamais envoyée (code mort depuis la fusion en une étape `Signature` unique).

```
routes/web.php                      # Toutes les routes Inertia + actions workflow
routes/auth.php                     # Breeze + routes two-factor.challenge/verify/resend (login différé OTP)
routes/channels.php                 # Broadcast::channel('App.Models.User.{id}', ...) — canal privé Pusher
routes/console.php                  # Scheduler hourly : ayelema:alerter-echeances, ayelema:alerter-formalites
database/
├── migrations/                     # 13 migrations (toutes les tables)
└── seeders/
    ├── UserSeeder.php              # 5 utilisateurs (1 par rôle + admin)
    ├── TypeActeSeeder.php          # 19 types d'actes répartis en 8 catégories
    ├── DossierSeeder.php           # 5 dossiers réalistes à différentes étapes
    ├── ModeleActeSeeder.php
    └── BaremeSeeder.php            # Barèmes par défaut (APIP, Impôts, Conservation, CNSS, Notaire…)
dictionnaire_balises.md                            # Référence des variables ${...} des modèles .docx (à recouper avec le dictionnaire ci-dessous, plus large)
Analyse_et_Prompt_Generation_Modeles_Ayelema.md    # (ajouté 2026-07-06) Analyse des 47 modèles Word reçus + dictionnaire de blocs
                                                    # réutilisables (pp./pm./soc./bien./bq./bail./cr./fac.) + plan d'implémentation. Voir §6 ci-dessous.
Documents reçus/                                   # (ajouté 2026-07-06) 64 fichiers modèles réels (.docx/.doc/.xls/.pdf) répartis en 9 sous-dossiers
                                                    # (SARL, SARLU, SAS, SASU, vente avec/sans TF, baux, courriers, questionnaires PDF).
                                                    # ⚠️ Un seul fichier normalisé avec balises ${...} (STATUTS_SARLU_balises.docx) — les 63 autres
                                                    # sont encore bruts (pointillés/MAJUSCULES), non exploitables tels quels par ActesGeneratorService.
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
- **Stepper** : workflow 6 étapes — fait (✓ vert) / courant (or) / futur (gris)
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
- [x] **Dossiers/Show** — en-tête dossier, stepper workflow, onglets contextuels (informations, documents, révision, formalités, expédition, facturation) tous avec données réelles ; l'onglet "Parties" a été fusionné dans "Informations" (gestion des personnes désormais inline) et l'ancien onglet "Journal" a été renommé **Historique**, sorti de la barre d'onglets et déplacé dans un dialog dédié (bouton, pas onglet) ; les onglets propres à une étape non encore atteinte sont estompés (`opacity-40`) plutôt que masqués — jamais rendus inaccessibles, juste visuellement désaccentués (voir [décision #29](#8-décisions-techniques)) ; panneau latéral droit, 2 modals d'édition (dossier + questionnaire)
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

### ✅ Complété — Module 8 : Notifications & Alertes (email + temps réel + push)

- [x] 10 notifications métier (voir l'arborescence §3) — canal `mail` (conditionné à `notifications_email`) + `broadcast` (temps réel) + `database` (historique), via le trait partagé `CanauxNotification`. **Aucune n'est `ShouldQueue`** (voir décisions #28 et #31)
- [x] `AlerterEcheances` + `AlerterFormalites` (Artisan commands, hourly, anti-doublon 12h)
- [x] Planificateur hourly dans `routes/console.php`
- [x] Temps réel via **Pusher** (Channels) — `routes/channels.php` (canal privé `App.Models.User.{id}`), `laravel-echo`/`pusher-js` initialisés dans `resources/js/bootstrap.js`, écoute dans `resources/js/hooks/useRealtimeNotifications.js` (badge instantané + notification navigateur native si permission accordée — voir [décision #27](#8-décisions-techniques) pour les limites de ce mécanisme)
- [x] `NotificationController` (index/markAsRead/markAllAsRead) + `NotificationDropdown.jsx` — vraie liste déroulante avec marquage lu, remplace l'ancien `Tooltip` statique dans `AppLayout.jsx`
- [x] Compteurs urgentes/révision + `unreadNotificationsCount` dans topbar via `HandleInertiaRequests`
- [x] SMTP configuré (Hostinger) et clés Pusher renseignées dans `.env` (non placeholders)

### ✅ Complété — Rendre visibles les conditions bloquantes (2026-08-03)

Signalé en usage réel : « la section Accord client passe inaperçue tout en bas, pourtant elle est nécessaire pour aller à l'étape suivante ». Symptôme du même fond que l'audit ci-dessous : une condition bloquante était appliquée par le serveur sans que l'interface la mette en avant.

- [x] `resources/js/Components/Dossiers/AccordClientCard.jsx` — extrait d'`InformationsTab` (qui portait son état local alors que rien d'autre dans l'onglet ne s'en sert) et placé **en tête de l'onglet Informations**, avant la fiche dossier. En dernière position, l'étape se découvrait en scrollant
- [x] La carte est **autonome** : elle offre les deux actions de la boucle (« Imprimer la fiche » + « Téléverser l'accord signé »), pour ne pas obliger à redescendre jusqu'à la carte Fiche dossier. Les repères de position (« ci-dessus »/« ci-dessous ») ont été retirés du message en conséquence
- [x] **Traitement visuel selon l'état** : en attente → anneau ambré, icône d'alerte et mention « Requis pour passer à la certification » ; reçu → carte discrète. Une action faite n'a plus à occuper l'attention
- [x] Message d'attente réécrit en instruction actionnable (imprimer → faire signer → téléverser) plutôt qu'en constat
- [x] **Les blocages de l'en-tête sont devenus cliquables.** `getStepBlockers()` renvoie désormais `{ texte, tab?, ancre? }` au lieu de chaînes : cliquer « L'accord signé du client n'a pas été téléversé » bascule sur l'onglet Informations et fait défiler jusqu'à la carte. Idem pour les pièces des parties, la certification, les signatures, les documents et courriers obligatoires. Les erreurs serveur (chaînes brutes) sont normalisées au même format pour n'avoir qu'un seul rendu
- [x] Ancres DOM partagées `ANCRE_ACCORD_CLIENT` / `ANCRE_PIECES_PARTIES`, et `?focus=accord` sur le même principe que `?focus=pieces`

### 🔍 Audit — Cohérence des prérequis d'étape (2026-08-03)

Après le correctif de l'étape Formalités, audit demandé sur l'ensemble des étapes. **Trois divergences trouvées, toutes de la même famille** : la règle d'avancement est écrite en **trois endroits** (le serveur qui décide, l'indicateur `peutAvancer` de la liste, et `getStepBlockers()` de la fiche dossier), et rien ne les tenait synchronisés.

- [x] **`peutAvancer` (liste) mentait à l'étape Expédition.** Un `default => true` couvrait `Expedition` et `Cloture` alors que `verifierExpedition()` exige que les documents *et* courriers marqués obligatoires soient signés/cachetés : le bouton « Avancer » était actif, le clic échouait. Le `match` est désormais **exhaustif** (plus de `default`), avec deux `withCount` dédiés (`documents_requis_non_signes`, `courriers_requis_non_signes`)
- [x] **`getStepBlockers()` (fiche) omettait deux prérequis de l'étape Édition** — pièces justificatives des associés/gérants et accord signé du client. La fiche annonçait « 0 condition requise » pendant que le serveur refusait
- [x] **`getStepBlockers()` appliquait l'ancienne règle d'Expédition** (« au moins une lettre de transmission envoyée »), remplacée côté serveur par le mécanisme granulaire `est_requis && !est_signe_cachete`. Elle bloquait donc des dossiers que le serveur acceptait, et laissait passer ceux qu'il refusait
- [x] `DossierStepService::verifierPrerequis()` — `default => null` remplacé par `EtapeDossier::Cloture => null` explicite : une étape ajoutée ne peut plus être franchissable sans contrôle, en silence
- [x] `Dossier::revisionValidee()` comparait `statut?->value === 'valide'` — passe au cas d'enum `StatutRevision::Valide` (même fragilité que le `'cloture'` des formalités)
- [x] Deux commentaires renvoyaient à `DossierStepService::verifierInitialisation()`, méthode supprimée avec l'étape `Initialisation`
- [x] `tests/Feature/AvancementEtapesTest.php` — 9 tests, dont un qui **traverse les 6 étapes** pour garantir qu'aucun `match` ne lève `UnhandledMatchError`, et deux qui vérifient que la liste et le serveur donnent la **même** réponse à l'Expédition (bouton désactivé ⟺ `assertSessionHasErrors`)

**Non retenu** : les autres comparaisons littérales de statut (`Courrier`, `Demande`, `Client`, compteurs de `FormaliteController`) portent sur des colonnes texte sans enum, ou sur des filtres de statistiques — aucune ne conditionne une transition d'étape.

### 🐛 Corrigé — L'étape Formalités était devenue un cul-de-sac (2026-08-03)

Signalé en usage réel : « je ne peux plus passer à l'étape suivante ». Le statut `StatutFormalite::Cloture` avait été supprimé (l'étape de clôture par formalité a été retirée du workflow — recevoir le retour de l'organisme *est* l'aboutissement de la démarche), mais `DossierStepService::verifierFormalites()` testait toujours `$f->statut?->value !== 'cloture'`. Plus aucune formalité ne pouvant porter ce statut, **toutes** étaient considérées comme bloquantes : le passage à l'Expédition était définitivement impossible, y compris sur un dossier entièrement traité — et aucune erreur ne le signalait, la comparaison à une chaîne littérale restant syntaxiquement valide.

Tout le reste de l'application avait bien été migré vers `retour_recu` (contrôleurs, modèle, frontend) : cette ligne était la seule oubliée.

- [x] **La règle vit désormais dans l'enum** : `StatutFormalite::estTerminee()`, écrit en `match` **exhaustif** plutôt qu'en comparaison de chaîne. Ajouter un statut provoque une erreur PHP tant qu'il n'a pas été classé — c'est précisément le filet qui manquait ([décision #35](#8-décisions-techniques))
- [x] `exigeCorrection()` distingue un **rejet** (action attendue du formaliste) d'une **attente de retour** (dépend de l'organisme). Les deux bloquent, mais le message d'erreur le dit maintenant séparément : « À corriger et redéposer : APIP. En attente de retour : Impôts. » — l'ancien message listait les organismes sans indiquer quoi faire
- [x] `StatutFormalite::valeursTerminees()` + scope `Formalite::nonTerminees()` — les comparaisons `'retour_recu'` codées en dur (5 dans le modèle, 2 en contrôleur) passent par la règle centralisée. `Formalite::estTerminee()` délègue à l'enum
- [x] `tests/Feature/AvancementFormalitesTest.php` — 7 tests : le cas devenu impossible (tout en retour reçu → avance), blocage par attente puis par rejet avec messages distincts, les deux causes annoncées ensemble, dossier sans formalité qui avance, exhaustivité de `estTerminee()`, cohérence du scope

### ✅ Complété — Brouillons de création de dossier (2026-08-03)

L'assistant de création est long (wizard 3 étapes, questionnaire de 20 à 40 champs, pièces justificatives) et ne survivait à rien : une interruption, une fermeture d'onglet, une information manquante à aller chercher — et tout était à ressaisir.

- [x] Table **`dossier_brouillons`** (`user_id`, `type_acte_id` nullable, `libelle`, `etat` json) — table dédiée et **pas** un `Dossier` en étape « brouillon » : la référence notariale `{PREFIXE}-{ANNÉE}-{XXXX}` est attribuée à la création, un brouillon abandonné y laisserait un trou définitif dans la numérotation ([décision #34](#8-décisions-techniques))
- [x] `App\Models\DossierBrouillon` — `repertoire()`, `piecesTeleversees()`, `fichierPourPiece()`, `supprimerAvecFichiers()`
- [x] `DossierBrouillonController::store()` (création **et** mise à jour) / `destroy()` — routes `POST /dossiers/brouillons` et `DELETE /dossiers/brouillons/{brouillon}`. `pourUtilisateur()` alimente la prop `brouillons` de l'assistant via `DossierController::create()`
- [x] **Un brouillon n'appartient qu'à son auteur** — même un administrateur reçoit un 403 : il n'y a rien à superviser dans un formulaire à moitié rempli
- [x] **Les pièces déjà sélectionnées sont conservées** (choix utilisateur) — téléversées dès l'enregistrement dans `storage/app/private/brouillons/{id}/`, sur le disque **`local`** et non `public` : une pièce d'identité n'a rien à faire derrière une URL tant qu'elle n'est pas rattachée à un dossier. À la finalisation, `fichierPourPiece()` les rejoue en `UploadedFile` pour passer par le même chemin que n'importe quel téléversement (`DocumentFichier::nouvelleVersion` — nommage, hash, versionnage identiques), puis le brouillon est supprimé avec son répertoire
- [x] `rangerPieces()` nettoie à chaque enregistrement : un fichier remplacé est supprimé, et tout fichier que l'état reçu ne mentionne plus (bouton « Retirer ») disparaît du disque
- [x] **`php artisan ayelema:brouillons-purger [--jours=60] [--supprimer]`** — contrepartie assumée du choix de conserver les fichiers. Dry-run par défaut (inventaire en tableau), même convention que `ayelema:ged-lister-orphelins` : sur des pièces d'identité, la suppression ne doit jamais être le comportement implicite
- [x] **Enregistrement explicite, pas d'auto-save** — bouton « Enregistrer le brouillon » dès qu'un type d'acte est choisi (avant, il n'y a rien qu'un clic ne referait), avec un discret « Brouillon enregistré à 14:32 ». Rien ne part à l'insu de l'utilisateur
- [x] Bandeau de reprise en tête de l'assistant listant les saisies inachevées (libellé, type d'acte, date, nombre de pièces conservées) — affiché **seulement** avant d'avoir commencé, pour ne jamais écraser une saisie en cours. Suppression avec confirmation qui annonce le nombre de pièces perdues
- [x] `resources/js/lib/brouillonDossier.js` — sérialisation **explicite** de l'état (`serialiserEtat`) plutôt qu'un ramassage automatique : un champ ajouté à l'assistant doit l'être sciemment, sinon on persisterait des états techniques (modales ouvertes, indicateur de soumission) qui n'ont aucun sens à la reprise. `etat` transite en JSON encodé, la requête étant un multipart qui transporte aussi les fichiers
- [x] `PieceStagedRow` — nouvel état « conservée au brouillon » (nom du fichier + Remplacer / Retirer, sans aperçu : le fichier est sur le disque privé et l'exposer pour un dossier qui n'existe pas encore serait excessif)
- [x] Reprise robuste : si le type d'acte a été désactivé entre-temps, l'assistant revient à l'étape 0 plutôt que d'ouvrir une étape 2 sans questionnaire
- [x] Le brouillon n'est supprimé qu'**après** succès de la création — un rollback le laisse intact pour que l'utilisateur puisse réessayer sans rien avoir perdu
- [x] `tests/Feature/DossierBrouillonTest.php` — 10 tests : création, mise à jour sans doublon, pièce sur le disque privé, remplacement qui supprime l'ancien fichier, retrait qui nettoie le disque, cloisonnement par auteur (403 pour un admin), suppression avec fichiers, état illisible refusé, purge en dry-run puis effective, et brouillons récents épargnés

### ✅ Complété — La fiche Client devient la source de vérité de l'identité (2026-08-03)

Signalé en usage réel : « la façon dont on sélectionne un client existant pour pré-remplir l'associé ou le gérant est mal faite, pas intuitive ». Diagnostic : sélectionner un client **recopiait** son identité dans les 18 champs texte de la section (`mapClientToPrefixedFields`). Trois conséquences — l'utilisateur créait la fiche en haut puis retrouvait le même formulaire complet en bas ; la copie divergeait dès la première correction (un n° de CNI corrigé sur la fiche ne remontait dans aucun dossier) ; et `RepeatableGroup` reconstruisait un **pseudo-client** depuis les champs de la ligne, faute d'avoir gardé le vrai.

**Écart de champs — l'hypothèse de départ était partiellement fausse.** Extraction de toutes les balises d'identité des modèles Word (`dictionnaire_balises.md`, `Analyse_et_Prompt_Generation_Modeles_Ayelema.md`) puis comparaison à la table `clients` : la fiche couvrait **déjà 100 %** des champs de l'associé unique, du gérant, du vendeur, de l'acquéreur et du débiteur. Un seul manquait : `representant_qualite`. Ce qui apparaissait « en plus » dans les sections est de la donnée **propre à l'acte** (`parts_chiffres`, `fonction` au CA, `qualite` du liquidateur) — la porter sur une fiche client serait une erreur de modélisation, la même personne pouvant détenir 100 parts ici et 5 ailleurs. **Le problème était le flux, pas le schéma.**

- [x] `clients.representant_qualite` — seul champ réellement manquant ([décision #33](#8-décisions-techniques))
- [x] `ClientController::update()` + `PATCH /clients/{client}` — aucune route de modification n'existait, ce qui rendait impossible le « si on veut le modifier, on le modifie de suite ». Règles factorisées avec `store()`
- [x] **`parties.donnees_prefixe` / `donnees_bloc` / `donnees_index`** — rend le lien Partie → questionnaire **auto-descriptif**. Voir l'obstacle et sa solution ci-dessous
- [x] `App\Services\ClientProjectionService` — `reprojeter(Dossier)` écrit l'identité des fiches liées dans `donnees` ; `reprojeterDossiersDuClient(Client)` répercute une correction sur tous les dossiers non clôturés + régénère leurs actes ; `regenererDocumentsNonVerrouilles()` extrait de `updateQuestionnaire()` (la boucle y était déjà, dupliquée)
- [x] **Ne touche jamais** une clé absente de `SUFFIXES_*` : les données propres à l'acte survivent à toute reprojection, y compris dans un bloc répétable (fusion dans l'item, jamais remplacement)
- [x] Déclencheurs : `creerDossier()` (**avant** la génération des documents, pour ne pas générer deux fois), `updateQuestionnaire()` (après resynchronisation des parties), `ClientController::update()`
- [x] Garde-fous : jamais un dossier `Cloture` (sa vérité est celle du jour de la clôture), jamais un document `est_signe_cachete`, et un `JournalActivite` par dossier touché — une modification déclenchée depuis le Répertoire ne doit pas être invisible pour l'équipe qui suit le dossier. Les dossiers réalignés sont aussi renvoyés au frontend et annoncés en toast
- [x] `resources/js/Components/ui/client-role-section.jsx` — la section **désigne** une fiche au lieu de resaisir son identité : sélecteur (puces des clients du dossier + répertoire + création), puis **carte de synthèse** en lecture (nom, pièce, résidence, contact, représentant) avec « Modifier la fiche » / « Changer » / détacher. Les 18 champs d'identité disparaissent de l'écran
- [x] **Échappatoire « Saisir sans fiche client »** — pour un tiers ponctuel qu'on ne verse pas au répertoire ; bascule explicite qui détache la fiche (garder les deux recréerait la double vérité)
- [x] **Fiche incomplète pour l'acte** — un champ d'identité obligatoire absent de la fiche est masqué **et** vide, donc invisiblement bloquant pour « Continuer ». Détecté en cours d'implémentation : la carte affiche désormais « Fiche incomplète — il manque : … » + « Compléter la fiche », plutôt que de laisser saisir la valeur à côté
- [x] `RepeatableGroup.jsx` — chaque ligne = référence client + données propres à l'acte ; l'objet client complet est conservé dans `item.client` (fin du pseudo-client reconstruit)
- [x] `Show.jsx` (ModalEditQuestionnaire) — **même composant** que la création, pour que les deux ne divergent pas ; `attachPartieIds()` réattache aussi l'objet `client` (sans lui, la ligne rouvrait en saisie libre)
- [x] `clientFields.js` — `estChampIdentite()` (règle de masquage partagée), `representant_qualite`, et le suffixe **`ville`** qui manquait au mapping alors que `${bq.ville}` est utilisé par les modèles
- [x] `tests/Feature/ClientProjectionTest.php` — 10 tests : projection scalaire, préfixe qui pilote la destination, **bloc répétable qui ne perd pas `parts_chiffres`**, personne morale, saisie libre préservée, dossier clôturé jamais reprojeté, répercussion multi-dossiers + journal, route `PATCH`, **test de dérive PHP↔JS**, et garde-fou « aucun champ propre à l'acte n'est projetable »
- [x] **Dossiers existants laissés tels quels** — pas de commande de reprise : leurs données en texte libre s'affichent via l'échappatoire, et une `Partie` sans `client_id` n'est jamais projetée

#### L'obstacle architectural, et sa solution

La synchronisation exige de recalculer `donnees` **côté serveur** (modifier une fiche depuis le Répertoire se passe hors du wizard). Or le serveur ne connaît ni le schéma des questionnaires ni la correspondance rôle → préfixe (`associe_unique` → `pp.`, `creancier` → `bq.`) : tout cela vit dans `resources/js/data/questionnaires.js`, en JavaScript.

Dupliquer ce schéma en PHP créait une dérive garantie. **Solution : rendre le lien auto-descriptif.** Le frontend connaît déjà le préfixe quand il construit le payload — il le transmet, et on le persiste sur la `Partie` (`donnees_prefixe`, `donnees_bloc`, `donnees_index`). Le serveur n'a alors plus besoin du schéma : la Partie lui dit exactement où écrire. La seule connaissance PHP restante est la liste finie des **suffixes qui proviennent du client**, verrouillée par un test de dérive qui compare `ClientProjectionService::suffixesProjetes()` aux `set('…')` de `clientFields.js`.

### ✅ Complété — Plafonnement des paiements au total facturé (2026-08-03)

**Règle métier** : la somme des paiements d'une facture ne peut **jamais** dépasser son total. Le comportement précédent autorisait explicitement le trop-perçu (le front n'avertissait qu'au-delà du double du total, avec un bouton « Confirmer malgré tout ») et le backend ne contrôlait rien du tout.

- [x] `Facture::totalPayeEnBase(?int $sauf)` — somme **relue en base**, volontairement distincte de `totalPaye()` qui peut servir une relation déjà chargée (parfait pour l'affichage, inacceptable pour contrôler un invariant)
- [x] `Facture::soldeDisponible(?int $saufPaiementId)` — montant maximum encore encaissable ; le paiement en cours d'édition libère son propre montant (le ramener de 1 000 000 à 800 000 sur une facture soldée doit passer)
- [x] `Facture::peutRecevoirPaiement()` / `estTropPercue()` — exposés dans `versArray()`, le front s'appuie sur la même règle que le serveur
- [x] `FactureController::assertMontantDansSolde()` — contrôle commun à `enregistrerPaiement()` et `updatePaiement()`, avec message chiffré (total facturé / déjà encaissé / maximum encaissable). Refuse aussi tout paiement sur une facture à 0 GNF
- [x] **Contrôle sous verrou de ligne** (`lockForUpdate` sur la facture, dans une transaction) — sans ça, deux encaissements concurrents valident chacun face au même solde et le dépassent à eux deux. Comparaison sur des montants arrondis à 2 décimales : un test strict sur des flottants rejetterait à tort le paiement qui solde exactement la facture
- [x] L'autre moitié de l'invariant **était déjà en place** : le total ne peut pas descendre sous les paiements reçus, car `assertLignesModifiables()` gèle les lignes et `FacturationService::genererFacture()` refuse la régénération dès qu'un paiement existe
- [x] Front `ModalEnregistrerPaiement.jsx` — plafond dur remplaçant l'avertissement mou : bouton désactivé, champ en erreur, message chiffré, raccourci « Solder (montant exact) », et cas distincts facture à 0 / facture soldée. `max` **non** posé sur `NumberField` (il rend un `type="text"`, l'attribut y serait un no-op trompeur) — l'erreur d'état et le bouton désactivé font le travail
- [x] Front `Dossiers/Show.jsx` — bouton « Enregistrer paiement » désactivé quand `peutRecevoirPaiement` est faux, avec un `title` expliquant lequel des deux cas s'applique
- [x] **Données antérieures** : un trop-perçu déjà en base n'est plus un état atteignable, donc c'est une anomalie — affiché en **rouge** (plus en vert « succès ») avec un bandeau explicite dans l'onglet Facturation et un badge « Trop-perçu » dans la liste globale. Volontairement **pas de migration corrective** : ce sont des écritures financières, la correction (modifier ou supprimer un paiement, possible tant qu'aucun reçu n'est émis) reste une décision humaine
- [x] `tests/Feature/PaiementPlafonneTest.php` — 11 tests : dépassement simple, cumul (500 000 valide seul mais refusé en cumul), montant soldant exactement, facture à 0, facture soldée, modification à la hausse/à la baisse, `soldeDisponible()` avec exclusion, libération de solde après suppression, facture déjà en trop-perçu, et non-régression du gel des lignes

### ✅ Complété — Fiabilisation des notifications (2026-08-03)

Signalé en usage réel : « certaines notifications ne passent pas parfois » et « ne vont pas vers les personnes concernées ». **Deux causes distinctes**, et cette section du devbook était elle-même périmée (elle annonçait 5 notifications `ShouldQueue` ; il y en avait 7, aucune queuée).

**A. Livraison — le « parfois »**

- [x] **Le temps réel ne dépend plus d'un worker.** `BroadcastNotificationCreated` est `ShouldBroadcast` (donc queué) : avec `QUEUE_CONNECTION=database` et aucun `queue:work` lancé, **aucun toast ni badge n'arrivait** — la notification était bien en base, visible seulement après rafraîchissement, d'où l'impression d'intermittence. `CanauxNotification::toBroadcast()` renvoie désormais un `BroadcastMessage` en `onConnection('sync')` ([décision #31](#8-décisions-techniques))
- [x] **Le scheduler est lancé en dev.** `composer dev` lançait server + queue + vite, **pas** `schedule:work` : les deux seules notifications planifiées (`ayelema:alerter-echeances`, `ayelema:alerter-formalites`) ne partaient donc **jamais** en local. Ajouté au `concurrently` (`--names=server,queue,scheduler,vite`), et `queue:listen --tries=1` passé à `--tries=3` (un échec Pusher/SMTP transitoire détruisait le job sans retry)
- [x] **Plus de perte à la navigation.** `AppLayout` n'est pas un layout Inertia persistant : il remonte à chaque visite, et `useRealtimeNotifications` appelait `Echo.leave()` au démontage puis re-souscrivait — les notifications émises pendant cette fenêtre étaient perdues (Pusher ne rejoue rien). Le hook ne retire plus que son handler (`stopListening`), la souscription survit à la navigation
- [x] **Une panne SMTP ne casse plus l'action métier.** `DemandeController::convertir()` appelait `notify()` sans `try/catch` après création du dossier → 500 alors que la conversion avait réussi. Tous les envois passent par `NotificationService::envoyer()` (try/catch + `report()` par destinataire), et l'ordre des canaux garantit que `database`/`broadcast` sont déjà passés quand `mail` échoue

**B. Destinataires — les « personnes concernées »**

- [x] `NotificationService` — point de routage unique : `destinataires()` cible le rôle réellement concerné via la colonne d'assignation du dossier, avec **repli sur le pool du rôle** (utilisateurs actifs le portant) + le notaire du dossier si le poste est vacant. `envoyer()` filtre les comptes désactivés, déduplique, et exclut l'acteur déclencheur
- [x] `Dossier::ayantsDroit()` exclut désormais les comptes désactivés (reste le bon périmètre pour les alertes transversales : échéance, formalité urgente)
- [x] Événement → destinataire après correction :

| Événement | Notification | Destinataire | État avant |
|---|---|---|---|
| Assignation (création / réassignation) | `DossierAssigne` | le nouvel assigné, hors acteur | ❌ aucune |
| Édition → Certification | `RevisionEnAttente` | certificateur (repli pool Reviseur) | les **4** assignés |
| Renvoi en correction | `DossierRenvoye` (+ motif) | rédacteur, hors acteur | ❌ aucune |
| Certification → Signature | `CertificationValidee` | notaire + rédacteur | ❌ aucune |
| Certification → Signature | `SignatureClientEnAttente` | notaire (repli pool Notaire) | notaire, **rien si `notaire_id` null** |
| Signature → Formalités | `FormalitesAFaire` | formaliste (repli pool + notaire) | ❌ aucune — trou le plus grave |
| Échéance < 72 h | `EcheanceDossier` | ayants droit actifs, anti-doublon 12 h | idem (mais scheduler absent) |
| Formalité urgente / dépassée | `FormaliteUrgente` | ayants droit actifs, anti-doublon 12 h | idem (mais scheduler absent) |
| Nouvelle demande d'intake | `NouvelleDemande` | pool Notaire + auteur du lien | idem |
| Demande convertie | `DemandeConvertie` | auteur de la demande | idem, sans `try/catch` |

- [x] `SignatureNotaireEnAttenteNotification` **supprimée** — code mort, jamais envoyée depuis la fusion en une étape `Signature` unique
- [x] `php artisan ayelema:notifications-diagnostic [--mail=adresse]` — répond à « pourquoi celle-là n'est pas passée ? » : transport (broadcaster, clés Pusher serveur **et** `VITE_PUSHER_APP_KEY` front, SMTP), jobs en attente/échoués, rappel scheduler, et destinataires à risque (comptes actifs sans email, `notifications_email=false`, **dossiers en cours assignés à un compte désactivé**)
- [x] `tests/Feature/NotificationRoutingTest.php` — 8 tests : ciblage du certificateur seul, repli pool quand `formaliste_id` est nul, exclusion des comptes désactivés, exclusion de l'acteur, canaux selon `notifications_email`/email vide, et `toBroadcast()` bien en `sync`
- [x] `UserFactory` renseigne explicitement `actif` et `notifications_email` — `create()` ne relit pas la ligne, l'instance retournée avait ces attributs à `null`, ce qui faisait diverger les tests du comportement réel
- [x] Front : 4 nouveaux `data.type` ajoutés à `TYPE_META` (`renvoi`, `formalites_a_faire`, `assignation`, `certification_validee`), sinon ils tombaient sur la cloche générique sans libellé

### ✅ Complété — Module Sécurité : politique de mot de passe + 2FA par email (OTP)

- [x] Politique de mot de passe forte centralisée : `Password::defaults()` dans `AppServiceProvider::boot()` (12 car. min., majuscule, minuscule, chiffre, caractère spécial) — fixée en dur, non paramétrable (voir [décision #26](#8-décisions-techniques))
- [x] `PasswordRequirements.jsx` — checklist live intégrée à Register, ResetPassword, Profil (changement mdp), création/édition utilisateur admin
- [x] `ParametresController::storeUtilisateur/updateUtilisateur` corrigés pour utiliser `Password::defaults()` (contournaient auparavant la politique avec un simple `min:8`)
- [x] 2FA OTP par email, durée paramétrable (`Setting` : `otp_enabled`, `otp_duration_minutes`) — connexion différée (`LoginRequest::authenticate()` ne connecte plus directement, voir [décision #23](#8-décisions-techniques))
- [x] `TwoFactorAuthenticationService` — génération/envoi/vérification/renvoi du code (6 chiffres, hash sha256, throttle), gestion des appareils de confiance
- [x] Appareil de confiance (30 jours) — table `user_trusted_devices`, cookie signé, case à cocher sur `VerifyOtp.jsx`, révocation depuis `Profile/Edit.jsx` (`TrustedDevicesForm.jsx`)
- [x] Section "Sécurité" dans `Parametres/Index.jsx` (`TabSecurite`) — toggle `otp_enabled`, durée en minutes, avertissement SMTP

### ✅ Complété — Module 9 : Répertoire clients

- [x] `RepertoireController` (index + autocomplete JSON)
- [x] `Repertoire/Index.jsx`
- [x] Route `/repertoire/autocomplete` pour auto-complétion formulaires

### ✅ Complété — Module 10 : Paramètres & Administration

- [x] `ParametresController` — index, utilisateurs CRUD, types d'actes
- [x] Pages Paramètres/Index, Utilisateurs, TypesActes
- [x] Routes admin protégées par `middleware('role:administrateur')`

### ✅ Complété — Module 6 : Barèmes & facturation automatique

- [x] Table `baremes` (taux % ou montant fixe, par type d'acte, par organisme) + `BaremeSeeder`
- [x] `ParametresController::baremes/storeBareme/updateBareme/destroyBareme` — CRUD complet
- [x] `Parametres/Baremes.jsx` — accordéon par type d'acte, légende organismes (APIP/Impôts/Conservation/CNSS/Notaire/Autre), toggle actif
- [x] `FacturationService::genererFacture()` / `simuler()` — calcule les lignes de facture depuis les barèmes actifs du type d'acte, déduit l'assiette depuis le questionnaire ou `dossier->valeur`
- [x] Tables `factures` / `lignes_factures` + modèles `Facture` / `LigneFacture`
- [x] **Export de la facture au format .docx fidèle au modèle du cabinet** (2026-07-28) — `FactureGeneratorService` remplace l'ancien `FacturePdfService` (dompdf/HTML, abandonné) : rendu depuis `storage/app/private/modeles/facture-notariale.docx` (le fichier fourni par le cabinet, balisé `${fac.*}`/`${ligne.*}`), table des lignes clonée dynamiquement (`TemplateProcessor::cloneRow`), logo d'en-tête **et** filigrane remplacés à la volée par le logo configuré (`Setting::logo_path`, Paramètres > Apparence) — le filigrane délavé est un effet VML (`gain`/`blacklevel`) du modèle, pas un traitement d'image de notre côté, donc n'importe quel logo uploadé en hérite automatiquement. Logo toujours ré-encodé en PNG (GD) avant injection, quel que soit son format source (jpg/png) — les deux emplacements image du docx sont typés `.png` dans `[Content_Types].xml`, un contenu jpg injecté tel quel serait rejeté par Word. Logo SVG non géré (pas de rasterisation) : filigrane/logo du modèle reste alors inchangé. Rendu **à la demande** (jamais persisté en GED) : les lignes restent modifiables tant qu'aucun paiement n'existe, un fichier stocké deviendrait silencieusement obsolète
- [ ] ⚠️ Non unifié : `Formalite::calculerMontant()` reste un mécanisme séparé (base×taux en dur sur la formalité), indépendant des `Bareme` — à terme, harmoniser les deux

### ✅ Complété — Module Courriers

- [x] Table `courriers` (référence auto `COU-{année}-{0000}`, statut brouillon/envoyé)
- [x] `CourrierController` — index (recherche, filtres type/statut, stats), store, update (gère `envoye_at`), destroy
- [x] `Courriers/Index.jsx` — KPIs, répartition par type cliquable, recherche debounced, modal `ModalCourrier`

### ✅ Complété — Données réutilisables (Client / Société / Bien / Banque)

- [x] Tables `clients`, `societes`, `biens_immobiliers`, `banques`, `settings` (migrations 2026-06-29 et 2026-07-03)
- [x] Modèles `Client` (physique|morale), `Societe`, `BienImmobilier` (champs vente **et** bail dans la même table), `Banque`, `Setting` (clé/valeur, config office : nom, couleurs, logo)
- [x] `Partie.client_id` (nullable) → `Client` : les deux modèles coexistent, `Client` n'est pas encore le pivot central
- [ ] ⚠️ Aucun CRUD HTTP dédié pour `biens_immobiliers` et `banques` — pas de controller/route ; alimentation indirecte via questionnaire uniquement, aucun seeder. **Deux exceptions** : `clients` depuis le 2026-08-03 (`ClientController` : `autocomplete`, `store`, `update`) et `societes` depuis le 2026-08-11 (`SocieteController` : `autocomplete`, `show`, `store`, `update`, + `ayelema:societes-backfill`). Ni l'un ni l'autre n'expose de `destroy` — supprimer une fiche référencée par des dossiers casserait leur projection

### ✅ Complété — Module GED unifié (2026-07-24)

Avant cette date, la gestion documentaire était éclatée en 3 sous-systèmes indépendants : `documents` (actes de dossier), `formalite_pieces` (pièces des démarches), et les colonnes `Partie.photo_chemin`/`pieces` (jamais câblées). Chaque régénération d'acte écrasait aussi silencieusement le fichier précédent, laissant des dizaines de fichiers orphelins non nettoyés dans `storage/app/public/dossiers/*` (117 recensés lors de la migration — voir `ayelema:ged-lister-orphelins`).

- [x] Tables `document_fichiers` (polymorphe `documentable_type`/`documentable_id` → `Dossier`, `Formalite`, `Partie`) + `document_versions` (historique complet, jamais écrasé) — remplacent `documents` et `formalite_pieces`, **supprimées** après backfill vérifié
- [x] Modèles `App\Models\DocumentFichier` (`nouvelleVersion()`, `restaurerVersion()`, `supprimerAvecFichiers()`, `dossierGouvernant()`) et `App\Models\DocumentVersion`
- [x] Convention de stockage unifiée : `documents/{reference}/` (actes), `formalites/{reference}/` (pièces), `parties/{reference}/` (pièces/photo de partie) — `ActesGeneratorService` ne pointe plus vers l'ancien `dossiers/{id}/`
- [x] `DocumentController` étendu : `GET /documents/{document}/versions` (JSON historique), `POST /documents/versions/{version}/restaurer` (restauration = nouvelle version, jamais de rollback destructif), `GET /documents/versions/{version}/telecharger`
- [x] `DocumentsTab` (`Dossiers/Show.jsx`) : bouton « Historique » par document, dialog listant toutes les versions avec téléchargement/restauration individuels
- [x] `FormaliteController`/`FormaliteGenerationService` réécrits sur `DocumentFichier` — **shape JSON frontend inchangée** (`label`, `est_fourni`, `aUnFichier`…) pour ne pas casser `PieceGedRow.jsx`/`ModalDepotFormalite`/`ModalRetourFormalite`
- [x] `PartieController::uploaderPhoto/uploaderPiece` — upload photo + pièces justificatives (CNI…) par partie, avec avatar cliquable et liste de pièces inline dans l'onglet Informations
- [x] Colonnes mortes `Partie.photo_chemin`/`Partie.pieces` (JSON) supprimées — `pieces` entrait d'ailleurs en collision avec la nouvelle relation Eloquent `Partie::pieces()` (voir [décision #30](#8-décisions-techniques))
- [x] `php artisan ayelema:ged-lister-orphelins [--supprimer]` — liste (dry-run par défaut) les fichiers hérités de `storage/app/public/dossiers/*` non référencés dans `document_versions`. **117 fichiers orphelins recensés, jamais supprimés automatiquement** — à traiter manuellement via `--supprimer` quand souhaité
- [ ] ⚠️ `ModeleActe` (bibliothèque de modèles) reste **hors** de cette unification — gabarit, pas document de dossier ; sa propre gestion de versions reste un item de backlog séparé (Module 4)
- [x] `GedController` + page `Ged/Index.jsx` (route `/ged`, menu sidebar « GED ») — vue transversale de tous les `DocumentFichier` visibles par l'utilisateur, **groupée par dossier** (accordéon repliable/dépliable, premier groupe ouvert par défaut) plutôt qu'en liste chronologique plate — corrigé sur retour utilisateur (« pas organisé, pas intuitif »), le regroupement par dossier reflète le modèle mental du reste de l'appli. Pagination au niveau dossier (15/page), filtres type/catégorie, aperçu/téléchargement par document. Ajoutée après coup : le plan initial ne couvrait que l'unification par dossier (`DocumentsTab`), sans vue centrale

### 🌍 Francisation de l'interface (2026-08-04)

Signalé sur l'écran de connexion : « The email field is required. » **L'application n'avait aucun dossier `lang/` et tournait en locale `en`** — toutes les erreurs de validation, les messages d'authentification et les e-mails automatiques sortaient en anglais.

- [x] `APP_LOCALE` et `APP_FALLBACK_LOCALE` passés à `fr` dans `.env`, `.env.example` **et** les valeurs par défaut de `config/app.php` (pour qu'un déploiement sans `.env` complet reste français) ; `APP_FAKER_LOCALE=fr_FR`. Le fallback en `fr` évite une interface mi-française mi-anglaise si une clé manque
- [x] **`lang/fr/validation.php`** — traduction complète, avec un tableau **`attributes`** recensant les ~110 champs réellement validés par l'application. C'est lui qui fait la différence entre « Le champ prenom_nom est obligatoire » et « Le champ nom et prénoms est obligatoire ». Tout champ ajouté à un `FormRequest` devrait y être déclaré
- [x] Messages `custom` là où la règle générique se lit mal : `required_if:type,physique` donnait « obligatoire quand type vaut physique », remplacé par « Le nom et les prénoms sont obligatoires pour une personne physique »
- [x] **`lang/fr/auth.php`** — `failed` reste volontairement vague (ni « e-mail inconnu », ni « mot de passe incorrect ») : distinguer les deux permettrait de savoir quelles adresses ont un compte dans l'office. Même prudence sur `passwords.user`
- [x] `lang/fr/passwords.php`, `lang/fr/pagination.php`, et **`lang/fr.json`** pour les clés du gabarit d'e-mail Laravel (`Hello!`, `Regards,`) et des notifications intégrées (réinitialisation de mot de passe, vérification d'adresse)
- [x] **Carbon suit automatiquement la locale** : `diffForHumans()` rend « dans 2 jours » — ce qui francise du même coup `EcheanceDossierNotification`, qui l'utilise dans son message
- [x] **Pages d'erreur** (`resources/views/errors/`) — 403, 404, 419, 429, 500, 503 sur un gabarit commun aux couleurs de l'office. Elles affichaient le libellé HTTP anglais (« Not Found », « Page Expired »). Les explications sont contextualisées : le 403 mentionne qu'un dossier clôturé n'accepte plus de modification, le 419 rappelle l'enregistrement en brouillon
- [x] `tests/Feature/LocalisationTest.php` — 6 tests : locale et fallback, messages de l'écran de connexion, **non-divulgation de l'existence d'un compte**, libellés métier plutôt que noms de colonnes, règles de mot de passe traduites, chargement effectif des fichiers de langue
- [x] Vérifié : le frontend React était déjà entièrement rédigé en français, aucune chaîne à reprendre

### ✨ Statut du dossier plus visible et Clôture en dernière position (2026-08-04)

- [x] **Clôture est désormais le dernier onglet**, après Facturation : c'est la dernière étape du workflow, la facturation étant transversale et n'ayant pas à la suivre
- [x] **Bug corrigé au passage** : le compteur du badge Clôture lisait encore `est_requis && !est_signe_cachete`, colonne vidée lors du passage à l'inventaire dérivé — il affichait donc toujours 0. Il montre maintenant les pièces restant à vérifier (`clotureProgression`), et un ✓ vert quand tout l'inventaire est contrôlé
- [x] **Statut nettement plus apparent** : dans l'en-tête collant, le petit badge pâle devient une étiquette lisible avec pastille de couleur et **position dans le workflow** (« Formalités 4 / 6 »). Ajouté aussi dans la carte d'en-tête du dossier — l'en-tête collant ne s'affiche que pour qui peut faire avancer le dossier, or tout le monde doit savoir où il en est
- [x] **L'aperçu d'un document s'ouvre sous la ligne concernée, partout.** La fiche dossier avait un **panneau d'aperçu unique en pied de page** alimenté par quatre onglets (actes, lettres de transmission, reçus, documents de certification) : on cliquait sur « Aperçu » d'une ligne et le document s'ouvrait très loin en bas, sans qu'on sache plus à quelle ligne il correspondait. Motif factorisé dans `resources/js/Components/documents/ApercuSousLigne.jsx` — un hook `useApercuEnLigne()` (une seule ligne ouverte à la fois) et un panneau animé à placer après la ligne. Dans les tableaux (actes, paiements), l'aperçu occupe sa propre ligne en `colSpan`. Le panneau de pied de page et toute sa machinerie (`previewDoc`, `openPreview`, `previewRef`, deux `useEffect` dont un défilement automatique devenu inutile) sont supprimés. `PieceGedRow` (pièces de formalités), `AccordClientCard` et `ClotureTab` appliquaient déjà ce motif ; la GED transversale garde volontairement sa modale plein écran, c'est une vue de consultation sans ligne de référence à conserver
- [x] **Corrigé : « Tout vérifier » renvoyait sur un autre onglet.** Les actions de l'onglet Clôture utilisaient `preserveState: false`, ce qui fait remonter la page par Inertia — or `activeTab` s'initialise sur l'étape courante du dossier, donc on atterrissait sur Formalités. Passé à `preserveState: true` (les props sont rafraîchies dans les deux cas, seul l'état local diffère) ; le bug affectait aussi la coche d'une pièce isolée. Vérifié qu'aucun autre `preserveState: false` ne subsiste dans la fiche
- [x] **L'onglet actif est désormais porté par l'URL** (`?tab=…`, déjà lue à l'initialisation) : un remontage du composant, quelle qu'en soit la cause, retrouve l'onglet consulté, et un lien vers un onglet précis devient partageable. `replaceState` et non `pushState`, pour ne pas remplir l'historique de navigation
- [x] **Bouton d'avancement explicite** : il n'affichait que le nom de l'étape cible, encadré d'une icône flèche **et** d'un « → » en texte (« → Expédition → ») — rien n'indiquait qu'il faisait avancer le dossier, et la double flèche brouillait la lecture. Une étiquette « Étape suivante » (ou « Dernière étape » avant la clôture) surmonte désormais la destination, l'infobulle dit l'action en clair, et l'état de chargement passe de « En cours… » à « Passage en cours… » avec une icône animée. Le bouton de la liste des dossiers disait déjà « Avancer » avec une infobulle explicite : cohérence conservée

### ⚖️ Intégré — Règles de gestion notariales (2026-08-05)

Source : `Regles_Gestion_Plateforme_Notariale.docx`, CR de réunion de juillet 2026. Onze jeux de règles — contraintes **légales** guinéennes et tarifs officiels, pas des préférences d'interface. Aucune n'était appliquée : `soc.forme` n'était qu'une liste de sept choix sans conséquence.

**Où chaque règle est appliquée** — ce tableau est la référence pour vérifier plus tard qu'aucune n'a été perdue :

| Règle | Appliquée par |
|---|---|
| 1. Capital minimum (SA ≥ 140 M GNF) | `FormeSociete::capitalMinimum()`, montant dans `Setting` (`capital_minimum_sa`) → `ReglesSocieteService::verifierCapitalMinimum()` |
| 2. Formes à associé unique (SASU, SARLU, SAU) | `FormeSociete::admetAssocieUnique()` → `verifierNombreAssocies()`, **dans les deux sens** (une SARL à un seul associé bloque aussi) |
| 3. Capitaux / personnes | `NatureSociete` + `FormeSociete::nature()`, avec le régime de responsabilité |
| 4. Dénomination unique | `verifierDenominationUnique()`, comparaison normalisée sur les questionnaires |
| 4. Nom de famille en MAJUSCULE | `clients.nom_famille` + `prenoms`, balise `${pp.nom_famille}` (capitales) et `${pp.identite_notariale}` |
| 5. Commissaire aux comptes (SA) | `FormeSociete::exigeCommissaireAuxComptes()` → `verifierCommissaireAuxComptes()` |
| 6. PV d'AG si associé personne morale | `Partie::PIECES_REQUISES['associe_morale']['pv_ag']` |
| 7. Mineurs et incapables | `verifierCapaciteJuridique()` + `NatureSociete::exigeMajoritePourEtreAssocie()` |
| 8. Impact Statuts / RCCM | `TypeModificationStatutaire::impacteStatuts()` / `impacteRccm()` — **restitué** dans l'assistant et l'onglet Informations depuis le 2026-08-11 |
| 9. Documents par modification | `TypeModificationStatutaire::documentsRequis()`, et `documentsRequisPour(array)` pour l'union multi-types → **filtre effectivement** la génération (`ActesGeneratorService::typesDocumentsRetenus()`) |
| 10-11. Tarifs DGI et greffe | `ReglesGestionBaremeSeeder` → table `baremes`, **conditionnés** par `condition_modification` + `Bareme::estApplicableA()` depuis le 2026-08-11 (ils s'appliquaient auparavant à tous les types de société) |
| 1 (bis). Capital minimum en **réduction** | `ReglesSocieteService::verifierCapital()` — réduire une SA sous 140 M GNF est refusé ; le garde-fou ne couvrait que la constitution |
| 8 (bis). Modifications mutuellement exclusives | `TypeModificationStatutaire::incompatiblesAvec()` / `conflits()` → grisage à la saisie (`lib/exclusionsChoix.js`), blocage à l'avancement (`verifierIncompatibilites()`), détection de collision au registre (`SocieteMutationService::fusionnerChangements()`) |

**Application bloquante** (décision validée) : `ReglesSocieteService::anomalies()` est branché dans `DossierStepService::erreursDeConstitution()`, au même titre que l'accord client. Les anomalies remontent donc automatiquement dans le panneau des conditions requises et dans `getStepBlockers()`.

- [x] `App\Enums\FormeSociete` — 9 formes, dont **`SCS` et `SAU` qui manquaient** à la liste des sept choix existants. `match` exhaustifs : ajouter une forme sans décider de sa nature ni de ses obligations fait échouer PHP
- [x] **Montants paramétrables, règles structurelles en dur** : le capital minimum vit dans `Setting` (la loi le changera), mais « une SARLU admet un associé unique » est du code — cela ne change pas sans réécrire le droit des sociétés
- [x] `App\Enums\TypeModificationStatutaire` — 6 types, vérifiés un à un contre les tableaux 8 et 9 du document. Le questionnaire `SOC-MOD` remplace son champ texte libre par un choix structuré, plus `modif.valeur_parts_cedees` (assiette du droit de 2 %)
- [x] `ReglesGestionBaremeSeeder`, idempotent et **séparé de `BaremeSeeder`** (données de démonstration) : ces montants ont une source datée. L'ancienne ligne « Dépôt statuts au greffe : 100 000 GNF » est **désactivée** au profit de « Enregistrement au Tribunal de Commerce : 180 000 GNF » (décision validée) — désactivée et non supprimée, pour que les factures qui la référencent gardent une trace lisible
- [x] Séparation `clients.prenom_nom` → `nom_famille` + `prenoms`. **`prenom_nom` survit en accessor ET mutateur** : les 63 modèles Word non normalisés le référencent, et l'intake public l'écrit encore — le mutateur découpe plutôt que de rejeter. `$appends` le remet dans les réponses JSON. Découpe automatique journalisée (4 clients convertis, tous relus)
- [x] Les règles sont **restituées dans l'assistant** au choix du type d'acte (nature, responsabilité, capital minimum, associé unique, commissaire) : elles doivent guider la saisie, pas se découvrir au moment du blocage
- [x] `tests/Feature/ReglesSocieteTest.php` — 25 tests, **un par règle et nommé d'après elle**

**Deux constats qui ont changé la conception**

1. **`soc.forme` n'existe que dans un seul questionnaire de société** — les 10 dossiers réels en sont tous dépourvus. La première version bloquait donc les dix sur « la forme juridique doit être renseignée ». La forme est en réalité **dans le type d'acte** (`SOC-SARLU` → SARLU) : `FormeSociete::depuisCodeTypeActe()` est la source prioritaire, le questionnaire un simple repli. Vérifié : les 10 dossiers réels sont conformes, aucun blocage rétroactif.
2. **Le PV d'assemblée était facultatif de fait** : la pièce s'appelait « Déclaration RCCM **ou** PV de délibération », l'alternative annulant l'obligation de la règle 6. Scindée en deux pièces distinctes.

> ⚠️ **Le gras de la règle 4 n'est pas programmable.** PhpWord `setValue()` hérite du formatage du placeholder : mettre `${pp.nom_famille}` en gras est une consigne de **mise en page des modèles Word**, qu'aucun code ne peut imposer. Seule la mise en capitales est traitée côté données.

> ⚠️ **`SOC-DIS` (dissolution) n'est pas soumis aux règles de constitution** — bloquer une dissolution au motif d'un capital minimum serait absurde. `depuisCodeTypeActe()` retourne `null` pour les types de société qui ne constituent pas une forme précise.

### 🔄 Réintroduit — L'étape Initialisation, avant l'édition des actes (2026-08-04)

Signalé en usage : « lorsque je crée un dossier tu dois m'envoyer à l'étape initialisation pour le dépôt de l'accord et l'ajout des pièces manquantes ». Vérification : **ce n'était pas le comportement**, sur trois points.

1. **Pas d'étape Initialisation** — `creerDossier()` déposait le dossier directement en `Édition`.
2. **L'Édition mélangeait deux métiers** — `verifierEdition()` exigeait d'un bloc : objet, notaire, certificateur, pièces des personnes, accord client **et** au moins un acte. Les cinq premières conditions relèvent de la constitution du dossier.
3. **On n'atterrissait pas sur les informations** — `ETAPE_TAB['edition'] = 'documents'`, donc arrivée sur *Actes & documents*. Un `?focus=pieces` faisait défiler jusqu'aux pièces, mais depuis le mauvais onglet : un pansement sur l'absence d'étape dédiée.

S'ajoutait une incohérence de fond : les actes étaient **générés pendant la création**, donc sur un questionnaire que le client n'avait pas encore validé par son accord signé — à régénérer à la moindre correction.

- [x] `EtapeDossier` — `Initialisation` en tête, `ordre()` décalé (0…6), `label()`/`suivante()`/`precedente()`/`ordered()` mis à jour. **Aucune migration de schéma** : la colonne `dossiers.etape` portait déjà `default('initialisation')` depuis sa migration d'origine, vestige devenu utile
- [x] `DossierStepService` — `verifierInitialisation()` (les cinq contrôles de constitution) et `verifierEdition()` (au moins un acte). Les deux partagent `erreursDeConstitution()` pour ne pas diverger
- [x] **Génération des actes déplacée** à l'entrée en Édition — `ActesGeneratorService::genererActesDepuisModeles()`, extrait de `creerDossier()`. **N'écrase jamais un acte déjà présent** (correspondance par `nom`) : un renvoi en correction repasse par cette étape, et les corrections manuelles doivent survivre
- [x] `creerDossier()` dépose en `Initialisation` ; `store()` redirige vers `?tab=informations` (+ `focus=pieces`), avec un message qui dit quoi faire : « déposez l'accord du client et complétez les pièces pour passer à l'édition des actes »
- [x] `DossierPolicy` — `modifierQuestionnaire()` passe d'`Édition` à **`Initialisation`** (donc aussi `PartieController::store/destroy` et le dépôt de l'accord, déjà câblés dessus) ; `delete()` autorise les deux premières étapes ; `update()` traite le nouveau cas. `genererDocuments()` reste sur Édition + Certification
- [x] **Double contrôle assumé** : `verifierEdition()` rejoue les contrôles de constitution. Les dossiers créés avant ce changement sont déjà en Édition ou au-delà et n'ont jamais franchi d'Initialisation — sans ce garde-fou, l'un d'eux filerait en Certification sans accord client. **Vérifié sur la base réelle** : les 3 dossiers en Édition sans accord sont bien bloqués, avec le détail de ce qui manque. À retirer quand plus aucun dossier antérieur ne sera en cours
- [x] Front — `ETAPE_META.initialisation` + `'initialisation'` en tête de `ETAPE_ORDER` (ce fichier pilote le stepper de la fiche **et** de la liste) ; `ETAPE_TAB.initialisation = 'informations'` ; `getStepBlockers()` partage le cas entre `initialisation` et `edition`, en n'exigeant les actes qu'à partir de l'Édition ; pastille « étape en cours » sur l'onglet Informations
- [x] 4 tests d'avancement (naissance en Initialisation + redirection, blocage sans accord, avancement **et génération des actes à ce moment-là**, non-écrasement d'un acte corrigé) ; 3 tests de restriction réécrits (le questionnaire est désormais refusé en Édition, l'inverse d'avant)
- [x] Vérifié sur la base réelle : 7 étapes dans le stepper, les 15 dossiers existants inchangés et leurs fiches en 200

### 🐛 Corrigé — 500 sur la fiche dossier : statut de formalité orphelin (2026-08-04)

Détecté en testant les pages sur la **base réelle**, après l'audit des restrictions : `GET /dossiers/{ref}` renvoyait un **500** sur 4 dossiers — `ValueError: "cloture" is not a valid backing value for enum App\Enums\StatutFormalite`, levé par le cast Eloquent.

Cause : le cas `Cloture` a été retiré de `StatutFormalite` (l'étape de clôture par formalité ayant été supprimée) **sans migration des lignes existantes**. 14 formalités sur 28 portaient encore cette valeur.

- [x] Migration `migrer_statut_formalite_cloture_vers_retour_recu` — bascule vers `retour_recu`, l'état terminal actuel : une formalité anciennement « clôturée » était bien une démarche achevée. Irréversible (restaurer la valeur recasserait le cast, et rien ne distingue les deux cas)
- [x] Vérifié après passage : plus aucun statut orphelin, et **les fiches des 5 dossiers répondent toutes en 200**

> ⚠️ **Leçon à retenir** : retirer un cas d'enum casté par Eloquent exige *toujours* de migrer les lignes existantes. Le correctif du 2026-08-03 sur `StatutFormalite::estTerminee()` avait rendu le **code** cohérent (`match` exhaustif) mais ne pouvait rien contre des **données** portant la valeur disparue. Aucun test sur base neuve ne pouvait détecter ceci — seul un passage sur la base réelle l'a révélé.

### 🔒 Audit des restrictions d'étape — toutes les étapes (2026-08-04)

Après le correctif de la clôture, audit demandé sur les cinq autres étapes. **Le défaut était systémique** : plusieurs abilities encodaient *qui* peut agir, mais pas *quand*.

| Constat | Correctif |
|---|---|
| 🔴 **`updateQuestionnaire` n'était gardé que par `update`** — donc le questionnaire restait modifiable en Signature, Formalités et Expédition, **et l'action régénère les actes**. Une certification validée pouvait porter sur un contenu réécrit après coup ; seuls les documents déjà `est_signe_cachete` y échappaient | Nouvelle ability **`modifierQuestionnaire`**, Édition seule. Pour corriger après certification : le renvoi en correction, qui existe déjà et laisse une trace |
| 🔴 **`date_signature_client`/`notaire` étaient dans `UpdateDossierRequest`** en `sometimes\|nullable`, sans contrôle d'étape : renseignables dès l'Édition (avant toute certification) et **effaçables** par le formaliste une fois le dossier en Formalités | Sortis de la requête générique. Nouvelle ability **`enregistrerSignatures`** (étape Signature) + action et route dédiées `PATCH /dossiers/{ref}/signatures`, tracées au journal (`type = signature`) |
| 🟠 **Le raccourci `hasRole(Administrateur)` précédait le contrôle d'étape** dans `update`, `delete` et `genererDocuments`, alors qu'il venait après dans `gererFormalites`, `cloturerDocuments` et `genererCourriers` | Ordre uniformisé **là où un contrôle d'étape existe** : un administrateur ne peut plus supprimer un dossier hors Édition ni régénérer un acte à l'étape Signature ([décision #38](#8-décisions-techniques)) |
| 🟠 **`ClientController::store`/`update`/`autocomplete` n'avaient aucune autorisation**, et aucune `ClientPolicy` n'existait — or `update` reprojette le questionnaire et **régénère les actes** de tous les dossiers liés non clôturés ([décision #33](#8-décisions-techniques)) | Nouvelle **`ClientPolicy`** : création/modification réservées aux rôles pouvant ouvrir un dossier (Clerc, Notaire, Administrateur) ; `viewAny` ouvert pour l'autocomplétion. Restriction par **rôle** et non par dossier : un client peut être lié aux dossiers de plusieurs clercs |
| 🟠 **`CourrierController::index` ne filtrait pas par `Dossier::visiblePar()`** et n'appelait aucun `authorize` : tout utilisateur connecté voyait les courriers de tous les dossiers | `authorize('viewAny')` + filtre par assignation, comme `FormaliteController::index` et `GedController::index`. **Les compteurs de tête portent le même filtre** — afficher « 47 courriers » au-dessus d'une liste qui en montre 3 était un bug d'affichage autant qu'une fuite |
| 🟡 **`PartieController::store`/`destroy` passaient par `update`** : on pouvait ajouter ou retirer une partie après que la certification a validé les personnes à l'acte | Passés à `modifierQuestionnaire`, cohérent avec `updateQuestionnaire()` qui gère aussi la liste des parties |

**Point délicat : ne pas uniformiser mécaniquement.** `update` couvre les informations générales (objet, valeur, échéance, assignations), légitimes à **toutes** les étapes ouvertes — son raccourci administrateur reste donc avant le `match`, qui ne porte que sur l'assignation par étape. Seul le gel d'un dossier clôturé s'y applique. Un test de non-régression le verrouille (`test_update_reste_autorise_a_toutes_les_etapes_ouvertes`).

**Nettoyage au passage** : `UpdateDossierRequest` validait `donnees` (le questionnaire), alors que celui-ci vit dans la table `questionnaires` et n'est pas dans `Dossier::$fillable` — ces règles n'écrivaient rien et laissaient croire que cette route pouvait modifier le questionnaire.

**Front** : les boutons « Modifier le questionnaire », « Ajouter une personne », « Retirer cette personne », le dépôt de l'accord client et les actions de signature se règlent sur les nouvelles abilities. Quand l'étape est dépassée, **la raison est affichée** (« Questionnaire modifiable uniquement à l'étape Édition — passez par le renvoi en correction ») plutôt que le bouton silencieusement absent.

**Ce qui était déjà correct et a servi de modèle** : `RevisionPolicy` (étape avant tout, plus des préconditions d'état dans `valider()`/`renvoyer()`), `gererFormalites`, `genererCourriers`, et l'homogénéité de `FormaliteController`/`FactureController`. Vérifié aussi : aucune route ne permet de rouvrir un dossier clôturé.

- [x] `tests/Feature/RestrictionsEtapesTest.php` — 16 tests, un par constat, dont un **garde-fou de structure** : chaque étape de `EtapeDossier` doit être refusée par au moins une ability mutante, sinon une étape ajoutée plus tard autoriserait tout
- [x] `ClientCreationTest` corrigé : il créait un `User` sans aucun rôle, ce que `ClientPolicy` refuse désormais à juste titre

### 🔄 Refondu — La clôture passe de la configuration à l'inventaire (2026-08-04)

**L'approche « documents obligatoires configurés par type d'acte » est abandonnée.** Elle reposait sur une prémisse devenue fausse : `modeles_actes.obligatoire_cloture` (+ son équivalent courriers) déclarait à l'avance, modèle par modèle, ce qui devrait exister à la clôture. Or le workflow **produit lui-même** toutes les pièces du dossier — pièces d'identité à la création, actes à l'édition, accord client, justificatifs de formalités, courriers à l'expédition, reçus de paiement. Cette déclaration dupliquait une vérité déjà connue, et pouvait la contredire : un modèle coché obligatoire mais jamais généré bloquait la clôture sans recours, un modèle décoché laissait passer un acte manquant.

Constat concret : sur `SOC-2026-0009`, l'ancien onglet Clôture affichait **5 éléments** (les seuls documents marqués `est_requis`) alors que le dossier compte **19 pièces** réparties sur 5 rubriques.

**Supprimé**

- [x] Colonnes `obligatoire_cloture` sur `modeles_actes` et `modeles_courriers` (migration `drop_obligatoire_cloture_from_modeles_tables`)
- [x] Page `Paramètres > Clôture` (`Parametres/Cloture.jsx`), `ParametresController::cloture()` + `bulkObligatoireCloture()`, routes `GET /parametres/cloture` et `POST /parametres/cloture/bulk`, onglet et KPI « Docs obligatoires »
- [x] `ModeleActe::synchroniserDocumentsRequis()` et `ModeleCourrier::synchroniserCourriersRequis()` — la synchronisation rétroactive de `est_requis` (ajoutée le 2026-07-28) n'a plus d'objet
- [x] Case « Obligatoire à la clôture » et badge cadenas dans `Modeles/Index.jsx`, règles de validation des deux contrôleurs, et l'alimentation de `est_requis` aux trois sites de création
- [x] Migration `clear_est_requis_on_actes_et_courriers` — ⚠️ `est_requis` est **surchargé** : notion morte sur les actes et courriers, mais **bien vivante** sur les pièces de `Partie` et de `Formalite` où elle signifie « pièce à fournir » (lue par `piecesChecklist()`). La migration filtre sur `documentable_type` ; vérifié en base après passage : 0 sur les actes et courriers, 3/3 et 54/54 préservés sur parties et formalités

**Ajouté**

- [x] `App\Enums\RubriqueCloture` — six rubriques ordonnées (Actes, Accord du client, Pièces des parties, Pièces des formalités, Courriers, Facturation). `pourDocument()` classe par `match` **exhaustif** sur `documentable_type` et non sur `categorie` : la catégorie d'un acte vient de `ModeleActe.type_document`, saisi librement (`dnsv`, `rccm`, `attestation`…), alors que le type de rattachement est un ensemble fermé de trois classes — un quatrième documentable lèvera une erreur tant qu'il n'aura pas été classé
- [x] `App\Services\InventaireClotureService` — assemble l'inventaire depuis **trois modèles de stockage** (`DocumentFichier` polymorphe, `Courrier`, `Recu`, ces deux dernières ayant leur propre table, héritage antérieur au module GED unifié) et normalise tout à une forme unique. `pour()`, `piecesNonVerifiees()`, `estCompletementVerifie()`, `progression()`. **Source unique de l'onglet Clôture et de la GED**, pour qu'ils ne puissent pas diverger sur le rangement
- [x] Table polymorphe **`cloture_verifications`** + modèle `ClotureVerification` — plutôt que deux colonnes réparties sur trois tables : poser des colonnes de clôture sur `recus` serait déplacé, et une quatrième famille de pièces exigerait une migration. Enregistre `verifie_par_id` et `verifie_at` (valeur probatoire), dévérifier = supprimer la ligne
- [x] `ClotureController` — `verifier()` / `retirerVerification()` / `verifierRubrique()` (« Tout vérifier » : sur vingt pièces, cocher une par une est punitif). Autorisé par `DossierPolicy::cloturerDocuments` (Notaire + Formaliste + Admin, étapes Expédition/Clôturé) **et** contrôle que la pièce appartient bien au dossier de l'URL — sans quoi l'accès à un dossier permettrait de cocher les pièces d'un autre. Le type est un mot court (`document`/`courrier`/`recu`), jamais un nom de classe venant du client
- [x] `DossierStepService::verifierExpedition()` — bloque tant qu'une pièce n'est pas vérifiée, message groupé par rubrique
- [x] `resources/js/Components/Dossiers/ClotureTab.jsx` — inventaire par rubrique, bandeau « X/Y pièces vérifiées et classées », case à cocher par pièce avec l'auteur et la date, « Tout vérifier » par rubrique. Les rubriques vides restent affichées (« aucune pièce de formalité » est une information), et une pièce sans fichier figure à l'inventaire — la masquer cacherait précisément ce qui manque
- [x] **GED rangée à l'identique** — `GedController` + `Ged/Index.jsx` : accordéon dossier > rubrique. Effet de bord bénéfique : les courriers et les reçus entrent enfin dans la GED, dont ils étaient absents bien qu'étant des pièces du dossier. Le filtre technique par `categorie` est remplacé par un filtre par rubrique
- [x] `DossierController::index()` — `peutAvancer` pour l'Expédition passe par `estCompletementVerifie()`, calculé seulement pour les dossiers de la page réellement à cette étape (l'inventaire est impossible à obtenir en `withCount`). Évite de rouvrir la divergence serveur/liste corrigée le 2026-08-03
- [x] `getStepBlockers()` (`Dossiers/Show.jsx`) — troisième miroir de la règle, mis à jour : lit `clotureProgression` fourni par le serveur plutôt que de réassembler l'inventaire côté client
- [x] `tests/Feature/ClotureInventaireTest.php` — 14 tests : les six rubriques dans l'ordre, rubriques vides conservées, accord client distinct des actes, catégorie d'acte inconnue toujours classée, pièce sans fichier présente, blocage puis acceptation de la clôture, dossier vide qui se clôture, traçabilité (qui/quand), retrait de vérification, « tout vérifier » limité à sa rubrique, cloisonnement entre dossiers (403), Rédacteur refusé, type de pièce inconnu rejeté
- [x] `AvancementEtapesTest` — les trois tests de l'ancienne règle d'Expédition réécrits sur la nouvelle

**Restrictions de l'étape — audit et corrections**

Audit demandé après la refonte. **Rien ne figeait un dossier clôturé** : les seuls garde-fous existants étaient `ClientProjectionService` (qui saute correctement les dossiers clos) et les scopes `enCours()`.

- [x] **`DossierPolicy::estFige()`** — un dossier `Cloture` n'accepte plus aucune modification de son contenu. Vérifié **avant** le raccourci administrateur de chaque ability, contrairement aux contrôles de rôle : c'est une propriété de l'état du dossier, pas une question de permission. Appliqué à `update`, `delete`, `genererDocuments`, `gererFormalites`, `gererFacturation`. La consultation reste ouverte — figer n'est pas masquer
- [x] **`gererFacturation` n'avait aucun contrôle d'étape** : un comptable pouvait enregistrer un paiement sur un dossier déjà clôturé, anomalie comptable pure
- [x] **`cloturerDocuments` autorisait l'étape `Cloture`** : une fois le dossier clos, on pouvait encore retirer la vérification d'une pièce ou déposer une nouvelle version signée — exactement l'altération silencieuse qu'une clôture doit interdire. Le contrôle a lieu **à l'Expédition**, pas après
- [x] **Le raccourci administrateur précédait le contrôle d'étape dans `cloturerDocuments`** : un administrateur pouvait donc vérifier des pièces sur un dossier encore en Édition. L'ordre est désormais celui de `gererFormalites()` / `genererCourriers()` — l'étape d'abord, le rôle ensuite
- [x] `ClotureTab` annonce « Dossier clôturé — l'inventaire est figé » au lieu de désactiver les cases sans explication
- [x] Vérifié sans faille : `DossierStepService::reculer()` n'est atteignable que depuis le renvoi en correction d'une certification (Révision → Édition) — **aucune route ne permet de sortir un dossier de Clôturé**, c'est volontaire. Et `avancer()` déléguant à `update()`, le gel le refuse désormais explicitement au lieu de compter sur `suivante() === null`
- [x] 5 tests supplémentaires : dossier clos figé, administrateur inclus dans le gel, vérification impossible avant l'Expédition, « Tout vérifier » soumis au gel, et les cinq abilities mutantes refusées sur un dossier clos alors que `view` reste autorisée

**Conservé de l'approche précédente (2026-07-24)**

- [x] `document_fichiers.est_signe_cachete`/`signe_cachete_at`/`signe_cachete_par_id` et `DocumentController::televerserSigne()` — la distinction version brouillon / version finale revenue du circuit papier reste utile, elle est simplement affichée comme un badge dans l'inventaire au lieu de piloter le blocage
- [x] **Verrouillage serveur** : `abort_if($document->est_signe_cachete, 403, ...)` dans `DocumentController::update/regenerer/destroy/restaurerVersion`
- [x] `DossierPolicy::cloturerDocuments()` — inchangée, elle autorise désormais la vérification
- [x] `document_versions.hash_sha256` — empreinte de toutes les versions (valeur probatoire)


### 🏛️ Refondu — Modification de société : registre, multi-modifications, actes conditionnels (2026-08-11)

Source : notes de l'étude sur le déroulé réel d'une modification (§3.2), recoupées avec les
tableaux 8 à 11 du CR de juillet 2026. La création de société était terminée ; la modification
avait été ébauchée quand l'information métier manquait et tenait en **six champs** —
`soc.denomination` en texte libre, `soc.rccm`, un `modif.type` à choix unique,
`modif.valeur_parts_cedees`, une zone de texte et une case à cocher « fiche de modification ».

**Cinq défauts que l'audit du code a révélés, au-delà de ce qui était signalé :**

| Constat | Correctif |
|---|---|
| 🔴 **Aucun modèle d'acte n'existait pour `SOC-MOD`** : un dossier de modification arrivait en Édition sans aucun document à corriger, alors que la procédure en produit jusqu'à cinq | 6 entrées seedées (`acte_cession`, `pv_modification`, `dnsv`, `statuts_maj`, `declaration_rccm`, `page_garde`), inactives en attendant les `.docx` |
| 🔴 **La génération ne savait pas filtrer** : elle prenait tous les modèles du type d'acte, donc un transfert de siège recevait un acte de cession vide et une DNSV sans objet | `ActesGeneratorService::typesDocumentsRetenus()`, **pour `SOC-MOD` seulement**, via les slugs de `documentsRequis()`. Aucune colonne ajoutée : `modeles_actes.type_document` portait déjà ce vocabulaire |
| 🔴 **Le droit de cession de 2 % portait sur 0 GNF** : `deduireAssiette()` cherchait `capital_chiffres`, `prix`… alors que les clés réelles sont **préfixées** (`soc.capital_chiffres`) — aucune assiette n'était donc jamais déduite, pour aucun type d'acte | Clés réelles ajoutées, et `modif.valeur_parts_cedees` testée **en premier** pour un `SOC-MOD` : l'assiette est la valeur des parts, jamais le capital social |
| 🔴 **Les tarifs de modification étaient posés sur tous les types de société** : une constitution SARLU se voyait facturer « Enregistrement des statuts **mis à jour** » et une DNSV | Colonne `baremes.condition_modification` + prédicat partagé `Bareme::estApplicableA()`, consommé par la facturation **et** la génération de formalités. Seeder corrigé, lignes hors périmètre **désactivées et non supprimées** |
| 🟠 **`ReglesSocieteService::modificationStatutaire()` était du code mort** — impact et documents calculés, jamais affichés | Exposé dans l'assistant (encart sous les cases) **et** dans l'onglet Informations |

**Décisions validées** : plusieurs modifications par dossier · table `societes` activée en
registre · les 3 étapes de l'assistant conservées, la structuration se faisant par cartes ·
champ `fiche_modification` supprimé (le questionnaire structuré le remplace).

#### Le registre des sociétés

- [x] **`societes` cesse d'être une table morte.** Elle existait depuis le 2026-06-29 sans
  controller, route ni seeder (§ « Problèmes connus ») : les dénominations ne vivaient que dans
  `questionnaires.donnees`, si bien qu'ouvrir une modification obligeait à retaper la
  dénomination, la forme, le capital et le siège d'une société que l'étude avait constituée
- [x] `dossiers.societe_id` (« ce dossier porte sur cette société ») en `nullOnDelete` —
  supprimer une fiche ne doit jamais emporter un dossier notarial. ⚠️ **Deux relations à ne pas
  confondre** : `Dossier::societe()` (belongsTo, les trois procédures) et
  `Dossier::societeConstituee()` (hasOne via `societes.dossier_id`, la constitution seule)
- [x] `Societe::depuisQuestionnaire()` / `versQuestionnaire()` — les **deux sens** de la
  traduction colonne ↔ clé `soc.*`, en un seul endroit. Rôle exactement symétrique de
  `ClientProjectionService` pour les personnes ([décision #33](#8-décisions-techniques)) :
  la fiche est la source de vérité, `donnees` n'en est qu'une projection. Miroir JavaScript dans
  `resources/js/lib/societeFields.js`, à faire évoluer avec lui
- [x] `SocieteController` (`autocomplete`, `show`, `store`, `update`) + `SocietePolicy`, calqués
  sur `ClientController`/`ClientPolicy`. **Pas de `destroy`** : supprimer une fiche référencée
  casserait la projection `soc.*` des dossiers, donc leurs actes — une société hors périmètre est
  désactivée (`actif = false`)
- [x] **Différence assumée avec la fiche client** : corriger une fiche société ne reprojette
  **rien**. La projection d'un dossier est figée au rattachement et n'évolue que par une
  modification statutaire — laquelle est l'objet d'un dossier, avec son PV, sa certification et
  ses formalités. Réaligner en silence contournerait ce circuit
- [x] `ayelema:societes-backfill`, **dry-run par défaut** (comme `brouillons-purger`). Vérifié
  sur la base réelle : **11 fiches créées, 12 dossiers rattachés, 0 doublon**, et relance
  idempotente (0 création). Les doublons de dénomination sont **signalés, jamais fusionnés** —
  fusionner deux fiches notariales est une décision de l'étude, pas d'un script
- [x] `SocieteMutationService`, appelé à l'entrée en **Expédition** : nouveau siège, capital,
  parts, objet, gérance portés au registre, avec un `JournalActivite` détaillant chaque champ.
  Le choix du moment se défend — avant les Formalités la modification n'est pas opposable, à la
  Clôture il serait trop tard (un dossier peut rester des semaines en Expédition pendant
  lesquelles la fiche mentirait)
- [x] Une **cession de parts ne modifie pas la fiche** : elle change les associés, pas les
  caractéristiques de la société. Le registre ne tient pas de table des associés — lui en
  inventer une dupliquerait ce que `Partie` porte déjà

#### Plusieurs modifications par dossier

- [x] **`modif.type` (choix unique) → `modif.types` (tableau)**, migration de données
  idempotente. Une même assemblée décide couramment une cession, un nouveau gérant et un
  transfert de siège, constatés par **un seul** procès-verbal : le choix unique obligeait à
  ouvrir un dossier par changement
- [x] Pas de `down()` destructif : `depuisLibelles()` accepte les deux formes, donc un retour
  arrière du code fonctionne sans retour arrière des données. Reconvertir un tableau de trois
  types en champ unique en perdrait deux
- [x] `TypeModificationStatutaire::documentsRequisPour(array)` — **union** et non concaténation,
  triée selon l'ordre de rédaction (`ORDRE_DOCUMENTS`) pour ne pas dépendre de l'ordre des clics
- [x] Nouveau type de champ **`checkbox_group`**, rendu dans `Create.jsx` **et** dans la modale
  d'édition de `Show.jsx` — un type géré d'un seul côté rendrait la valeur non modifiable après
  création. Composant partagé `Components/ui/choix-multiple.jsx`
- [x] `showIf` gagne `includes` / `includesAny`, et traite le **tableau vide comme une absence de
  choix** : `[]` étant truthy en JavaScript, un groupe vidé laissait ses champs dépendants
  affichés et « Suivant » actif

#### Septième type : la diminution de capital

- [x] `TypeModificationStatutaire::CapitalDiminution` — les notes de l'étude sont explicites :
  « Augmentation de capital : une DNSV est établie. **Diminution de capital : aucune DNSV n'est
  requise dans ce cas.** » Cette asymétrie est portée par `exigeDnsv()`, méthode propre plutôt
  que déduite de `documentsRequis()` : elle conditionne aussi le barème DGI et le bloc de champs
- [x] `documentsRequis()` gagne **`declaration_rccm`**, dérivé de `impacteRccm()` : les notes
  comptent le RCCM parmi les actes **édités** d'une cession, pas seulement parmi les formalités
- [x] **Règle 1 appliquée à la modification** : réduire le capital d'une SA sous 140 M GNF est
  refusé. Ce garde-fou n'existait que pour la constitution — on obtenait donc par réduction ce
  qu'elle interdisait. Une réduction totale est refusée avec renvoi vers la dissolution

#### Le questionnaire, de 6 à 73 champs répartis en 15 sections

- [x] **Société concernée** — marqueur `societePicker`, `SocietePicker` + `ModalNouvelleSociete`.
  Les champs `soc.*` sont masqués et remplis par la fiche dès qu'elle est rattachée, comme les
  champs d'identité d'un client lié. Le picker affiche une carte de synthèse **et propose
  d'importer les associés et gérants du dossier de constitution** : pour une cession, le cédant
  est presque toujours un associé déjà fiché, et le proposer garantit que c'est la **même** fiche
  client, non un doublon concurrent
- [x] **Assemblée générale** (`ag.*`) — nature, date, heure, lieu, présidence, quorum, parts
  représentées, **date d'effet** (souvent ≠ date d'AG), résolutions. Aucun de ces champs
  n'existait, alors que le PV est produit dans tous les cas
- [x] Un bloc par type de modification, conditionné par `includes` : cession (cédants,
  cessionnaires, agrément, **répartition après**), siège, augmentation (souscripteurs, banque,
  versement → DNSV), diminution (**sans DNSV**), gérance (sortant/entrant), objet social
- [x] Le **capital après** n'est jamais saisi : il se déduit du capital actuel et du montant de
  l'opération — statuts, PV et DNSV doivent porter le même chiffre
- [x] `FORMES_SOCIETE` exportée : les questionnaires portaient chacun leur copie de **sept**
  formes, où **SCS et SAU** (ajoutées en juillet 2026) n'étaient jamais entrées.
  `Societe::formeLabel()` délègue désormais à `FormeSociete::label()` — son `match` local
  ignorait ces trois formes et les affichait sous leur sigle brut
- [x] Nouveaux rôles et pièces (`Partie`) : `cedant`, `cessionnaire`, `souscripteur`,
  `gerant_entrant`. Le `in_array` en dur devient une table `JEU_PAR_ROLE`. **Pas de pièces** pour
  `gerant_sortant` ni la présidence de séance : ils sont mentionnés à l'acte, ils n'apportent
  rien (un gérant révoqué, a fortiori décédé, ne fournit plus de justificatif).
  `DossierController::store()` dérive « a des pièces à fournir » de la checklist elle-même, au
  lieu d'une liste de rôles recopiée

#### Deux correctifs trouvés en vérifiant sur la base réelle

1. 🐛 **La règle 4 signalait une société en doublon d'elle-même.** `SOC-2026-0010`
   (constitution de MICH SARL) était en conflit de dénomination avec `SOC-2026-0012`, la
   **modification** de cette même société. `verifierDenominationUnique()` ne compare plus que les
   dossiers de **constitution** (`FormeSociete::depuisCodeTypeActe() !== null`) — un dossier de
   modification porte le nom de la société qu'il traite, ce n'est pas un homonyme.
2. 🐛 **Le `with('dossier:id,reference')` de ce même correctif désarmait la règle** : sans
   `type_acte_id` dans la sélection, Eloquent ne résout pas `dossier.typeActe`, la relation
   revient à `null` et le filtre écartait *tous* les dossiers, vrais homonymes compris. Deux
   tests verrouillent les deux sens.

> ⚠️ **Le dictionnaire de balises annonçait une convention qu'aucun code n'appliquait.**
> « Toute date a la variante `_jma` » y figure depuis l'origine, sans qu'aucun `_jma` ne soit
> produit pour les champs du questionnaire : un modèle normalisé selon la documentation laissait
> `${ag.date_jma}` littéral dans l'acte. Désormais dérivée pour tout champ au format JJ/MM/AAAA,
> avec la variante `_lettres`. Un **choix multiple** est aussi écrit comme énumération
> (`${modif.types}`) : la valeur étant un tableau, le moteur la routait vers `remplirBlocRepetable()`,
> qui ignore les items non-tableaux — la balise restait littérale.

- [x] `dictionnaire_balises.md` — section « 10 bis » : `modif.*`, `ag.*`, `gerant_sortant.*`,
  `gerant_entrant.*`, blocs répétables, et le mapping des cinq modèles. **Le piège documenté en
  tête** : `soc.*` porte l'état **avant**, `modif.*` l'état **après** — les statuts mis à jour
  doivent porter l'après
- [x] `tests/Feature/ModificationSocieteTest.php` (39 tests) et `RegistreSocietesTest.php`
  (20 tests), un par règle et nommé d'après elle. Les cas décisifs sont ceux d'**asymétrie** —
  ce qu'une modification produit et ce qu'une autre ne produit pas —, plus deux tests de
  non-régression : les modèles d'un `SOC-SARLU` ne sont pas filtrés, un barème sans condition
  reste applicable partout
- [x] Vérifié sur la base réelle : les **17 fiches dossiers répondent en 200**, le dossier
  `SOC-2026-0012` a bien migré vers `modif.types`, il est rattaché à sa fiche registre, le
  barème DNSV est retenu et le droit de cession écarté, et la génération retiendrait
  `pv_modification / dnsv / statuts_maj / declaration_rccm / page_garde` — **pas** l'acte de
  cession

**Reste à brancher** : les gabarits `.docx` (acte de cession, PV d'AGE, statuts mis à jour, DNSV,
déclaration RCCM, page de garde) ne figurent pas dans `Documents reçus/`, qui n'en contient que
les **deux lettres de transmission** (déjà seedées). Les entrées de modèles existent, inactives,
prêtes à recevoir leur fichier depuis *Modèles d'actes*.

#### 🐛 Correctif du même jour — modifications incompatibles

Signalé en testant l'écran livré : quatre cases cochées ensemble, dont **Augmentation + Diminution
de capital** et **Gérant statutaire + non statutaire**. Rien ne l'empêchait, ni le formulaire ni le
serveur. Deux combinaisons se contredisent, et la première **corrompait silencieusement le registre**.

| Constat | Correctif |
|---|---|
| 🔴 **Augmentation + Diminution écrasaient le capital au registre.** Les deux écrivent `capital_chiffres` ; `SocieteMutationService` fusionnait type par type, donc `CapitalDiminution` — déclaré **après** dans l'enum — l'emportait. **L'ordre de déclaration de l'enum décidait du capital de la société**, sans le moindre avertissement. Le questionnaire produisait de son côté deux « capital après » contradictoires, calculés indépendamment depuis le même capital de départ | `TypeModificationStatutaire::incompatiblesAvec()` |
| 🔴 **Gérant statutaire + non statutaire décrivaient le même changement.** Le questionnaire n'a qu'un seul bloc de gérance (un sortant, un entrant) : les deux cases qualifiaient donc le même fait de deux façons exclusives. `impacteStatuts()` étant une union, des **statuts mis à jour étaient produits** que le cas non statutaire ne justifie pas | idem |
| 🟠 **Décocher une modification ne purgeait pas ses champs.** Cocher *Cession*, saisir 25 000 000 en valeur des parts, décocher : la valeur restait dans `donnees` et `deduireAssiette()` la retenait **en priorité** — la facture portait donc cette assiette sur un dossier sans cession. Le même travers existait ailleurs : les champs `ger.*` d'une SARLU restaient soumis après avoir décoché « le gérant est une personne différente » | `purgerChampsInvisibles()` |

**Trois barrières, pas une** — le formulaire n'est pas le seul chemin vers
`questionnaires.donnees` (`PATCH /dossiers/{ref}/questionnaire`, conversion d'une demande externe,
brouillon antérieur, migration `modif.type → modif.types`) :

1. **Saisie** — `ChoixMultiple` grise l'option exclue, motif en infobulle, et affiche une ligne
   d'avertissement nommant la paire. ⚠️ **Une option déjà cochée n'est jamais grisée** : sans cette
   exception, une sélection déjà en conflit serait un cul-de-sac, chacune désactivant l'autre.
   Logique extraite en fonctions pures dans `resources/js/lib/exclusionsChoix.js` — vérifiable sans
   rendu, le composant n'étant plus qu'un afficheur. La table d'exclusions est **dérivée de la prop
   `typesModification`** : la règle n'est jamais redéclarée en JavaScript. Câblée dans les **deux**
   rendus (assistant *et* modale d'édition) — d'où l'ajout de `typesModification` aux props de
   `DossierController::show()`.
2. **Avancement** — `ReglesSocieteService::verifierIncompatibilites()`, contrôlé **en premier et
   seul** : tant que la sélection se contredit, les contrôles de détail porteraient sur des blocs
   qui n'ont pas à coexister et noieraient le vrai problème.
3. **Registre** — `SocieteMutationService::fusionnerChangements()` **détecte les collisions** au
   lieu de laisser le dernier écraser. Une colonne réclamée avec deux valeurs différentes est
   **écartée** (ni la première, ni la dernière — les deux seraient arbitraires) et le refus est
   journalisé nommément. Les autres colonnes s'appliquent : un conflit sur le capital ne doit pas
   empêcher d'enregistrer le transfert de siège décidé par la même assemblée. Deux types proposant
   la **même** valeur ne sont pas un conflit (les deux changements de gérant écrivent le même bloc
   `direction`).

- [x] **« Répartition du capital après » sort du bloc cession** : elle s'affiche pour les **trois**
  opérations qui changent qui détient quoi — cession, augmentation (parts nouvelles), diminution
  (parts annulées). Et elle est **déplacée après** les blocs de capital : on ne renseigne la
  répartition finale qu'une fois connu de combien le capital varie. Clé inchangée, aucune migration
- [x] `purgerChampsInvisibles()` appliquée **à la soumission uniquement** (création et édition du
  questionnaire), et à `buildPartiesPayload()` — sans quoi une cession décochée créerait encore ses
  cédants et cessionnaires. **Jamais sur un brouillon** : il doit conserver une saisie mise de côté
  pour qu'en recochant la case le clerc retrouve ses valeurs
- [x] **Décision** : pas de type « coup d'accordéon » (réduction puis augmentation immédiate) pour
  l'instant. S'il se présente, ce sera un type dédié portant **un** capital final — pas deux cases
  qui se contredisent
- [x] 15 tests ajoutés, dont un **invariant de symétrie** : si A exclut B, B doit exclure A —
  faute de quoi l'exclusion dépendrait de l'ordre des clics. Plus la non-régression : trois
  modifications compatibles (cession + siège + gérant) restent conformes
- [x] Vérifié sur la base réelle : **aucune nouvelle anomalie** sur les 12 dossiers de société, les
  17 fiches toujours en 200, et le scénario de cul-de-sac contrôlé sur la table d'exclusions réelle

### 🔑 Jeton CSRF manquant, et choix de société posé en clair (2026-08-11)

#### 🔴 « Votre session a expiré » alors qu'elle était valide

Signalé en cliquant « Ajouter au registre ». Cause : **aucune balise `<meta name="csrf-token">`**
dans `app.blade.php`, et `bootstrap.js` ne configurait aucun en-tête de jeton. Les appels axios ne
reposaient donc que sur le cookie `XSRF-TOKEN` posé par Laravel — dès qu'il manquait ou avait expiré
(page laissée ouverte, cookie purgé), la requête partait sans jeton, Laravel répondait **419 avec sa
page HTML** d'erreur, et l'appelant, ne trouvant pas de JSON exploitable, en concluait à une session
expirée. Reproduit : `POST /societes` sans jeton → 419 + HTML.

- [x] Balise `csrf-token` dans le layout, et `X-CSRF-TOKEN` posé en en-tête axios par défaut. Le
  jeton de la balise est stable pour la durée de la page et ne dépend d'aucun cookie
- [x] Concerne **tous** les appels axios de l'application (création de société ou de client depuis
  l'assistant, dépôt d'une pièce constitutive), pas seulement celui qui a révélé le défaut

#### ✨ Du registre ou hors registre — le choix, posé en premier

La modale « Nouvelle société au registre » rendait le choix implicite : il fallait chercher, ne rien
trouver, puis penser à l'ouvrir. Or le clerc sait dès le départ s'il traite une société que l'étude a
constituée ou celle d'un confrère.

- [x] `Components/Societes/ChoixSociete.jsx` — deux options en clair, **dans la page** et non dans
  une fenêtre qui masque la saisie : *Société de notre registre* (sélecteur, informations et dossier
  constitutif repris) ou *Société hors registre* (champs `soc.*` saisis dans le dossier)
- [x] Une fiche rattachée impose son mode et désactive l'autre : proposer « hors registre » alors
  qu'une société est liée n'aurait pas de sens, et le détachement est déjà offert par le sélecteur
- [x] En mode registre **sans fiche choisie**, les champs `soc.*` sont masqués : le sélecteur *est*
  la saisie, et les afficher vides sous lui inviterait à une double saisie dont l'une serait perdue
- [x] `ModalNouvelleSociete` n'est plus montée dans l'assistant
- [x] **Le pendant serveur, qui manquait** : une modification sur une société hors registre crée
  désormais sa fiche. Sans cela, la saisie n'aurait servi qu'une fois et le dossier suivant l'aurait
  redemandée. `dossier_id` reste **nul** — ce dossier ne constitue pas la société — ce qui maintient
  l'exigence de son dossier constitutif. Deux tests verrouillent les deux sens

### 🗺️ Référentiel de lieux et contrôles de cohérence sur les identités (2026-08-12)

`ClientController::regles()` ne vérifiait que des **types** : une pièce pouvait expirer avant d'avoir
été délivrée, une naissance être postérieure à la pièce, un régime matrimonial être renseigné pour un
célibataire. Et le triplet ville/commune/quartier était en saisie libre à **dix endroits**.

**Ce n'était pas théorique** — les valeurs déjà en base : `Forecariah` enregistré comme **commune**
(c'est une préfecture), `Kountia` comme **quartier de Conakry** (il est à Dubréka), `GBESSIA` comme
commune (c'est un quartier de Matoto).

#### 🔴 Le défaut qui rendait tous ces contrôles invisibles

`bootstrap/app.php` réduisait le rendu JSON au seul préfixe `api/*` — qu'**aucune** route du projet
n'utilise :

```php
$exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
```

Ce prédicat **remplace** l'heuristique par défaut de Laravel (`expectsJson()`), il ne s'y ajoute pas.
Toute erreur de validation partait donc en **redirection HTML**, y compris pour les appels XHR :
mesuré, `POST /clients` invalide renvoyait un **302 text/html**. Conséquence — la branche
`status === 422` de **toutes** les modales en axios (nouveau client, nouvelle société, dépôt de
pièce) était du code mort, aucune erreur de champ ne s'est jamais affichée, et le HTML reçu était
même interprété comme une session expirée.

- [x] `expectsJson()` rétabli, `api/*` conservé pour un futur préfixe d'API. `POST /clients`
  invalide renvoie désormais **422 + `errors`**

#### Le référentiel — une table, trois niveaux

- [x] `lieux` **auto-référencée** (`parent_id`, `niveau`, `nom_normalise`, `a_verifier`, `actif`) :
  un seul écran, un seul point d'entrée, un seul composant. La hiérarchie guinéenne est à profondeur
  variable, un quatrième niveau ne coûtera rien
- [x] **Unicité relative au parent** : « Matam » est une commune de Conakry **et** une préfecture —
  une unicité globale l'aurait interdit
- [x] `App\Support\Normalisation::comparable()` — extraite de `Societe::normaliserDenomination()`,
  qui y délègue désormais. Elle **retire les accents** : « Forécariah » et « Forecariah » sont le même
  lieu. La règle 4 en devient légèrement plus stricte, ce qui va dans son sens
- [x] **La valeur stockée reste le nom**, jamais l'identifiant : aucune migration de
  `questionnaires.donnees`, et les balises `${soc.siege_quartier}` fonctionnent inchangées. Un acte
  signé garde le nom qui était le bon ce jour-là
- [x] `LieuSeeder` — **34 villes** (Conakry + 33 préfectures), **38 communes**, **54 quartiers**.
  ⚠️ Les quartiers portent `a_verifier = true` : leur liste n'est ni garantie exhaustive ni garantie
  à jour, et une donnée administrative fausse présentée comme sûre serait pire que du texte libre.
  `firstOrCreate` et non `updateOrCreate` : une relance ne remet pas à « à vérifier » ce que l'étude
  a validé — vérifié
- [x] `ayelema:lieux-rapprocher` — **dry-run par défaut**. Sur la base réelle : 37 valeurs conformes,
  5 écarts nommés, avec **suggestion** pour les fautes de frappe (« Almanya » → Almamya, « Lambagni »
  → Lambanyi). Seuil de ressemblance **relatif à la longueur** : un seuil fixe rapprochait « Kountia »
  de « Koubia », deux lieux sans rapport. `--appliquer` **vide** le champ mal placé plutôt que de
  deviner où reposer la valeur, et ne touche **jamais** aux questionnaires — ils alimentent des actes
  déjà produits

#### La cascade et les autorisations

- [x] `LieuSelect` — un composant **par niveau**, parce que le triplet est déclaré comme trois champs
  distincts à dix endroits : les regrouper aurait imposé de migrer `donnees` et de toucher aux
  balises Word. Changer la ville **réinitialise** commune et quartier, sans quoi on garde une commune
  orpheline — l'incohérence même qu'on supprime
- [x] Un parent manquant **neutralise** le champ au lieu de le remplir : le point d'entrée renvoie
  `[]` plutôt que les 54 quartiers du pays
- [x] Une valeur héritée hors référentiel reste **visible et sélectionnée**, signalée — sinon ouvrir
  une fiche ancienne l'effacerait en silence
- [x] Ajout en un clic (`peuventOuvrir()`, même ensemble que `ClientPolicy`), **jamais** sans
  authentification : l'intake public recevra le référentiel dans ses props. Vérifié par test
- [x] Correction/désactivation réservées à l'**administrateur** : ajouter est un geste de saisie,
  corriger le référentiel est un acte d'administration

#### Les contrôles de cohérence

- [x] Dates : naissance ni future ni avant 1900 · pièce délivrée ni future ni avant la naissance ·
  expiration postérieure à la délivrance
- [x] **Paires indissociables** : un type de pièce sans numéro ne prouve rien, un numéro sans type ne
  se vérifie pas
- [x] `regime_matrimonial` `prohibited_unless` marié — et le champ **disparaît** du formulaire sinon,
  plutôt que d'accepter une saisie que le serveur refuse
- [x] Personne morale : `representant_legal` **requis** — l'acte le nomme, et c'est lui qui signe
- [x] Téléphone guinéen (`6XX XX XX XX`, `+224`/`00224` et séparateurs tolérés)
- [x] **Pièce expirée : avertissement seul** (choix retenu) — `Client::pieceExpiree()` et
  `avertissements()`. L'étude consigne la situation réelle avant de demander un renouvellement ; la
  cohérence des dates entre elles, elle, décrit une saisie impossible et reste bloquante
- [x] Messages réécrits là où Laravel se lit mal (`before:today` interpolait « antérieure au today »)
- [x] **Pays de résidence verrouillé** — `ChampVerrouille` : la valeur vaut « République de Guinée »
  sur la totalité des fiches, et la laisser en saisie libre l'exposait à une modification par
  inadvertance sur une donnée qui figure dans les actes. Verrouillé, **pas supprimé** : un clic
  délibéré le déverrouille, car un client peut résider à l'étranger. Appliqué à la fiche client et
  aux **9 champs `pays`** des questionnaires, via le `readonly` déjà prévu par le moteur
- [x] ⚠️ **`readonly` n'était honoré que par l'assistant** : ni la modale d'édition du questionnaire
  ni le formulaire public ne le lisaient, si bien qu'un champ verrouillé à la création redevenait
  librement modifiable à la première correction. Les trois rendus le respectent désormais — même
  leçon que `checkbox_group` et le type `lieu`
- [x] `ControlesClientTest` (17 tests) et `LieuxTest` (14 tests) — un test par règle, nommé d'après
  elle. **376 tests, 368 passent**, les 8 échecs préexistants inchangés

#### La cascade, partout où le triplet apparaît

- [x] **`TRIPLETS_GEO`** — les 7 triplets déclarés **une fois** dans `questionnaires.js`, plus
  `roleGeo()` et `patchGeo()`. Aucune des **48 déclarations de champ** n'est touchée : les
  identifiants ne changent pas, donc ni `questionnaires.donnees` ni les balises Word ne bougent.
  Une table explicite était nécessaire — le préfixe varie (`soc.siege_`, `pp.`, `bq.siege_`,
  `modif.siege_nouveau_`, ou rien dans les blocs répétables) et le champ ville n'est pas régulier
  (`siege_ville` ici, `demeurant_ville` là)
- [x] ⚠️ **Ne pas se fier au suffixe** : `bien.livre_foncier_ville` finit par « ville » sans être un
  lieu du référentiel — c'est une mention cadastrale. C'est la raison d'être de la table
- [x] Rendu branché dans les **trois** renderers — `Create.jsx`, la modale d'édition de `Show.jsx`,
  et `Intake/Show.jsx` — comme `checkbox_group` l'avait exigé : un type géré d'un seul côté rendrait
  la valeur non modifiable après la création du dossier
- [x] **Formulaire public** : cascade **hors ligne**, le référentiel voyageant dans les props de
  l'intake. Aucun point d'entrée exposé, et l'ajout d'un lieu n'y est pas offert — vérifié par test
- [x] Fiche **société** : même cascade, et l'ordre d'affichage devient ville → commune → quartier,
  qui est l'ordre de saisie (l'ancien allait à l'envers). Règles de dates et de téléphone alignées
  sur la fiche client — `date_constitution` ni future ni avant 1958
- [x] **`Paramètres > Lieux`** — accordéon ville/commune/quartier, **ajout aux trois niveaux**,
  renommage, activation, et le **filtre « à vérifier »** qui est la liste de travail. Les formulaires
  d'ajout sont repliés par défaut : cet écran sert d'abord à valider les 54 quartiers amorcés,
  l'ajout est le geste secondaire. Ils passent par le **même point d'entrée** que la cascade des
  formulaires, pour que la validation des niveaux n'ait qu'une implémentation
- [x] **Suppression possible — mais seulement d'un lieu non référencé.** La désactivation seule
  laissait le référentiel se remplir de scories sans recours : un lieu ajouté par erreur, mal
  orthographié ou placé au mauvais niveau ne pouvait plus partir. `Lieu::estSupprimable()` refuse
  dans deux cas seulement, et le message distingue lesquels parce que la conduite à tenir diffère :
  **des enfants** (les supprimer d'abord — une cascade silencieuse emporterait les quartiers d'une
  commune) ou **un nom employé** dans une fiche ou un questionnaire, donc possiblement dans un acte
  produit (désactiver plutôt). `nomsEmployes()` balaie une seule fois pour tout l'écran, blocs
  répétables compris — le faire par lieu rebalaierait les questionnaires 126 fois. La corbeille
  n'apparaît que si la suppression est possible ; sinon elle est grisée et l'infobulle dit pourquoi
- [x] **`a_verifier` dépend de qui ajoute** — un clerc créant un lieu en pleine saisie fait un geste
  utile mais non validé, il entre dans la liste de travail ; un administrateur ajoutant depuis
  l'écran du référentiel **est** cette validation, et marquer son propre ajout le lui renverrait à
  lui-même
- [x] Asymétrie des droits : **ajouter** un lieu manquant est un geste de saisie (rôles qui ouvrent
  des dossiers), **corriger** le référentiel est un acte d'administration — renommer « Ratoma »
  changerait la liste proposée à toute l'étude
- [x] `CascadeGeoQuestionnaireTest` (18 tests) : les quartiers d'une commune ne débordent pas sur
  l'autre, un lieu ajouté sert immédiatement, le public ne peut pas écrire, un niveau inconnu est
  refusé, le seeder place bien une préfecture comme **ville** portant sa commune urbaine homonyme

**394 tests, 386 passent** — 49 ajoutés, les 8 échecs préexistants inchangés. Vérifié sur la base :
`/parametres/lieux`, `/parametres`, `/dossiers/create`, `/repertoire`, `/modeles`, les 18 fiches et
le formulaire public d'intake répondent tous en 200.

### 🔤 Le vocabulaire des types de document, servi et non recopié (2026-08-11)

Signalé à l'usage : la colonne **TYPE** de l'onglet Actes affichait `statuts_maj` et
`declaration_rccm` en brut, là où les autres lignes montraient « DNSV ».

Cause : le vocabulaire était **recopié trois fois en JavaScript**, et les trois copies avaient
divergé de la référence PHP — 13 entrées chacune contre 17, les quatre types de la modification
statutaire manquant partout. Le repli `?? doc.categorie` faisait alors ressortir le slug.

> Ajouter les quatre entrées à la main aurait réglé l'affichage du jour **et laissé la cause
> intacte** : la prochaine addition au vocabulaire aurait recréé le même écart, aux trois mêmes
> endroits.

- [x] `HasTypeDocumentLabel` gagne `champTypeDocument()` — **une méthode et non une propriété** : PHP
  refuse qu'une classe redéclare une propriété de trait avec une valeur par défaut différente.
  `DocumentFichier` la surcharge à `categorie`, `ModeleActe` et `ModeleCourrier` gardent
  `type_document`
- [x] Le libellé **voyage avec le document** : `typeDocLabel` dans `documentFichierToArray()` — la
  fonction que traversent tous les documents de la fiche — et dans le payload de Certification
- [x] `TYPE_DOC_LABELS` **supprimée** de `Show.jsx` et `Revision.jsx`. Dans `Modeles/Index.jsx`,
  `TYPES_DOC` est réduite à **l'icône et la couleur** : le libellé est du vocabulaire métier, la
  présentation reste au client. Un slug absent de cette table garde une icône neutre
- [x] Repli sur le **slug** et non sur une chaîne vide : un type non répertorié doit rester lisible —
  c'est ce qui a permis de repérer le défaut

#### 🔴 Deux régressions de l'unification GED, trouvées en remontant la piste

`RevisionController` lisait `$doc->type_document` et `$doc->chemin_fichier` — **deux colonnes qui
n'existent pas** sur `DocumentFichier` depuis le 2026-07-24 (le type est `categorie`, le fichier vit
sur la version courante).

- [x] Le type était donc **vide depuis toujours** sur l'écran de Certification
- [x] Et `has_file` **toujours faux** : vérifié en base sur un acte dont le fichier existe bel et
  bien. L'aperçu et le téléchargement disparaissaient donc de l'écran où le certificateur doit
  précisément lire les actes avant de les valider
- [x] `has_file` lit `versionActuelle`, chargée en `load()` pour éviter une requête par document
- [x] `tests/Feature/VocabulaireTypesDocumentTest.php` — 7 tests, dont un **garde-fou** : les options
  exposées aux écrans doivent couvrir exactement les clés de `TYPES_DOCUMENT`, sinon un type ajouté
  redeviendrait invisible quelque part

> ⚠️ `accord_client` n'est **pas** dans `TYPES_DOCUMENT`, volontairement : cette liste est aussi la
> règle de validation des modèles, et un accord client se téléverse, il ne se génère pas. Vérifié
> qu'il ne fuit nulle part — il est exclu côté serveur des deux listes de documents et rendu par
> `AccordClientCard`, qui porte son propre titre.

### 🏷️ « Modification de société », et non « de statuts » (2026-08-11)

Relevé à l'usage : le type d'acte s'intitulait **« Modification de statuts »** alors qu'il couvre les
sept résolutions — cession de parts, transfert de siège, capital, objet, gérance.

Le libellé n'était pas seulement étroit, il était **faux dans un cas** : le *changement de gérant non
statutaire* ne touche pas les statuts, seul le RCCM enregistre le changement
(`TypeModificationStatutaire::impacteStatuts()` → `false`, règle 8 du CR de juillet 2026). Un clerc
pouvait hésiter à ouvrir ce dossier pour un changement qui ne relève pas des statuts.

- [x] `label` → **« Modification de société »**, cohérent avec « Constitution SARL » et « Dissolution
  de société » : les trois procédures de la catégorie forment une série lisible
- [x] `description` → « Modification d'une société existante — statuts et/ou RCCM (capital, gérance,
  siège, objet, cession de parts) », qui nomme les **deux** registres touchés
- [x] Le **code `SOC-MOD` ne change pas** : il est déjà générique, et c'est lui que référencent les
  dossiers, les rattachements de gabarits, `VariantesTypeActe` et les règles. Renommer un code pour
  un libellé aurait été un risque sans contrepartie
- [x] Appliqué par `TypeActeSeeder`, idempotent (`updateOrCreate` sur le code) : aucune migration.
  Vérifié — les 2 dossiers `SOC-MOD` sont intacts, 30 types en base, aucun libellé codé en dur ailleurs

### 🎯 Rôles et types d'actes confrontés, rôle principal respecté (2026-08-11)

Signalé à l'usage : « RCCM SARLU » portait bien le rôle *Déclaration de modification RCCM*, mais
l'acte n'apparaissait dans aucun dossier de modification. Deux défauts distincts.

#### 🔴 Le rôle principal était ignoré — régression du jour même

`rolePourProcedure()` retenait `$roles[0]` — le **premier rôle enregistré** — dès que la procédure
n'exprimait aucune attente. Or `$retenus === null` couvre **tous les types d'actes sauf `SOC-MOD`** :
constitutions, ventes, baux, hypothèques. C'était donc l'ordre d'insertion en table qui décidait du
type d'un document, et non `type_document`, la déclaration explicite de l'administrateur.

Mesuré : sur une constitution SARLU, « RCCM SARLU » produisait un document typé **`declaration_rccm`**
au lieu de `rccm`, ses rôles se lisant `[declaration_rccm, rccm]`.

- [x] Le **rôle principal tranche** quand rien ne l'impose ; la procédure garde la main quand elle
  attend un rôle précis. Vérifié dans les deux sens : `SOC-SARLU` → `rccm`, `SOC-MOD` →
  `declaration_rccm`
- [x] Test de non-régression avec les rôles déclarés dans l'ordre **inverse** du rôle principal —
  il échoue sans le correctif
- [x] `ayelema:retyper-actes` (dry-run par défaut, `--appliquer`) recale les actes déjà produits avec
  un rôle erroné. Une commande et non une migration : cela touche des dossiers en cours et se lit
  avant de s'appliquer. Ne corrige que si la catégorie actuelle fait partie des rôles du gabarit —
  sinon elle vient d'ailleurs (dépôt manuel), et ce n'est pas à cette commande d'en décider.
  **Résultat sur la base : aucun acte mal typé**, le rôle ayant été ajouté après les générations

#### 🟠 Un rôle qu'aucun type rattaché n'attendait, accepté en silence

Le gabarit n'était rattaché qu'à `SOC-SARLU` et `SOC-SARL`, alors que le rôle coché n'existe que dans
la procédure de **modification**. Configuration contradictoire, sans effet, et sans avertissement.
L'information existait dans *Paramètres > Types d'actes*, mais dans un autre écran que celui où
l'erreur se commet.

- [x] `ActesGeneratorService::rolesAttendusPour(TypeActe)` — union sur toutes les variantes, **`null`
  quand la procédure les accepte tous**. `null` n'est pas « aucun » mais « tous » : une constitution
  ne déclare pas de documents attendus, et scinder la liste là où tout est pertinent serait mentir
- [x] La liste des rôles de la modale se **scinde** : *attendus par vos types d'actes* / *autres
  rôles*. Un rôle coché sans effet affiche « Sans effet : aucun type coché n'attend ce rôle. Attendu
  par *Modification de statuts* », et cliquer sur ce nom **rattache le type**. Jamais bloquant :
  préparer un gabarit avant de le rattacher reste légitime

> ⚠️ **Première version corrigée le jour même** : elle taisait tout avertissement dès qu'un type
> « sans attente déclarée » était coché — donc dès qu'une constitution l'était. C'est exactement le
> cas signalé : le gabarit RCCM rattaché à deux constitutions portait le rôle de modification, et
> rien ne le signalait.
>
> Le critère juste ne confond pas **« accepte tout »** et **« attend »** : depuis le correctif de
> `rolePourProcedure()`, une procédure sans attente déclarée retient toujours le **rôle principal**,
> donc un rôle secondaire n'y sert **jamais**. Un rôle est inerte s'il n'est ni le rôle principal, ni
> attendu par un type rattaché — et ce critère ne dépend pas du découpage de la liste. Deux tests le
> fixent : le rôle seul ne rend pas un gabarit applicable à une autre procédure, et le rattachement
> lui donne effet.

#### L'écart signalé sur le dossier

- [x] `dossier.actesManquants` — actes prévus par la configuration et absents du dossier. Corriger un
  rattachement ne réveille **pas** les dossiers existants, et c'est voulu : une correction ne doit pas
  modifier en silence les dossiers d'autres clercs. Mais l'écart doit se voir
- [x] Comparaison par `nom`, **la même que la génération** utilise pour ne pas écraser un acte déjà
  produit : les deux doivent voir le même ensemble, sinon l'encart annoncerait un acte que le bouton
  ne produirait pas
- [x] Seuls les actes **ayant un gabarit** y figurent : un rôle attendu sans gabarit relève de la
  configuration, pas du dossier, et le bouton ne pourrait rien en faire
- [x] Encart en tête de l'onglet Actes, avec « Produire les actes manquants » — le bouton de
  génération existant, dont la raison est enfin affichée
- [x] 7 tests ajoutés ; vérifié sur la base : 18 fiches et les 3 écrans concernés en 200

### 🧹 Un seul champ « types d'actes », et un récapitulatif qui dit vrai (2026-08-11)

Signalé à l'usage : la modale d'un modèle demandait **deux fois** la même chose — « Type d'acte
d'origine » (liste simple) et « Types d'actes auxquels ce modèle s'applique » (cases). Le second est
né avec le partage de gabarits, le premier a survécu.

Audit de ce que `modeles_actes.type_acte_id` portait encore : `applicablePour()` et
`scopePourTypeActe()` — **ce qui décide de la génération** — ne la lisaient jamais. Son seul
consommateur métier était `TypeActe::modeles()`, qui alimentait la carte « Actes à produire » de
l'assistant, **et l'alimentait faux** : un gabarit partagé n'y figurait pas, un gabarit détaché y
restait, et les variantes étaient ignorées — un dossier de modification annonçait tous les actes
alors que la génération n'en produit que ceux des résolutions cochées.

- [x] **Colonne supprimée** (`drop_type_acte_id_from_modeles_actes`). Garde-fou avant suppression :
  la migration **échoue** si un modèle n'a pas de rattachement correspondant, plutôt que de rendre un
  gabarit inapplicable en silence. ⚠️ Les deux moteurs imposent des ordres opposés — MySQL refuse de
  supprimer l'index composite tant que la clé étrangère s'y appuie, SQLite refuse de supprimer une
  colonne encore référencée. Contrainte → index → colonne est le seul ordre qui satisfasse les deux
- [x] `TypeActe::modeles()` **supprimée** plutôt que réécrite : un `hasMany` ne peut voir ni les
  gabarits partagés, ni `applicable_tous`, ni les variantes
- [x] Clé d'idempotence du seeder ramenée au **`nom` seul** — 68 modèles, 68 noms distincts, vérifié.
  Le rattachement y est désormais créé explicitement, en `firstOrCreate`
- [x] `synchroniserOrigine()` supprimée. Nouvelle règle : `type_acte_ids` est **obligatoire** sauf
  `applicable_tous` — sans `type_acte_id`, plus rien n'imposait qu'un gabarit serve à quelque chose,
  et un modèle rattaché à rien n'aurait été généré nulle part, en silence
- [x] `dupliquer()` recopie **rattachements et rôles** : sans cela, la copie ne dupliquait pas ce qui
  compte et n'aurait rien produit

#### Le récapitulatif annonce désormais ce qui sera produit

- [x] `ActesGeneratorService::actesPrevus(TypeActe, variantes)` **extrait** de `actesAProduire()`, qui
  s'appuie dessus. Toute la logique — rôle attendu × variante × `applicable_tous` — reste écrite **une
  seule fois** : la réécrire en JavaScript pour l'aperçu garantirait la divergence entre l'annonce et
  la production, exactement le défaut corrigé
- [x] Les rôles **attendus sans gabarit** sont retournés marqués, pas omis : c'est toute l'utilité de
  l'aperçu. Les types inconditionnels (page de garde) en sont exclus — ils ne sont produits que si un
  gabarit existe, les signaler serait du bruit
- [x] Point d'entrée `GET /types-actes/{typeActe}/actes-prevus?variantes[]=…`, autorisé par
  `create` sur `Dossier` — cet aperçu ne révèle rien de plus que l'écran suivant
- [x] `Create.jsx` : la liste d'actes affichée au **choix du type d'acte** disparaît (annoncer avant
  de connaître les résolutions est ce qui trompait) ; la carte du récapitulatif interroge le point
  d'entrée avec les variantes cochées et distingue **produit** / **attendu sans gabarit**, avec le
  lien vers la configuration
- [x] Vérifié sur la base : `SOC-MOD` + augmentation → DNSV (gabarit partagé), PV, statuts et RCCM
  signalés sans gabarit ; + cession et siège → l'acte de cession apparaît, **la DNSV disparaît**

#### Un nom partagé, signalé

- [x] Les noms portent leur forme d'origine (« RCCM SARLU »), et le nom est structurant — la
  génération dédoublonne dessus, « Régénérer » retrouve le gabarit par lui. Dès qu'un gabarit sert
  plusieurs procédures, la modale prévient que l'acte produit portera ce nom **partout**, et propose
  un intitulé neutre en un clic. **Suggestion, jamais imposition**

- [x] Vérifié sur la base réelle : 68 modèles et 70 rattachements inchangés, seeder relancé sans
  doublon, une constitution SARLU produit toujours ses **5 actes**, 18 fiches et les 4 écrans
  concernés en 200

### ⚙️ Configurer les actes par procédure, et non par type d'acte (2026-08-11)

Signalé à l'usage : « Aucun modèle actif pour ce type de dossier » alors que deux actes venaient
d'être produits. Derrière ce message, un problème de fond — une catégorie comme l'hypothèque est un
processus unique, la **société** en compte trois (création, modification, dissolution) et la
modification se décline en sept résolutions. Les actes se recoupent partiellement, et rien ne
permettait de le déclarer.

#### 🔴 Le bouton « Générer » était une réimplémentation dérivée du service

`DossierController::genererDocuments()` portait sa **propre copie** de la génération. Elle
interrogeait encore `where('type_acte_id', …)` — donc ignorait les modèles partagés —, n'appliquait
**aucun** filtre par modification décidée et ne connaissait pas les gabarits hérités. Deux chemins
pour le même métier, et toute règle ajoutée aurait été à écrire deux fois.

- [x] L'action **délègue** à `ActesGeneratorService::produireActes()` ; ~40 lignes supprimées
- [x] Le service retourne `crees` **et** `attendus` : les trois cas que le message confondait sont
  enfin distincts — aucun gabarit configuré (défaut de paramétrage, avec renvoi vers la vue
  Processus), tout est déjà présent, ou *n* produits
- [x] **Un test verrouille la fin du doublon** : le bouton et l'avancement d'étape doivent produire
  exactement le même résultat sur le même dossier

#### ⚙️ Rattachement type d'acte **+ variante**, générique

- [x] `App\Contracts\VarianteTypeActe` + `App\Support\VariantesTypeActe` — **le registre**, une seule
  table : `['SOC-MOD' => TypeModificationStatutaire::class]`. C'est là, et là seulement, qu'une future
  catégorie déclinée se déclare ; un type absent n'a pas de variantes et se comporte comme avant
- [x] Le pivot `modele_acte_type_acte` (créé la veille) devient **`modele_acte_rattachements`**, entité
  portant une `variante` nullable — `null` = toutes. Un `hasMany` plutôt qu'un `belongsToMany` : une
  colonne de pivot porteuse de sens se manipule mal en `sync()`, et le rattachement s'affiche et s'édite
- [x] **Pas d'éclatement en sept types d'actes** : contredirait la décision validée (« la variante est
  une donnée du dossier ») et casserait le multi-modifications — une assemblée décidant cession *et*
  transfert de siège doit tenir dans un seul dossier
- [x] `ModeleCourrier` **garde son pivot simple** : une lettre de transmission ne se décline pas par
  résolution. Asymétrie voulue, commentée pour qu'on ne la « corrige » pas

#### 🔤 Un gabarit remplit plusieurs rôles

Le vocabulaire diffère entre procédures pour un même document : les statuts sont `acte_principal` en
création et `statuts_maj` en modification, le RCCM `rccm` puis `declaration_rccm`. Un modèle ne portant
qu'un rôle, partager le gabarit ne suffisait pas — constaté la veille sur le RCCM.

- [x] Table `modele_acte_roles`, backfillée depuis `type_document`, qui **reste** le rôle principal
- [x] Le rôle retenu à la génération est **celui qu'attend la procédure**, pas le rôle principal :
  sans quoi un gabarit de statuts partagé aurait été rejeté par le filtre alors qu'il couvre le besoin

#### 📋 Documents attendus : éditables, mais traçables

Choix retenu : la table est **éditable depuis Paramètres**. Conséquence assumée — une saisie erronée
produirait un dossier incomplet au greffe. Le dispositif rend donc l'erreur visible et réversible :

- [x] Table `documents_attendus`, **seedée depuis l'enum** (`ReglesGestionDocumentsSeeder`, idempotent).
  L'enum reste la **référence datée** du CR de juillet 2026 ; la table en est la configuration effective
- [x] **Repli sur l'enum tant qu'aucune ligne n'existe** — garantie de zéro changement au déploiement,
  vérifiée par un test dédié
- [x] Badge **« modifié par rapport à la référence légale »** dès qu'une variante diverge du seed, et
  bouton **« Réinitialiser »** qui restaure la liste du CR. Chaque modification est journalisée
- [x] **Portée volontairement limitée** : seuls les types qui déclarent des attendus passent par la
  table. Créations, ventes, baux et hypothèques gardent « les modèles actifs du type d'acte » — leur
  inventer une liste reproduirait l'approche abandonnée pour la clôture le 2026-08-04

#### 👁️ La vue « Processus » — Paramètres > Types d'actes

- [x] `Components/Parametres/PanneauProcessus.jsx` : par type d'acte, ses variantes, leurs documents
  attendus, et pour chacun le gabarit qui le remplit ou son absence. Compteur d'incomplets en tête,
  et lien « rattacher » portant **type d'acte + variante + rôle** — l'admin n'a plus à deviner le slug,
  ce qui était le blocage réel
- [x] Modale *Modèles d'actes* : cases de variantes (celles des types cochés seulement) et cases de
  rôles. ⚠️ Composition des rattachements via `transform()` d'Inertia et non `setData()`, asynchrone :
  la valeur serait partie périmée
- [x] Colonne morte **`types_actes.actes_requis`** supprimée — jamais lue, vide sur les 30 types,
  vestige de l'approche abandonnée. La laisser invitait à la recâbler en concurrence de la nouvelle table

- [x] `tests/Feature/ConfigurationActesTest.php` — 22 tests : variantes (couverture, restriction,
  `applicable_tous`), rôles multiples et rôle non déclaré, repli sur l'enum, primauté de la
  configuration, détection d'écart, réinitialisation, variante inconnue refusée, égalité bouton /
  avancement, les trois messages, et trois non-régressions
- [x] Vérifié sur la base réelle : **0 modèle délié** sur 68 après reprise du pivot, la DNSV partagée
  la veille conserve ses 2 rattachements, `/parametres/types-actes`, `/modeles`, `/dossiers/create` et
  `/ged` en 200, **18 fiches en 200**. Suite complète : 318/326, les 8 échecs préexistants inchangés

### 🏭 Produire les actes d'une modification (2026-08-11)

Signalé à l'usage : le panneau annonçait 4 actes attendus sur `SOC-2026-0013`, l'onglet Actes
affichait « Aucun document ». **Quatre causes distinctes**, toutes vérifiées en base.

#### 🔴 Un modèle ne servait qu'à un seul type d'acte

`ModeleActe::where('type_acte_id', …)` : les statuts, la DNSV, l'attestation, la déclaration et le
RCCM chargés pour `SOC-SARLU` — fichiers présents et actifs — étaient **invisibles** depuis un
dossier de modification. L'étude devait recharger les mêmes fichiers sous un autre type puis
maintenir deux copies. **Les courriers savaient déjà faire autrement** depuis le 2026-07-10 :
`modele_courrier_type_acte` + `applicable_tous`.

- [x] Pivot `modele_acte_type_acte` + colonne `applicable_tous`, **calqués sur ceux des courriers** —
  inventer un second mécanisme aurait garanti la divergence. `typesActes()`, `applicablePour()` et
  `scopePourTypeActe()` sont des copies de `ModeleCourrier`
- [x] **Backfill** : une ligne de pivot par modèle depuis son `type_acte_id`. Vérifié — 68 lignes
  pour 68 modèles, **0 mal rattaché** : aucun comportement ne change tant que personne n'ajoute de type
- [x] `type_acte_id` **conservée** comme « type d'origine » (affichage, groupage, seeders), tenue en
  synchronisation avec le premier type coché. C'est le **pivot** qui décide de l'applicabilité —
  documenté aux deux endroits, sinon la double vérité devient un piège
- [x] Écran *Modèles d'actes* : cases à cocher par catégorie + « applicable à tous », comme les
  courriers. Vérifié sur la base : cocher « Modification de statuts » sur la DNSV de la SARLU la rend
  produisible sur `SOC-2026-0013` — **aucun fichier rechargé**

#### 🔴 Charger un modèle de modification était impossible

Les 4 slugs de la modification (`acte_cession`, `pv_modification`, `statuts_maj`,
`declaration_rccm`) avaient été ajoutés au seeder mais **oubliés dans la liste blanche** de
validation. `store()` et `update()` répondaient donc **422** : l'étude ne pouvait pas fournir son
gabarit de procès-verbal.

- [x] La liste était écrite **en dur trois fois** (deux règles + le `match` des libellés) — c'est ce
  qui a laissé passer l'oubli. Extraite en `HasTypeDocumentLabel::TYPES_DOCUMENT`, **seule
  référence**, avec `reglesTypeDocument()` et `typesDocumentOptions()`. Appliqué aussi aux courriers

#### ✨ Les statuts en vigueur de la société comme gabarit

Le dossier de constitution contient les **vrais statuts `.docx`** de la société. Bien meilleur point
de départ qu'un gabarit générique, dont ni les articles ni la numérotation ne correspondraient.

- [x] `Societe::gabaritStatutsEnVigueur()` — trois sources, **du plus récent au plus ancien** :
  pièce `statuts` déposée au registre → `statuts_maj` de la dernière modification **devenue
  effective** → `acte_principal` du dossier de constitution
- [x] La source 2 n'est pas une élégance : après une première modification, les statuts en vigueur
  sont ceux qu'elle a produits. Restreinte aux dossiers en **Expédition ou Clôture** — seuil où
  `SocieteMutationService` considère déjà la modification opposable, donc un dossier abandonné ne
  devient pas la référence des suivants
- [x] `origine` est **journalisée** (« produit depuis les statuts du dossier de constitution
  SOC-2026-0008 ») : le rédacteur doit savoir de quel texte il part
- [x] Vérifié sur la base : « Statuts mis à jour » est produit sur `SOC-2026-0013` **sans qu'aucun
  fichier ne soit ajouté**

#### 🔴 Cul-de-sac à l'Édition, et un panneau qui promettait l'impossible

L'étape exige au moins un acte, n'offrait que « Générer depuis les modèles », et **aucun dépôt manuel
n'existait côté interface** — alors que `DocumentController::store` et sa route étaient là depuis
toujours. Sans modèle actif, le dossier était bloqué sans recours. Même forme que le blocage du dépôt
des pièces corrigé le même jour.

- [x] Bouton **« Déposer un acte »** + modale (nom, catégorie, fichier facultatif) dans l'onglet Actes
- [x] `modificationStatutaire()` expose `documents` avec un booléen **`disponible`** par acte attendu :
  un modèle applicable et actif existe, **ou** un gabarit hérité est disponible. Le panneau marque les
  autres « modèle manquant », avec un lien vers *Modèles d'actes*. Annoncer un document que rien ne
  peut produire est un mensonge d'interface — c'est ce qui rendait la situation incompréhensible

#### 🐛 Corrigé au passage

- [x] **`Array to string conversion` à chaque génération.** Un item de bloc répétable porte la fiche
  client complète sous la clé `client` (déposée par le sélecteur, consommée par
  `buildPartiesPayload`) : la caster en chaîne émettait un avertissement PHP. Une liste de libellés
  est désormais rendue en énumération, un objet est ignoré — ses champs sont déjà projetés un par un
- [x] **`SOC-2026-0013` avait perdu son `societe_id`** — victime du brouillon qui ne conservait pas
  `societeLink` (corrigé la veille). `ayelema:societes-backfill` sait déjà rattacher un dossier de
  modification à une fiche existante par dénomination normalisée : 13 dossiers rattachés, 0 fiche créée
- [x] `tests/Feature/ActesModificationTest.php` — 17 tests : les trois sources de statuts et leur
  ordre de préséance, une modification non effective ignorée, l'origine journalisée, le partage de
  modèles dans les deux sens, `applicable_tous`, les 4 slugs autorisés, un modèle créé sans types
  rattaché à son type d'origine, la disponibilité par acte, le dépôt manuel satisfaisant le prérequis,
  et la non-régression d'une constitution

**Reste à fournir par l'étude** : le gabarit `.docx` du **procès-verbal d'assemblée** (son entrée
existe, inactive) et celui de la **déclaration de modification RCCM** — le formulaire du greffe diffère
de celui d'une constitution, le modèle `rccm` de la SARLU ne peut donc pas être simplement partagé
(slug `declaration_rccm` ≠ `rccm`).

### 🔄 L'Initialisation d'une modification n'est pas celle d'une constitution (2026-08-11)

Signalé à l'usage sur `SOC-2026-0013` : « est-ce normal que tu me demandes l'accord du client et les
pièces des personnes, alors que le client existait déjà ? » — non. L'étape Initialisation a été
conçue pour une **constitution**, et `SOC-MOD` la traversait à l'identique :
`DossierStepService::erreursDeConstitution()` — le nom le dit — n'a jamais regardé le type de dossier.

#### 🔴 Blocage complet : les pièces exigées n'étaient pas téléversables

Le pire défaut, découvert en écrivant les tests. Le dépôt d'une pièce de personne passait par
`genererDocuments`, qui **exclut l'Initialisation** (Édition + Certification seules). Or c'est
précisément l'étape qui **exige ces pièces pour être franchie**. Vérifié sur `SOC-2026-0013` :
`genererDocuments` = **non**, alors que trois pièces y étaient réclamées — et comme l'interface règle
ses boutons sur cette même capacité, **aucun bouton de téléversement ne s'affichait**. Le dossier
demandait des pièces que personne ne pouvait déposer.

- [x] **Nouvelle ability `gererPieces`** — Initialisation **et** Édition. Déposer un justificatif
  n'est pas produire un acte : les deux n'avaient aucune raison d'être confondues. L'Édition reste
  couverte, une pièce pouvant manquer et être ajoutée après un renvoi en correction
- [x] Les 5 actions de `PartieController` et les 4 points d'appui du frontend (avatar, checklist,
  reprise, pièces libres) y passent. **Non-régression verrouillée** : le dépôt reste refusé à partir
  des Formalités — ajouter une pièce après la certification changerait un dossier validé

#### 🔴 Des pièces déjà détenues par l'étude étaient redemandées

`SOC-2026-0013` réclamait CNI, certificat de résidence et 2ᵉ photo à Thierno DIALLO. Le **même
`client_id = 14`** les avait déjà toutes fournies dans `SOC-2026-0011`. Une pièce d'identité est un
attribut de la **personne**, pas du dossier — et la fiche client est justement la source de vérité de
l'identité ([décision #33](#8-décisions-techniques)).

- [x] `Partie::piecesReprenables()` — rapprochement sur **`client_id` exclusivement** : deux
  homonymes ne sont pas la même personne, et une partie sans fiche client ne propose rien. La plus
  **récente** l'emporte, sans quoi on proposerait de reprendre une pièce périmée
- [x] La **date** accompagne chaque proposition (« déjà fournie dans SOC-2026-0011 le 06/08/2026 ») :
  une pièce d'identité a une durée de validité, et reprendre un scan ancien sans le voir serait pire
  que de le redemander
- [x] `Partie::reprendrePiece()` — **copie et non référence**. Un dossier notarial doit être
  physiquement complet : c'est ce que l'inventaire de clôture atteste et ce que l'archive conserve.
  ⚠️ Deux `DocumentFichier` sur le même chemin auraient été un piège — `supprimerAvecFichiers()`
  aurait effacé le fichier sous les pieds de l'autre dossier, et `nouvelleVersion()` accepte
  justement une chaîne, ce qui rendait l'erreur facile. **Un test le verrouille** : supprimer
  l'original laisse la copie lisible
- [x] Version tracée `source = 'reprise'`, aux côtés de `upload`, `genere`, `restauration` et
  `signe_cachete` — l'historique doit dire d'où vient chaque fichier
- [x] `source_id` **vérifié contre `piecesReprenables()`** : sans ce contrôle, la route permettrait
  de copier n'importe quelle pièce de n'importe quel dossier vers le sien
- [x] Front : « Reprendre » sur la ligne, et **« Reprendre les 3 pièces »** en tête de la personne —
  une personne déjà connue de l'étude a rarement une seule pièce à reprendre

#### 🟠 L'accord attendu dépend désormais du type de dossier

« Imprimez la fiche dossier, faites-la signer » n'a pas de sens sur une modification : ce qui engage
l'opération est la **décision des associés**, et le PV que l'étude rédige n'arrive qu'à l'Édition —
trop tard pour garder l'Initialisation.

- [x] `Dossier::pieceAccordAttendue()` → `{ categorie, nom, titre, instructions, imprimable }`.
  Pour `SOC-MOD` : « Décision d'assemblée des associés », **sans** bouton d'impression, avec la
  consigne correspondante
- [x] **La `categorie` reste `accord_client`** dans les deux cas, délibérément : c'est le *créneau
  technique* de « la pièce écrite qui atteste l'accord et conditionne l'Initialisation ». En
  introduire une seconde obligerait à la classer dans `RubriqueCloture::pourDocument()` (elle
  tomberait sinon dans **Actes**, alors qu'elle est fournie et non produite), à l'exclure de l'onglet
  Actes et à doubler le contrôle bloquant. C'est le **nom** du document qui porte le sens
- [x] Consommé par `televerserAccordClient()`, `AccordClientCard`, `getStepBlockers()` **et**
  `erreursDeConstitution()` — un appel direct à l'API doit dire la même chose que l'écran
- [x] Libellés : « chaque associé/gérant » devient la liste des **rôles réellement présents**
  (cédant, cessionnaire, souscripteur, gérant entrant), dérivée des parties du dossier

**Ce qui reste inchangé, et pourquoi** — vérifié, à ne pas « corriger » par symétrie : objet, notaire
et certificateur valent pour tout dossier ; les règles de constitution **ne se déclenchaient déjà
pas** sur `SOC-MOD` (`depuisCodeTypeActe()` retourne `null`) ; `gerant_sortant`, `president_seance` et
`secretaire_seance` n'exigent déjà aucune pièce ; le dossier constitutif d'une société hors registre
reste bloquant.

- [x] `tests/Feature/PiecesReprisesTest.php` — 16 tests, dont le dépôt possible dès l'Initialisation
  (avec la prémisse du bug assertée), la copie qui survit à la suppression de l'original, le refus
  d'un `source_id` étranger, et trois non-régressions (dépôt refusé après certification, constitution
  gardant sa fiche signée, « reprendre tout » n'écrasant pas une pièce déjà scannée ici)
- [x] Vérifié sur la base réelle : les 3 pièces de Thierno DIALLO sont proposées à la reprise,
  `gererPieces` est accordé, l'accord attendu est bien la décision d'assemblée, `SOC-2026-0002`
  (constitution) garde le sien, et les **18 fiches répondent en 200**

### 📎 Dossier constitutif d'une société hors registre (2026-08-11)

Le cas resté ouvert de la refonte `SOC-MOD` : l'étude modifie aussi des sociétés **qu'elle n'a pas
constituées**. Leur fiche est alors créée à la main, et il n'existe **rien** en base — ni dossier
d'origine, ni statuts, ni RCCM. Le dossier de modification partait donc de zéro documentaire, et
« Statuts mis à jour » aurait été produit depuis un gabarit Ayelema générique dont ni les articles
ni la numérotation ne correspondent aux statuts réels de la société.

**Décisions** : les statuts déposés servent de **gabarit** aux statuts mis à jour · statuts et RCCM
**bloquants**, le reste facultatif · dépôt possible **dans l'assistant et dans la fiche dossier** ·
les pièces entrent dans l'**inventaire de clôture**, dans une rubrique dédiée.

#### 🚧 Un bloqueur levé avant tout le reste

`cloture_verifications` portait `unique(verifiable_type, verifiable_id)` — « une pièce n'est
vérifiée qu'une fois ». Prémisse juste tant qu'une pièce n'appartenait qu'à **un** dossier. Les
pièces d'une société sont partagées par **tous** ses dossiers, alors que
`InventaireClotureService::verificationsIndexees()` filtre déjà `where('dossier_id', …)` : la
vérification est donc conçue **par dossier**. Sans correctif, les statuts vérifiés lors de la 1ʳᵉ
modification seraient réapparus non vérifiés à la 2ᵈᵉ, et cocher aurait violé l'index — **clôture
impossible sur une erreur SQL**.

- [x] Migration `corriger_unicite_cloture_verifications` — l'index devient
  `(dossier_id, verifiable_type, verifiable_id)`. C'est le second sens que la colonne `dossier_id`,
  dénormalisée dès l'origine, appelait déjà. Aucune donnée à migrer, la contrainte est plus permissive

#### Les pièces, au registre et non au dossier

- [x] **`Societe` devient le 4ᵉ `documentable`** de `DocumentFichier`, après Dossier, Formalite et
  Partie — et le seul qui **n'appartienne pas à un dossier**. Les statuts d'origine ne sont pas
  produits par le workflow : ils sont le dossier propre de la société, et une seconde modification
  deux ans plus tard doit les retrouver sans les redemander
- [x] `Societe::PIECES_CONSTITUTIVES` — 7 pièces, dont **2 requises** : statuts en vigueur (on ne
  peut pas produire des statuts mis à jour sans les avoir vus) et extrait RCCM (identité légale
  reprise dans tous les actes). Facultatives : NIF, actes modificatifs antérieurs, attestation de
  dépôt du capital, insertion JAL, DNSV d'origine
- [x] **`exigePiecesConstitutives()` est le pivot** : `dossier_id === null`. Une société que l'étude
  a constituée n'a rien à fournir — son dossier d'origine fait foi, et la checklist ne s'affiche pas
  du tout. Vérifié : les **11 fiches issues du backfill en sont toutes dispensées**, aucun dossier
  existant ne se voit réclamer quoi que ce soit
- [x] `SocietePieceController` — calqué sur `PartieController::televerserPieceRequise()`,
  `firstOrCreate` par catégorie compris : re-téléverser crée une **version 2** sans dupliquer la
  ligne. Autorisé sur `update` de la **Societe** et non `genererDocuments` d'un dossier : ces pièces
  relèvent du registre, dont la tenue est réservée aux rôles pouvant ouvrir un dossier — un
  formaliste ne verse pas les statuts d'une société
- [x] `DocumentFichier::sujetAutorisation()` — `dossierGouvernant()` vaut `null` pour ces pièces
  (aucun dossier ne les gouverne), et les 4 méthodes de **lecture** de `DocumentController`
  autorisaient dessus : aperçu et téléchargement auraient échoué sur un `authorize(..., null)`
  illisible. `DossierPolicy::view` et `SocietePolicy::view` existant tous deux, aucune branche n'a
  été nécessaire. Les 5 méthodes **mutantes** refusent explicitement une pièce de registre
  (`estPieceDeRegistre()`) et renvoient vers la société

#### Les statuts déposés deviennent le gabarit

- [x] `ActesGeneratorService::gabaritPour()` — **la décision, en un seul endroit**, consommée par
  `genererActesDepuisModeles()` **et** `regenererDocument()`. Sans ce partage, « Régénérer »
  repartirait du modèle de l'étude et écraserait des statuts reconstruits. Même raison qui avait
  fait extraire `Bareme::estApplicableA()`
- [x] `actesAProduire()` ajoute une **seconde passe** : les types à gabarit héritable sont produits
  même sans modèle actif. Indispensable — le modèle `statuts_maj` est seedé **inactif** faute de
  gabarit fourni à l'étude, donc « Statuts mis à jour » ne serait jamais apparu au dossier alors que
  les statuts déposés permettent parfaitement de le produire
- [x] **Trois garde-fous.** ① `.docx` seulement (`TemplateProcessor` dézippe un OOXML) — le dépôt
  reste ouvert au PDF, c'est souvent ce que le client apporte, mais on retombe alors sur le modèle.
  ② Un fichier **sans balise** produit une **copie fidèle** des statuts d'origine : c'est le
  résultat voulu, bien supérieur à un gabarit dont la numérotation d'articles ne correspond à rien —
  mais le journal le dit, sans quoi le rédacteur croirait les modifications déjà reportées.
  ③ Toute erreur de lecture retombe sur un document vierge plutôt que d'interrompre l'entrée en
  Édition, qui produit aussi tous les autres actes

#### Clôture, règles, interface

- [x] `RubriqueCloture::PiecesSociete` — son `match` sur `documentable_type` est exhaustif et son
  commentaire annonçait « ajouter un quatrième documentable lèvera une erreur ici » : c'est
  exactement ce garde-fou qui s'est déclenché. Rangée **après les pièces des parties** (ce sont, comme
  elles, des pièces *fournies* et non produites), d'où la renumérotation de `ordre()` à 7 rubriques
- [x] **Conséquence assumée** : ces pièces sont à cocher dans **chaque** dossier de la société. La
  vérification étant par dossier, chaque clôture atteste que *ce* dossier a été contrôlé sur *ces*
  pièces — c'est ce que la migration du bloqueur rend possible
- [x] `ReglesSocieteService::verifierPiecesConstitutives()` — ne s'applique qu'aux sociétés externes.
  Portée volontairement limitée à `SOC-MOD` : `SOC-DIS` a le même besoin, mais la dissolution n'a pas
  encore été reprise et l'étendre sans l'examiner bloquerait des dossiers sur une règle non validée
- [x] `PiecesConstitutivesCard` — **un composant, deux emplacements** (assistant et fiche dossier).
  ⚠️ Téléversement en **axios** et non par visite Inertia : dans l'assistant, une visite rechargerait
  `/dossiers/create` et perdrait toute la saisie. Le rafraîchissement est délégué à l'appelant —
  `router.reload({ only: ['societe'] })` dans la fiche, un rappel de `/societes/{id}` dans
  l'assistant. Aucun mécanisme de *staging* : la fiche société existe déjà en base au moment du
  rattachement, et un dossier abandonné ne laisse pas d'orphelins puisque les fichiers appartiennent
  à la société
- [x] La carte annonce **avant la génération** si les statuts serviront de gabarit (`.docx`) ou non
  (PDF) — le clerc ne doit pas découvrir le repli en ouvrant l'acte produit. Pour une société
  constituée par l'étude, pas de checklist mais un lien vers son dossier d'origine
- [x] `tests/Feature/PiecesConstitutivesSocieteTest.php` — 26 tests, dont la vérification de la
  **même pièce dans deux dossiers** (le bloqueur), le gabarit `.docx` / PDF / absent, l'égalité de
  décision entre génération initiale et régénération, et trois non-régressions : inventaire d'un
  dossier sans société, classement des trois rattachements historiques, société constituée par
  l'étude jamais bloquée
- [x] Vérifié sur la base réelle : **17 fiches en 200**, `/dossiers/create` et `/ged` en 200,
  anomalies des 12 dossiers de société **inchangées**

### 🧭 Assistant de création — trois défauts signalés à l'usage (2026-08-12)

#### 🔴 « Suivant » se grisait sans dire pourquoi

Le défaut le plus coûteux : `canNext()` agrégeait **quatre familles de blocages hétérogènes** en un
seul `&&` — champs requis vides, blocs répétables sous leur minimum, objet de moins de 10 caractères,
notaire non choisi — et n'en restituait aucune. Sur un questionnaire de modification, jusqu'à
**10 sections et une trentaine de champs**, trouver le coupable à l'œil était intenable.

- [x] **`resources/js/lib/blocantsEtape.js`** — `canNext()` (booléen) devient `blocantsEtape()`
  (**tableau** de `{cle, section, label, raison, ancre}`). Même retournement que `getStepBlockers()`
  avait opéré pour le workflow du dossier : l'assistant parle enfin le même langage que le panneau
  « conditions requises » de la fiche
- [x] **Le bouton n'est plus jamais `disabled`** (décision validée) : cliquer avec des manques passe
  les champs fautifs en rouge, déroule la liste et **fait défiler jusqu'au premier**
  (`allerAuBlocant`, focus différé de 350 ms pour ne pas casser l'animation). Cliquer sans manque
  avance. Le bouton devient l'outil qui guide au lieu d'une porte fermée sans écriteau
- [x] Compteur permanent dans la barre — « ⚠ 3 à compléter » / « ✓ Prêt à continuer » — cliquable
  pour dérouler la liste groupée par section, chaque ligne menant au champ concerné
- [x] **Badge par section** dans l'en-tête de chaque carte (`BadgeSection`) : le nombre de manques, ou
  une coche verte. La coche n'apparaît que si la section porte au moins un champ obligatoire — sur une
  section entièrement facultative elle ne voudrait rien dire
- [x] **Rouge après tentative seulement** (`validationTentee`), et effacé **dès la saisie** : un
  formulaire vierge intégralement rouge est une accusation sans faute. Appliqué par variantes
  descendantes (`[&_input]:border-danger`) plutôt qu'en ajoutant une prop aux dix types de champ
- [x] **Deux règles rendues visibles.** L'objet gagne un compteur vivant (« 10 caractères minimum —
  4 saisis ») : le placeholder qui énonçait la règle disparaissait à la première frappe, donc au
  moment précis où elle commençait à compter. Et l'étape 0 (catégorie) produit désormais une ligne
  explicite au lieu de se griser en silence elle aussi
- [x] **Le blocage vraiment invisible, enfin nommé** : un champ requis **masqué** parce qu'une fiche
  liée le porte (`champsAffichables`) restait obligatoire sans être à l'écran — le bouton se grisait
  sans qu'aucun champ visible ne soit en défaut. `estMasque` le détecte, la raison devient « Absent
  de la fiche liée — complétez la fiche, ou détachez-la pour saisir ici », et l'ancre renvoie vers la
  **carte de section** (`ancreSection`) puisque le champ n'existe pas dans le DOM. Le cas valait pour
  les fiches client, il valait désormais aussi pour la fiche société
- [x] ⚠️ `blocantsEtape()` passe par **`groupFieldsBySection`** et ne lit pas `field.section`
  directement : dans `questionnaires.js`, **seul le premier champ d'une section porte `section`**, les
  suivants l'héritent. Lire l'attribut brut aurait rangé la quasi-totalité des champs sous une section
  fantôme, et les badges des cartes — calés sur `group.name` — n'auraient correspondu à rien

#### 🔴 Un champ obligatoire que la fiche ne renseigne pas était impossible à saisir

Découvert en testant le correctif précédent, et plus grave que lui : `champsSocieteAffichables`
masquait **tous** les champs `soc.*` dès qu'une société était rattachée, sans regarder si la fiche
les renseignait. Or `MICH SARL` est au registre **sans numéro RCCM**, champ pourtant obligatoire à
l'acte. Il était donc masqué, obligatoire, et le seul recours affiché — « complétez la fiche, ou
détachez-la » — revenait à perdre tout le préremplissage pour une donnée manquante. Impasse complète.

C'est aussi ce qui expliquait l'incohérence signalée entre les deux parcours : le brouillon perdait
`societeLink` (défaut ci-dessous), donc les champs y réapparaissaient — la seule voie par laquelle le
RCCM était saisissable était… un bug.

- [x] **`ficheRenseigneChamp(societe, fieldId)`** — « porté par la fiche » et « renseigné dans la
  fiche » cessent d'être confondus. Un champ que la fiche ne renseigne pas **reste saisissable**.
  Vérifié sur `MICH SARL` : dénomination, sigle, forme, capital, siège et objet restent masqués ;
  RCCM, NIF et date de constitution redeviennent saisissables
- [x] **`Societe::completerDepuisQuestionnaire()`** — la contrepartie indispensable : la saisie
  complète la fiche au registre. Sans elle, la valeur ne vivrait que dans le questionnaire du
  dossier, le dossier suivant la redemanderait et le registre resterait indéfiniment incomplet
- [x] **Jamais d'écrasement** : une valeur déjà au registre est la vérité de référence. La corriger
  relève d'une modification statutaire — `SocieteMutationService`, à l'entrée en Expédition — pas d'un
  formulaire de création. Journalisé sur le dossier : une fiche de référence qui change sans trace
  n'est pas acceptable, même exigence que pour une fiche client modifiée depuis le Répertoire
- [x] `enregistrerAuRegistreDesSocietes()` ne sort plus dès que `societe_id` est posé : ce cas — une
  modification ou une dissolution — passe désormais par l'enrichissement
- [x] Mention sous les champs concernés : « Absent de la fiche du registre — votre saisie la
  complétera ». Le clerc doit comprendre pourquoi ce champ-là est visible quand les autres ne le sont
  pas, et que sa saisie sert au-delà de ce dossier
- [x] 4 tests : complétion d'une colonne vide, non-écrasement d'une colonne renseignée, valeur vide
  ignorée, et le parcours complet `POST /dossiers` → fiche complétée + journal

#### 🟠 Reprendre un brouillon perdait la société sélectionnée

`serialiserEtat()` est une **liste blanche explicite**, et `societeLink` n'y figurait pas — alors que
l'assistant le transmettait (avec un commentaire expliquant pourquoi il est indispensable) et le
relisait bien au retour. La liste blanche le jetait entre les deux. D'où le symptôme : `formValues`
étant persisté, les champs `soc.*` revenaient remplis, mais le rattachement au registre était perdu,
donc `societe_id` partait vide et le dossier naissait délié.

- [x] `societeLink` ajouté à la liste blanche
- [x] **`verifierCouvertureEtat()`** — garde-fou de développement : compare les clés transmises à
  celles réellement persistées et avertit en console pour toute clé qui serait perdue. La liste
  blanche reste le bon choix (elle évite de persister `submitting`, l'ouverture des modales…), mais
  rien ne signalait l'omission. Avertissement et non erreur : un brouillon incomplet vaut mieux qu'un
  enregistrement refusé. Silencieux en production, où il n'aurait aucun lecteur
- [x] Vérifié sur la base réelle : aller-retour d'un brouillon portant `societeLink` — l'identifiant
  de la société est bien relu

#### 🟠 Le gérant restait à saisir alors que la société était choisie

`soc.gerant_actuel` n'était dans **aucune** des deux tables de projection (`CHAMPS_QUESTIONNAIRE` en
PHP, `CHAMPS` dans `societeFields.js`) : ce qu'on voyait en gris était le *placeholder*. Le nom était
pourtant affiché juste au-dessus, dans « Personnes connues de cette société ».

- [x] **`Societe::gerantActuel()`** — deux sources : `direction['gerant']` (autoritaire, écrite par
  `SocieteMutationService` à chaque changement de gérance porté au registre), puis repli sur les
  parties du dossier de constitution. ⚠️ Le repli n'est pas un raffinement : **`direction` est nulle
  sur les 11 fiches** issues du backfill, qui ne la peuplait pas
- [x] **`ROLES_DIRECTION` couvre les 9 formes**, dont le dirigeant ne porte pas le même titre :
  `gerant` (SARL/SARLU/SNC/SCS), `president` (SAS/SASU), `pca` puis `dg` (SA), `administrateur` (GIE),
  et `associe_unique` **en dernier** — c'est une déduction, pas un rôle déclaré. L'ordre compte : une
  SA doit rendre son PCA, représentant légal, et non son directeur général. Requête propre plutôt que
  passage par `associesConnus()`, qui ne retient que associe/associe_unique/gerant et laissait une
  SAS sans dirigeant (constaté sur la base réelle : `Guinée Tech Innovation SAS`)
- [x] Projeté dans `versQuestionnaire()` **hors** `CHAMPS_QUESTIONNAIRE` (correspondance
  clé↔colonne un-pour-un, or le dirigeant est calculé) — donc la balise `${soc.gerant_actuel}` du PV
  et des statuts mis à jour est enfin alimentée
- [x] Côté front : prérempli **et modifiable**, hors de `CHAMPS` de `societeFields.js` pour ne pas
  être masqué. L'information peut être périmée, ou inconnue pour une société hors registre
- [x] Vérifié sur la base réelle : **11 sociétés sur 11** ont désormais un dirigeant résolu (0 avant)
- [x] 8 tests dans `RegistreSocietesTest` — un par règle : ordre des rôles, associé unique d'une
  SARLU, président d'une SAS, PCA prioritaire sur le DG, `direction` prioritaire sur le dossier
  d'origine, société hors registre à `null`, projection, exposition par l'API

### ⚙️ Changement de workflow (2026-07-03)

- [x] `EtapeDossier` réduit de 8 à 6 cas : `Signature client` et `Signature notaire` supprimées
- [x] Migration `migrate_signature_etapes_to_formalites` — bascule les dossiers en signature vers `formalites` (irréversible, pas de `down()`)
- [x] `DossierStepService::verifierPrerequis()` adapté aux 6 étapes

---

### 🔲 Reste à faire

#### Module 4 — Génération de documents (suite)
- [ ] **Normaliser les 63 modèles `.docx`/`.doc` bruts** dans `Documents reçus/` (pointillés/MAJUSCULES → balises `${...}`) — un seul fichier fait référence (`STATUTS_SARLU_balises.docx`). Voir `Analyse_et_Prompt_Generation_Modeles_Ayelema.md` §A/§E pour la méthode et le dictionnaire de blocs cible
- [ ] Ajouter les blocs `cr.*` (Courrier) et `fac.*` (Facture) dans `resources/js/data/questionnaires.js` — documentés dans l'analyse mais absents du frontend, donc pas de formulaire de saisie pour les 13 courriers de transmission et les factures détaillées
- [ ] Étendre `ActesGeneratorService` avec `cloneRowAndSetValues()` pour les tableaux répétables (lignes de facture, titres fonciers multiples, pièces transmises) — seul `cloneBlock()` simple est utilisé actuellement
- [x] ~~Prévisualisation PDF dans le navigateur~~ — **ligne obsolète, corrigée le 2026-07-24** : l'aperçu `.docx`/`.xlsx` est déjà implémenté côté client (`docx-preview` + `xlsx`, sans conversion serveur) dans `DocumentPreviewModal.jsx`/`DocumentInlinePreview.jsx`. Aucune conversion PDF serveur nécessaire (voir [décision #30](#8-décisions-techniques))
- [ ] Gestion des versions de **modèles** (`ModeleActe` — historique, rollback du gabarit lui-même). À ne pas confondre avec le versionnage des documents d'un dossier, **déjà implémenté** depuis le module GED unifié (§ ci-dessous)
- [ ] Signature électronique intégrée (module futur)

#### Module 5 — Grilles de révision dynamiques
- [ ] Interface admin pour configurer les grilles par type d'acte (table `revision_grilles`) — routes `parametres/grilles` déjà présentes dans `web.php`, à vérifier si l'UI existe
- [ ] La page `Revision.jsx` utilise `DEFAULT_GROUPES` si aucune grille en DB — à terme : grilles personnalisées par type d'acte
- [x] ~~Notification au rédacteur en cas de renvoi en correction~~ — fait le 2026-08-03 (`DossierRenvoyeNotification`, déclenchée depuis `DossierStepService::reculer()`, motif inclus)

#### Module 6 — Barèmes & formalités avancées (suite)
- [ ] Unifier `Formalite::calculerMontant()` et `Bareme::calculerMontant()` (deux mécanismes de calcul de montant coexistent aujourd'hui)
- [ ] Génération des bordereaux de paiement

#### Fonctionnalités transverses
- [x] ~~Upload pièces jointes (CNI, photos parties)~~ — fait le 2026-07-24 via le module GED unifié (`PartieController::uploaderPhoto/uploaderPiece`)
- [ ] Export PDF d'un dossier
- [ ] CRUD HTTP pour `BienImmobilier` et `Banque` (alimentés seulement via le questionnaire, sans écran dédié ni seeder). `Client` (2026-08-03) et `Societe` (2026-08-11) sont couverts — reste à ajouter un écran d'édition dans `Repertoire/Index.jsx`, qui n'expose encore que la consultation alors que `ModalNouveauClient` sait modifier une fiche, et à y exposer le **registre des sociétés**, aujourd'hui atteignable seulement depuis l'assistant de dossier
- [ ] Page `Parametres/Apparence` — routes présentes dans `web.php` (`GET/POST /parametres/apparence`, upload logo), à vérifier si l'UI React existe et est branchée à `Setting`

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
users — id, name, email, password, role(enum), initiales, telephone, avatar, actif,
        notifications_email(bool, défaut true), timestamps

dossiers — id, reference(unique), type_acte_id, etape(enum), redacteur_id, reviseur_id,
           notaire_id, formaliste_id, objet, valeur, echeance, notes,
           etape_changed_at, deleted_at, timestamps

types_actes — id, code, label, categorie(enum), prefixe_reference, delai_jours,
              description, actes_requis(json), fiche_modification_obligatoire,
              actif, ordre, timestamps

questionnaires — id, dossier_id, donnees(json), timestamps

-- table `documents` supprimée le 2026-07-24 (module GED unifié, voir plus bas)

revision_grilles — id, type_acte_id, points(json), version, est_active, timestamps

revisions — id, dossier_id, reviseur_id, statut(enum), commentaire,
            valide_at, renvoye_at, timestamps

revision_points — id, revision_id, point_id(string), etat(string), commentaire, timestamps

-- statut : a_deposer | depose | en_attente | retour_recu | rejete
-- `retour_recu` est l'état TERMINAL (le statut `cloture` a été supprimé) — voir
-- StatutFormalite::estTerminee() et décision #35.
formalites — id, dossier_id, organisme, statut(enum), taux, montant_base,
             montant_calcule, type_impot, retour_attendu, delai_heures,
             depose_at, retour_at, echeance_at, timestamps

-- table `formalite_pieces` supprimée le 2026-07-24 (module GED unifié, voir plus bas)

parties — id, dossier_id, nom, role, type_personne, cni, telephone, adresse, email, client_id,
          donnees_prefixe, donnees_bloc, donnees_index, timestamps
          -- (2026-08-03) les 3 colonnes donnees_* décrivent OÙ projeter l'identité de la
          -- fiche client dans questionnaires.donnees : préfixe pour une section scalaire
          -- ('pp' → pp.prenom_nom…), ou bloc + index pour un item répétable
          -- ('associes'[1]). Renseignées par le frontend, seul détenteur du schéma des
          -- questionnaires (JS) — voir décision #33 et ClientProjectionService.
          -- photo_chemin et pieces(json) supprimées le 2026-07-24 (jamais alimentées,
          -- remplacées par la relation polymorphe Partie::pieces() — voir module GED)

journal_activites — id, dossier_id, user_id, action, type, meta(json), created_at

modeles_actes — id, type_acte_id, nom, chemin_fichier, version, est_actif, updated_by, timestamps

cloture_verifications — id, dossier_id, verifiable_type, verifiable_id (morph :
                        DocumentFichier|Courrier|Recu), verifie_par_id, verifie_at, timestamps
                        -- (2026-08-04) contrôle d'une pièce avant clôture. Table polymorphe
                        -- plutôt que des colonnes sur trois tables — voir décision #36.
                        -- unique(verifiable_type, verifiable_id) : la coche est un état,
                        -- pas un journal. Dévérifier = supprimer la ligne.

notifications — (table standard Laravel notifications)

dossier_brouillons — id, user_id, type_acte_id(nullable), libelle, etat(json), timestamps
                     -- (2026-08-03) saisie inachevée de l'assistant de création. `etat` porte
                     -- l'étape courante, les valeurs du questionnaire, les clients rattachés,
                     -- les assignations et `piecesBrouillon` (chemins des pièces déjà
                     -- téléversées dans storage/app/private/brouillons/{id}/).
                     -- Volontairement PAS un Dossier en étape « brouillon » — voir décision #34.

-- Ajoutées depuis (non documentées avant le 2026-07-06) :

courriers — id, reference, dossier_id, redacteur_id, destinataire, adresse, objet,
            type(enum: transmission/convocation/relance/divers), statut(brouillon/envoye),
            contenu, envoye_at, timestamps

baremes — id, type_acte_id, organisme, libelle, taux, montant_fixe,
          base_calcul(valeur_acte/montant_fixe), description, actif, ordre, timestamps

clients — id, type(physique/morale), civilite, prenom_nom, ne_a, date_naissance, nationalite,
          piece_type, piece_numero, piece_delivree_le, piece_delivree_a, piece_expire_le,
          situation_matrimoniale, regime_matrimonial, denomination, forme, rccm,
          representant_legal, representant_qualite, demeurant_ville, quartier, commune,
          pays, telephone, email, siege, statut, timestamps
          -- representant_qualite ajoutée le 2026-08-03 : seul champ d'identité attendu
          -- par les modèles Word (${bq.representant_qualite}) qui manquait à la fiche.

societes — id, dossier_id(nullable, cascadeOnDelete /* dossier de CONSTITUTION */),
           denomination(index), forme, sigle,
           capital_chiffres, nombre_parts, valeur_nominale_chiffres, siege_quartier,
           siege_commune, siege_ville, email_societe, telephone_societe, objet_social,
           duree, exercice_social, date_acte, rccm_numero(index), nif, jal_journal,
           direction(json), commissaire_titulaire, commissaire_suppleant, timestamps
           -- 2026-08-11, passage en registre réutilisable :
           date_constitution, notaire_origine, derniere_modification_at, actif
           -- Le lien inverse « ce dossier porte sur cette société » est dossiers.societe_id
           -- (nullOnDelete) : renseigné pour une constitution comme pour une modification
           -- ou une dissolution, où la fiche est choisie dans le registre.

baremes — (…) + condition_modification (2026-08-11, nullable)
           -- null = barème inconditionnel (tous les barèmes historiques). Sinon, valeur de
           -- TypeModificationStatutaire : le barème n'est retenu que si le dossier porte ce
           -- type de modification (Bareme::estApplicableA, partagé facturation/formalités).

biens_immobiliers — id, dossier_id(nullable, cascadeOnDelete), parcelle_numero, lot_numero,
                    lieu_de, nature_terrain, usage, superficie_m2, pcp, titre_foncier_numero,
                    tf_date, livre_foncier_ville, tf_volume, tf_folio, tf_annee,
                    limites_ne/so/se/no, origine_propriete, prix_vente_chiffres,
                    -- champs bail dans la même table :
                    type_bail, duree_bail, date_prise_effet, loyer_chiffres,
                    periodicite_loyer, destination_bien, engagement_construction, timestamps

banques — id, dossier_id(nullable, cascadeOnDelete), denomination, forme, quartier, commune,
          ville, montant_credit_chiffres, type_garantie, rang_hypothecaire, timestamps

factures — id, dossier_id, note_numero, note_date, objet, assiette_chiffres,
           total_chiffres, timestamps

lignes_factures — id, facture_id, designation, quantite, montant, timestamps

settings — key(string, PK), value(text, nullable), timestamps
           -- seed par défaut : office_nom, office_sous_titre, couleur_primaire,
           -- couleur_accent, couleur_fond, logo_path
           -- clés Sécurité (ajoutées 2026-07-15) : otp_enabled, otp_duration_minutes

-- Ajoutées le 2026-07-15 (2FA OTP + appareils de confiance) :

user_otp_codes — id, user_id, code_hash, attempts, expires_at, consumed_at,
                 last_sent_at, ip_address, timestamps

user_trusted_devices — id, user_id, token_hash(unique), label, ip_address,
                       last_used_at, expires_at, timestamps

-- Ajoutées le 2026-07-24 (module GED unifié — remplacent documents/formalite_pieces) :

document_fichiers — id, documentable_type, documentable_id (morph : Dossier|Formalite|Partie),
                     nom, categorie, statut, est_requis, est_fourni,
                     version_actuelle_id, edite_par_id, edite_at, created_by_id, timestamps

document_versions — id, document_fichier_id, numero, chemin_fichier, nom_original,
                     mime_type, taille_octets, source(upload|genere|restauration),
                     cree_par_id, timestamps
```

### Relations clés
- `Dossier` BelongsTo `User` ×4 (redacteur, reviseur, notaire, formaliste)
- `Dossier` BelongsTo `TypeActe`
- `Dossier` HasMany `Document`, `Formalite`, `Partie`, `JournalActivite`
- `Dossier` HasOne `Revision`, `Questionnaire`
- `Revision` HasMany `RevisionPoint`
- `TypeActe` HasOne `RevisionGrille` (grilleActive = est_active=true)
- `TypeActe` HasMany `Bareme`
- `Formalite` HasMany `FormalitePiece`
- `Partie` BelongsTo `Client` (nullable — coexistence, pas remplacement de `Partie`)
- `Client` HasMany `Partie`
- `Societe`, `BienImmobilier`, `Banque` BelongsTo `Dossier` (nullable, cascadeOnDelete)
- `Dossier` HasMany `Courrier`, `Facture`
- `Facture` HasMany `LigneFacture`
- `Courrier` BelongsTo `Dossier`, BelongsTo `User` (redacteur)
- `User` HasMany `UserOtpCode`, `UserTrustedDevice`

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
- `modif.*` / `ag.*` / `gerant_sortant.*` / `gerant_entrant.*` — modification de statuts (2026-08-11)

Les clés suffixées `_chiffres` génèrent automatiquement les variantes `_lettres`
(`NombreEnLettres::convertir()`) et `_formate` (séparateurs de milliers).

Une valeur au format **JJ/MM/AAAA** génère `_jma` (identique) et `_lettres` (date en toutes
lettres) — dérivation ajoutée le 2026-08-11 : le dictionnaire de balises annonçait cette
convention depuis l'origine sans qu'aucun code ne la produise.

⚠️ **Les champs masqués par `showIf` sont purgés à la soumission** (`purgerChampsInvisibles()`),
jamais sur un brouillon. Sans cela, décocher une case laissait ses valeurs dans `donnees` : elles
partaient dans les actes et, pour `modif.valeur_parts_cedees`, servaient d'assiette à la facture.

⚠️ **Une valeur en tableau a deux sens**, distingués par son contenu :
- tableau d'**objets** → bloc répétable, cloné par `cloneBlock()` (associés, gérants…) ;
- tableau de **scalaires** → choix multiple (`checkbox_group`, ex. `modif.types`), écrit comme
  énumération séparée par ` · `. Sans cette distinction, la balise restait littérale dans l'acte.

⚠️ Pour une modification de statuts, `soc.*` porte l'état **avant** (projeté depuis la fiche du
registre) et `modif.*` l'état **après**. Les statuts mis à jour doivent porter l'après.

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
| 19 | Workflow réduit à 6 étapes (suppression Signature client/notaire) | La signature était en pratique gérée dans les formalités plutôt que comme étape séparée bloquante. Migration `migrate_signature_etapes_to_formalites` bascule tout dossier existant vers `formalites`, sans `down()` — **irréversible**, l'état exact (signature client vs notaire) des dossiers migrés n'est pas conservé |
| 20 | `Partie.client_id` nullable plutôt que fusion `Partie`/`Client` | `Client` a été introduit pour permettre la réutilisation d'une personne entre plusieurs dossiers (répertoire), mais sans migration de données existantes ni CRUD dédié. `Partie` reste la source de vérité pour un dossier donné ; `Client` est un enrichissement optionnel, pas un remplacement |
| 21 | Deux mécanismes de calcul de montant non unifiés | `Formalite::calculerMontant()` (base×taux stocké sur la formalité) a précédé `Bareme::calculerMontant()` + `FacturationService` (barèmes paramétrables par type d'acte). Les deux coexistent aujourd'hui — ne pas supposer qu'un changement de barème impacte automatiquement le montant d'une formalité existante |
| 22 | `ActesGeneratorService` reste générique clé/valeur | Plutôt que du code dédié par bloc (`bien.*`, `bq.*`, `bail.*`), le service boucle sur toutes les clés du questionnaire et appelle `setValue()` pour chacune — ça fonctionne déjà pour ces préfixes sans changement de code, mais la dérivation d'adresse auto (`{pfx}.adresse`) ne couvre que `pp/ger/acq/loc/liquidateur`, pas `bien/bq/bail` |
| 23 | Login différé pour l'OTP (`Auth::validate` au lieu de `Auth::attempt`) | `LoginRequest::authenticate()` ne connecte plus l'utilisateur directement — il vérifie les identifiants et retourne le `User`. `AuthenticatedSessionController::store()` décide ensuite d'appeler `Auth::login()` immédiatement ou de rediriger vers le challenge OTP. Avantage : aucune session authentifiée n'existe avant validation complète du code, donc **aucun middleware `EnsureOtpVerified` n'est nécessaire** — le middleware `auth` existant bloque déjà tout accès tant que `Auth::login()` n'a pas été appelé |
| 24 | `user_otp_codes` et `user_trusted_devices` en tables séparées, pas de colonnes sur `users` | Données transactionnelles/éphémères (code OTP) ou multi-valuées (plusieurs appareils par utilisateur, révocation individuelle) — éviter d'alourdir `User` avec des colonnes majoritairement vides |
| 25 | `otp_enabled` est un interrupteur global (`Setting`), pas par utilisateur, désactivé par défaut | Simplicité : pas de préférence par compte à gérer dans cette v1. Défaut `false` volontaire — tant que le SMTP réel n'est pas testé, l'activer bloquerait tous les comptes hors du système (le code partirait dans les logs, jamais reçu) |
| 26 | Politique de mot de passe fixée en dur (`Password::defaults()`), non paramétrable par un admin | Contrairement à `otp_duration_minutes` (paramètre opérationnel sans risque), affaiblir la politique de mot de passe a un impact de sécurité direct et silencieux — un admin ne doit pas pouvoir la réduire par erreur ou intentionnellement via l'UI |
| 27 | Pusher (Channels) choisi pour le "push" plutôt que Web Push standard (VAPID) | Décision utilisateur explicite. **Nuance à ne pas oublier** : ce mécanisme (Echo + `Notification` API déclenchée en JS) ne délivre rien si le navigateur est complètement fermé — seulement tant que l'app est ouverte (onglet actif ou arrière-plan). Un vrai push hors-ligne nécessiterait un Service Worker + VAPID, ou Pusher Beams (produit séparé, payant au-delà d'un seuil) |
| 28 | **Aucune** notification n'implémente `ShouldQueue` — ni `TwoFactorCodeNotification`, ni les notifications métier | ⚠️ **Réécrite le 2026-08-03** : cette ligne affirmait que les 4 notifications métier étaient `ShouldQueue` et que seul l'OTP en était exempté. C'est faux — plus aucune ne l'est. Le raisonnement d'origine tient toujours pour l'OTP (le code doit partir immédiatement, dépendre d'un worker bloquerait la connexion) ; pour les notifications métier, le non-queuage garantit que `database` et `broadcast` partent quoi qu'il arrive. Contrepartie assumée : l'envoi du mail est **synchrone**, donc une latence SMTP ralentit la requête HTTP qui a déclenché l'événement. Acceptable au volume actuel (quelques destinataires par événement) ; à revoir si un événement doit notifier un pool large |
| 38 | **L'ordre d'évaluation d'une ability est : gel → étape → rôle → assignation** — le raccourci administrateur vient *après* le contrôle d'étape, jamais avant | Une ability doit encoder *quand* l'action est légitime autant que *qui* peut la faire. Trois abilities plaçaient `hasRole(Administrateur) return true` avant le contrôle d'étape (`update`, `delete`, `genererDocuments`) et trois après (`gererFormalites`, `cloturerDocuments`, `genererCourriers`) : l'incohérence permettait de régénérer un acte à l'étape Signature — donc après validation de la certification, sur un contenu qu'elle n'a pas vu — ou de supprimer un dossier engagé auprès d'un organisme. L'administrateur garde son passe-droit sur les **rôles** (il n'a pas besoin d'être assigné au dossier), plus sur le **moment**. **Nuance à ne pas perdre** : `update` couvre les informations générales, légitimes à toutes les étapes ouvertes — il n'y a pas d'étape à y contrôler au-delà du gel, et son raccourci admin reste avant le `match`. Uniformiser mécaniquement les six abilities aurait cassé le fonctionnement normal **Corollaire découvert le 2026-08-04 en réintroduisant l'étape Initialisation** : ajouter une étape à `EtapeDossier` fait échouer bruyamment les `match` exhaustifs côté PHP — c'est voulu — mais **passe en silence côté JavaScript**. `ETAPE_ORDER` (stepper de la fiche et de la liste), `ETAPE_TAB` (onglet d'atterrissage) et le `switch` de `getStepBlockers()` n'ont aucun filet : un oubli y donne un stepper amputé et un `indexOf()` à -1, sans erreur. Les traiter fait partie de la manœuvre. |
| 39 | Les **règles légales** (capital minimum, commissaire aux comptes, capacité juridique) sont bloquantes et vivent dans des enums à `match` exhaustif ; leurs **montants** sont paramétrables | Ce sont des contraintes de droit, pas des préférences : produire les statuts d'une SA sous-capitalisée expose l'office. Elles sont donc branchées dans `erreursDeConstitution()` comme l'accord client. La distinction code/paramètre suit la nature de la règle : « une SARLU admet un associé unique » ne change pas sans réécrire le droit des sociétés (code), le seuil de 140 000 000 GNF changera par voie légale (`Setting`). Corollaire découvert à l'implémentation : la forme juridique se déduit du **code du type d'acte** et non du questionnaire — `soc.forme` n'existe que dans un seul des questionnaires de société, et les 10 dossiers réels en sont dépourvus |
| 38 | `clients.prenom_nom` scindé en `nom_famille` + `prenoms`, mais **conservé en accessor et mutateur** | La règle 4 exige le nom de famille en majuscules dans les actes : impossible avec un champ unique, on ne sait pas quelle partie est le nom. La scission était donc inévitable — mais `prenom_nom` est référencé par les 63 modèles Word non normalisés, par l'intake public et par le répertoire. L'accessor le restitue en lecture, le mutateur le découpe en écriture, `$appends` le remet dans les réponses JSON : aucun appelant existant ne casse. La découpe (dernier mot = nom de famille) est faillible sur les noms composés, d'où sa journalisation à la migration |
| 37 | **Un dossier clôturé est figé** — `DossierPolicy::estFige()` refuse toute modification de contenu, et ce contrôle précède le raccourci administrateur | Le gel porte sur l'**état du dossier**, pas sur le rôle : c'est pourquoi il s'évalue avant `hasRole(Administrateur)`, contrairement à tous les autres contrôles de la policy. En notarial, altérer un dossier clôturé — retirer la vérification d'une pièce, déposer une nouvelle version signée, ajouter un paiement, régénérer un acte — n'a aucun usage légitime et ruinerait la valeur probatoire de la clôture. Avant cette décision, rien ne figeait un dossier clos : un administrateur pouvait tout y faire, et `gererFacturation` n'avait même aucun contrôle d'étape. Corollaire assumé : aucune route ne permet de rouvrir un dossier clôturé (`reculer()` n'est atteignable que depuis un renvoi de certification). La consultation reste entièrement ouverte — figer n'est pas masquer |
| 36 | L'inventaire de clôture est **dérivé du workflow**, pas configuré par type d'acte ; la coche de vérification vit dans une table polymorphe `cloture_verifications` | La configuration (`obligatoire_cloture`) déclarait à l'avance ce qui devrait exister à la clôture, alors que chaque étape **produit** ses pièces : c'était une duplication qui pouvait contredire la réalité (modèle coché mais jamais généré → clôture bloquée sans recours ; modèle décoché → acte manquant qui passe). Dériver l'inventaire supprime la double saisie et le risque de divergence. Le classement porte sur `documentable_type` (ensemble fermé) et non sur `categorie` (libre, venant de `ModeleActe.type_document`), pour qu'un `match` exhaustif soit possible. Table polymorphe plutôt que colonnes sur trois tables : l'inventaire traverse `document_fichiers`, `courriers` et `recus`, et poser des colonnes de clôture sur `recus` serait déplacé ; elle enregistre en outre qui a vérifié et quand (valeur probatoire). Ce qui bloque la clôture est un **contrôle humain** — des pièces d'origines hétérogènes qu'aucune règle automatique ne peut déclarer complètes |
| 35 | L'état terminal d'une formalité est défini par un `match` **exhaustif** dans `StatutFormalite::estTerminee()`, jamais par une comparaison de chaîne | La suppression du statut `Cloture` a laissé un `!== 'cloture'` dans `DossierStepService`, transformant l'étape Formalités en cul-de-sac sans qu'aucune erreur ne se déclenche : une comparaison à une chaîne littérale reste valide même quand la valeur n'existe plus nulle part. Un `match` sur les cas de l'enum, sans branche par défaut, échoue au contraire à la compilation dès qu'un cas n'est pas classé. Corollaire : les requêtes SQL passent par `valeursTerminees()` / le scope `nonTerminees()` plutôt que par `where('statut', '!=', …)`. `retour_recu` est terminal ; `rejete` bloque **et** exige une correction, ce que le message d'erreur distingue explicitement |
| 34 | Un brouillon de dossier est une table dédiée (`dossier_brouillons`), pas un `Dossier` en étape « brouillon » ; ses pièces sont stockées sur le disque **privé** et une commande de purge est fournie | La référence notariale est attribuée à la création du `Dossier` : un brouillon abandonné laisserait un trou définitif dans la numérotation, et polluerait la liste des dossiers, le dashboard et les compteurs. Un brouillon n'est pas un dossier — c'est un formulaire en cours de saisie, qui n'appartient qu'à son auteur (403 même pour un administrateur). Conserver les pièces déjà téléversées (choix utilisateur) impose un stockage réel : disque `local` et non `public`, parce que ce sont des pièces d'identité sans dossier de rattachement. Contrepartie assumée et outillée : `ayelema:brouillons-purger`, en dry-run par défaut — le projet traîne déjà 117 orphelins hérités d'avant la GED unifiée, on ne recommence pas. Enregistrement **explicite** plutôt qu'auto-save : pas d'écriture continue, et le bandeau de reprise ne s'affiche jamais par-dessus une saisie en cours |
| 33 | La fiche `Client` est la source de vérité de l'identité ; `questionnaires.donnees` n'en est qu'une **projection dérivée**, et l'emplacement de projection est porté par la `Partie` | Renverse le fonctionnement antérieur, où sélectionner un client **recopiait** son identité dans les champs du questionnaire : l'utilisateur ressaisissait ce qu'il venait d'enregistrer, et la copie divergeait dès la première correction. `donnees` reste néanmoins matérialisé, car `ActesGeneratorService` est un moteur générique clé/valeur et les modèles Word attendent `${pp.prenom_nom}` — le supprimer aurait imposé de réécrire la génération de documents. Le point délicat : le serveur doit projeter quand une fiche est modifiée depuis le Répertoire, mais le schéma des questionnaires vit en JavaScript. Plutôt que de le dupliquer en PHP (dérive garantie), le lien est rendu **auto-descriptif** : le frontend persiste `donnees_prefixe`/`donnees_bloc`/`donnees_index` sur la `Partie`. Seule la liste des suffixes d'identité subsiste en double, verrouillée par un test de dérive. **Ne sont jamais projetés** : les champs propres à l'acte (`parts_chiffres`, `fonction`, `qualite`, `bq.*` de crédit) — la même personne peut détenir 100 parts ici et 5 ailleurs. Un dossier clôturé et un document signé/cacheté ne sont jamais réécrits |
| 32 | Les paiements sont plafonnés au total facturé, et le trop-perçu existant est signalé plutôt que corrigé automatiquement | Renverse la position antérieure (« payer plus que le dû reste normal », qui n'était de toute façon contrôlée nulle part côté serveur). Décision utilisateur explicite du 2026-08-03. Le contrôle est posé **sous verrou de ligne** parce que la validation seule est vulnérable à deux encaissements concurrents. Les données déjà en trop-perçu ne sont **pas** rectifiées par migration : ce sont des écritures financières, seul un humain peut décider quel paiement corriger — l'UI les rend visibles (rouge + bandeau) et bloque tout ajout. Corollaire déjà acquis : le total ne peut pas non plus baisser sous les paiements (lignes gelées + régénération refusée dès le premier paiement) |
| 31 | Canal `broadcast` forcé en `onConnection('sync')` plutôt que de rendre le worker de queue obligatoire | `BroadcastNotificationCreated` implémente `ShouldBroadcast`, donc l'événement est **queué** même quand la notification ne l'est pas. Avec `QUEUE_CONNECTION=database` et aucun `queue:work` lancé, plus aucune notification temps réel n'était émise : la ligne existait bien dans `notifications` (visible après rafraîchissement) mais ni toast ni badge — la cause réelle du « parfois ça ne passe pas ». `CanauxNotification::toBroadcast()` renvoie un `BroadcastMessage` en `onConnection('sync')`, ce que `BroadcastManager::queue()` exécute immédiatement (il lit `$event->connection`). Alternative écartée : imposer un worker permanent en dev — rien ne garantit qu'il tourne, et l'échec est silencieux. Le worker reste nécessaire pour les `Mailable` explicitement `->queue()` (ex. `DemandeLienMail`) |
| 29 | Onglets `Dossiers/Show` jamais masqués, seulement estompés (`opacity-40`) selon l'étape atteinte | Alternative retenue à un show/hide strict : les onglets futurs restent cliquables (consultables) mais visuellement désaccentués via `TAB_STAGE` + `tabPasEncoreAtteint()` — "Informations" et "Facturation" sont exclus de ce mécanisme (toujours pleinement visibles, transversaux). L'ancien onglet "Parties" a été fusionné dans "Informations" (gestion des personnes désormais inline) et "Journal" a été renommé **Historique** puis sorti de la barre d'onglets vers un dialog dédié (le champ backend `dossier.journal` n'a pas été renommé, seul le libellé/l'emplacement UI a changé) |
| 30 | `DocumentFichier` polymorphe unique plutôt que 3 systèmes séparés (`documents`, `formalite_pieces`, `Partie.pieces`) | Les trois évoluaient déjà en parallèle avec des conventions de stockage divergentes et aucun vrai historique de versions (chaque régénération d'acte écrasait le fichier précédent sans le nettoyer ni le référencer — 117 orphelins accumulés dans `storage/app/public/dossiers/*`). Un modèle polymorphe + `document_versions` centralise `nouvelleVersion()`/`restaurerVersion()` une seule fois. Contrepartie assumée : `dossierGouvernant()` sur `DocumentFichier` doit résoudre le Dossier réel (direct, via `Formalite`, ou via `Partie`) pour que les policies (`genererDocuments`, `view`) restent valides quel que soit le documentable. Le JSON exposé au frontend pour les pièces de formalité garde volontairement les noms de champs historiques (`label`, `aUnFichier`…) pour ne pas casser `PieceGedRow.jsx` et les modals de dépôt/retour — seul `DocumentsTab` (nouveauté : historique de versions) expose le nouveau schéma (`categorie`, `est_fourni`…). La colonne `Partie.pieces` (JSON, jamais utilisée) a dû être supprimée dans la même foulée : elle entrait en collision avec la nouvelle relation Eloquent `Partie::pieces()` (un attribut de colonne réel prime sur une méthode de relation du même nom) |

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
| **La règle d'avancement d'étape est écrite en 3 endroits** | `DossierStepService::verifierPrerequis()` (l'autorité), `DossierController::index()` → `peutAvancer` (bouton de la liste) et `getStepBlockers()` dans `Dossiers/Show.jsx` (fiche). Les deux `match` PHP sont exhaustifs et lèveront une erreur si une étape est ajoutée ; **`getStepBlockers()` est en JavaScript et n'a aucun filet** — c'est le point faible restant. Modifier un prérequis exige de toucher les trois. `AvancementEtapesTest` couvre les deux premiers ; à terme, exposer les blocages depuis le serveur supprimerait le troisième |
| **Retirer un cas d'enum casté exige une migration de données** | Le cas `StatutFormalite::Cloture` a été retiré sans migrer les lignes : 14 formalités ont gardé la valeur `cloture`, et le cast Eloquent levait un `ValueError` → **500 sur la fiche dossier**. Corrigé le 2026-08-04. Avant de retirer un cas de `StatutFormalite`, `EtapeDossier`, `StatutRevision` ou `RoleUtilisateur`, vérifier `SELECT DISTINCT <colonne>` en base et prévoir la bascule. Un `match` exhaustif protège le code, pas les données déjà écrites |
| **Les restrictions d'étape sont aussi réparties sur 3 couches** | La `DossierPolicy` (qui peut agir, et quand), les `FormRequest` (quels champs sont acceptés) et les contrôleurs (quelle ability est invoquée). Un champ retiré d'une requête sans que la policy suive — ou l'inverse — laisse un trou : c'est précisément ce qui s'était produit avec les dates de signature, acceptées par `UpdateDossierRequest` alors qu'aucune ability n'en contrôlait le moment. `RestrictionsEtapesTest` teste les trois couches ensemble (ability **et** appel HTTP réel), ce qui est le seul moyen fiable de détecter la désynchronisation |
| `DEFAULT_GROUPES` dans Revision | 3 groupes statiques utilisés si pas de `RevisionGrille` en DB pour le type d'acte. Normal pour l'instant |
| Pages Auth (Login/Register) | Utilisent encore `GuestLayout` de Breeze — design non unifié, fonctionnel |
| `recharts` installé | Non encore utilisé — prévu pour graphiques dashboard (module futur) |
| Importation `@/components/*` vs `@/Components/*` | Windows insensible à la casse : les deux fonctionnent. Sur Linux (déploiement) : vérifier la cohérence de la casse |
| PDF preview non implémentée | Génération `.docx` OK, mais pas de prévisualisation navigateur. Nécessite LibreOffice headless ou service tiers |
| 63 modèles `.docx`/`.doc` bruts dans `Documents reçus/` | Non normalisés (pointillés/MAJUSCULES) — inutilisables tels quels par `ActesGeneratorService`. Un seul fichier de référence normalisé (`STATUTS_SARLU_balises.docx`). Voir `Analyse_et_Prompt_Generation_Modeles_Ayelema.md` |
| Blocs `cr.*` / `fac.*` absents de `questionnaires.js` | Documentés dans l'analyse mais pas de formulaire frontend — les courriers de transmission et factures détaillées ne peuvent pas encore être générés depuis un questionnaire dédié |
| `BienImmobilier`/`Banque` sans CRUD ni seeder | Tables et modèles existent, alimentés uniquement en creux via le questionnaire du dossier — pas d'écran de gestion, pas de données de démo. **`Client` (2026-08-03) et `Societe` (2026-08-11) sont sortis de cette liste** |
| Le registre des sociétés n'a pas d'écran dédié | `SocieteController` n'est atteignable que depuis l'assistant de dossier (autocomplétion + modale). Consulter ou corriger une fiche hors création d'un dossier n'est pas possible — à exposer dans `Repertoire/Index.jsx`, au même titre que les clients |
| Les 6 modèles d'actes de `SOC-MOD` sont inactifs | Les gabarits `.docx` (acte de cession, PV d'AGE, statuts mis à jour, DNSV, déclaration RCCM, page de garde) ne figurent pas dans `Documents reçus/` — seules les 2 lettres de transmission existent. Les entrées sont seedées `est_actif = false`, prêtes à recevoir leur fichier depuis *Modèles d'actes*. **Exception depuis le 2026-08-11** : `statuts_maj` est produit malgré son modèle inactif dès que la société a déposé ses statuts en `.docx` (gabarit hérité) |
| Les pièces de société sont à cocher dans chaque dossier | Conséquence assumée du choix de les inventorier : la vérification de clôture est par dossier, donc les mêmes statuts se recochent à chaque modification de la société. Correct sur le fond (chaque clôture atteste que *ce* dossier a été contrôlé) mais répétitif — à revoir si l'étude enchaîne beaucoup de modifications sur une même société |
| `SOC-DIS` n'exige pas de dossier constitutif | La règle `verifierPiecesConstitutives()` est volontairement limitée à `SOC-MOD`. Une dissolution a le même besoin (les statuts y sont tout aussi nécessaires), mais la dissolution n'a pas encore été reprise : l'étendre sans l'examiner bloquerait des dossiers sur une règle non validée |
| Migration `migrate_signature_etapes_to_formalites` irréversible | Pas de `down()` — si des dossiers étaient en étape signature avant le 2026-07-03, leur état précis (client vs notaire) est perdu, ils sont tous en `formalites` maintenant |
| Routes `parametres/apparence` et `parametres/grilles` présentes dans `web.php` | Non vérifié si les pages React correspondantes existent et sont branchées (`Setting` pour apparence, UI d'édition de grille de révision) — à confirmer avant de s'y fier |
| ~~Worker de queue requis en production (2FA/notifications)~~ | ⚠️ **Ligne périmée, corrigée le 2026-08-03** : aucune notification n'est `ShouldQueue`, elles partent toutes en synchrone (mail inclus), et le temps réel ne dépend plus du worker depuis la décision #31. Ce qui manquait réellement, c'était le **scheduler** (ligne suivante) |
| **Scheduler requis** pour les alertes d'échéance et de formalité | `ayelema:alerter-echeances` / `ayelema:alerter-formalites` sont la **seule** source de ces notifications. En dev : `schedule:work` est désormais dans `composer dev`. **En production : un cron `* * * * * php artisan schedule:run` est indispensable** — sans lui, aucune alerte d'échéance ne part jamais, silencieusement. À vérifier avec `php artisan ayelema:notifications-diagnostic` |
| Worker de queue toujours utile en production | Plus pour les notifications, mais pour les `Mailable` explicitement `->queue()` (`DemandeLienMail`). Un `queue:work` sous supervisor reste recommandé ; sa présence se vérifie via le compteur « Jobs en attente » du diagnostic |
| Envoi de mail synchrone | Une latence ou un timeout SMTP ralentit la requête HTTP qui déclenche l'événement (l'action métier aboutit quand même, l'erreur est capturée par `NotificationService::envoyer()`). À revoir si un événement doit notifier un pool large — voir décision #28 |
| `otp_enabled` désactivé par défaut | Ne pas l'activer dans `Parametres > Sécurité` sans avoir testé un envoi SMTP réel au préalable (`Mail::raw` via tinker) — sinon tous les comptes se retrouvent bloqués hors du système au prochain login |
| Push "temps réel" via Pusher ≠ push hors-ligne | Le mécanisme actuel (Echo + `Notification` API) ne fonctionne que navigateur ouvert (onglet actif ou arrière-plan) — pas de notification si le navigateur est complètement fermé. Voir décision #27 si un vrai push hors-ligne devient nécessaire |
| Pas de "renouvellement forcé" du mot de passe | `Password::defaults()` ne s'applique qu'aux nouveaux mots de passe saisis (inscription, reset, changement) — les mots de passe déjà en base avant cette politique ne sont ni vérifiés ni forcés à la mise à jour |
| 117 fichiers orphelins dans `storage/app/public/dossiers/*` (hérités d'avant le module GED) | Recensés par `php artisan ayelema:ged-lister-orphelins` le 2026-07-24, **jamais supprimés automatiquement** — à traiter manuellement avec `--supprimer` une fois la bascule GED validée en conditions réelles |

---

*Dernière mise à jour : 05/08/2026 — **Intégration des règles de gestion notariales** (`Regles_Gestion_Plateforme_Notariale.docx`, CR juillet 2026). Onze jeux de règles légales et tarifaires, dont aucune n'était appliquée : `soc.forme` n'était qu'une liste de sept choix sans conséquence. Capital minimum, formes unipersonnelles, classification capitaux/personnes, unicité de dénomination, commissaire aux comptes, PV d'assemblée, capacité juridique des mineurs, impact et documents des modifications statutaires, tarifs DGI et greffe — toutes appliquées, les six premières de façon **bloquante** (décision #39). Deux constats ont changé la conception : la forme juridique se déduit du **code du type d'acte** et non du questionnaire (les 10 dossiers réels n'ont pas de `soc.forme` — ma première version les bloquait tous), et le PV d'assemblée était **facultatif de fait**, la pièce s'appelant « Déclaration RCCM *ou* PV ». `clients.prenom_nom` scindé en nom de famille et prénoms pour la mise en capitales exigée, avec accessor et mutateur de compatibilité (décision #38). 25 tests, un par règle. Précédemment — **réintroduction de l'étape Initialisation.** La création déposait le dossier directement en Édition, où `verifierEdition()` cumulait « constituer le dossier » (objet, intervenants, pièces des personnes, accord signé du client) et « produire les actes » — et les actes étaient générés dès la création, donc sur un questionnaire que le client n'avait pas validé. Le workflow compte désormais **7 étapes** : on atterrit sur l'onglet Informations d'un dossier en Initialisation, et les actes sont produits au passage en Édition, sans jamais écraser un acte corrigé à la main. Le questionnaire, les parties et l'accord ne se modifient plus qu'en Initialisation. Double contrôle conservé à la sortie d'Édition pour les 3 dossiers antérieurs qui y sont déjà — vérifiés bloquants sur la base réelle. 4 tests ajoutés, 3 réécrits. Précédemment — **francisation de l'interface** : l'application n'avait aucun dossier `lang/` et tournait en locale `en` — l'écran de connexion affichait « The email field is required. » Paquet `lang/fr` complet (validation avec les ~110 libellés métier des champs, auth, mots de passe, pagination, `fr.json` pour les e-mails), locale et fallback en `fr` jusque dans les défauts de `config/app.php`, et pages d'erreur 403/404/419/429/500/503 traduites et contextualisées. Carbon suit la locale, ce qui francise les alertes d'échéance. 6 tests, dont la non-divulgation de l'existence d'un compte. Et **statut du dossier plus visible** : étiquette avec pastille et position dans le workflow (« Formalités 4 / 6 »), présente aussi dans la carte d'en-tête ; **Clôture passe en dernier onglet** après Facturation, et son badge — qui lisait encore `est_requis`, colonne vidée par la refonte, et affichait donc toujours 0 — compte désormais les pièces restant à vérifier. Précédemment — **correctif d'un 500 en production** : 14 formalités portaient encore `statut = 'cloture'`, valeur retirée de `StatutFormalite` sans migration de données — le cast Eloquent levait un `ValueError` et la fiche de 4 dossiers était inaccessible. Détecté en testant les pages sur la base réelle, pas par les tests (base neuve). Et **audit des restrictions d'étape, toutes étapes.** Le défaut était systémique : plusieurs abilities encodaient *qui* peut agir mais pas *quand*. Le plus grave : `updateQuestionnaire` n'était gardé que par `update`, donc le questionnaire restait modifiable jusqu'à l'Expédition **et l'action régénère les actes** — une certification validée pouvait porter sur un contenu réécrit après coup. Deux abilities nouvelles (`modifierQuestionnaire`, Édition seule ; `enregistrerSignatures`, étape Signature seule, avec action et route dédiées après extraction des dates de `UpdateDossierRequest` où elles étaient effaçables à toute étape). Ordre d'évaluation uniformisé — gel → étape → rôle (décision #38) — sans uniformiser mécaniquement : `update` reste légitime à toutes les étapes ouvertes. Trois manques adjacents comblés : `ClientPolicy` (aucune autorisation n'existait sur des actions qui régénèrent les actes de tous les dossiers liés), cloisonnement de la liste des courriers et de ses compteurs, parties non modifiables après certification. 16 tests, dont un garde-fou de structure vérifiant que chaque étape est refusée par au moins une ability. Précédemment — **la clôture passe de la configuration à l'inventaire.** L'approche « documents obligatoires déclarés par type d'acte » (`obligatoire_cloture`, page `Paramètres > Clôture`) est **abandonnée** : elle dupliquait une vérité que le workflow produit déjà, et pouvait la contredire. L'onglet Clôture affiche désormais l'**inventaire complet** du dossier — toutes les pièces de toutes les étapes, rangées en six rubriques canoniques dérivées de leur origine — que le notaire ou le formaliste vérifie pièce par pièce avant de clôturer (table polymorphe `cloture_verifications`, décision #36). Sur `SOC-2026-0009`, l'ancien écran montrait 5 éléments là où le dossier compte 19 pièces. La GED adopte le même rangement (dossier > rubrique), ce qui y fait entrer courriers et reçus, jusqu'ici absents. Point délicat traité : `est_requis` est une colonne surchargée — notion morte sur les actes et courriers, vivante sur les pièces de parties et de formalités où elle signifie « pièce à fournir » ; la migration de nettoyage filtre sur `documentable_type`. 19 nouveaux tests, 3 tests d'avancement réécrits. **Audit des restrictions de l'étape** dans la même passe : rien ne figeait un dossier clôturé — un administrateur pouvait le modifier, régénérer ses actes ou le supprimer, un comptable y enregistrer un paiement (`gererFacturation` n'avait aucun contrôle d'étape), et l'inventaire d'un dossier clos restait décochable. `DossierPolicy::estFige()` (décision #37) refuse désormais toute modification de contenu, **avant** le raccourci administrateur, puisqu'il s'agit de l'état du dossier et non d'une permission. Précédemment — **visibilité des conditions bloquantes** : la carte « Accord client », prérequis obligatoire pour quitter l'Édition, était reléguée en bas de l'onglet Informations ; extraite en composant dédié (`AccordClientCard`), placée **en tête** de l'onglet, rendue autonome (imprimer la fiche + téléverser l'accord depuis la carte) et signalée comme bloquante quand elle est en attente. Les blocages listés dans l'en-tête sont désormais **cliquables** et mènent à la section où agir. Précédemment — **audit des prérequis d'étape** : 3 divergences trouvées entre le serveur et les deux miroirs frontend (bouton « Avancer » actif à tort en Expédition, deux prérequis d'Édition omis sur la fiche, ancienne règle d'Expédition restée en place). Les `match` PHP sont désormais exhaustifs, `revisionValidee()` compare un cas d'enum, 9 tests verrouillent la cohérence. Le point faible restant est documenté : la règle vit en 3 endroits, dont un en JavaScript sans filet. Précédemment — **correctif : l'étape Formalités était un cul-de-sac.** La suppression du statut `Cloture` par formalité avait laissé un `!== 'cloture'` dans `DossierStepService` : aucune formalité ne pouvant plus porter ce statut, le passage à l'Expédition était définitivement bloqué, silencieusement. L'état terminal est désormais défini par un `match` exhaustif dans `StatutFormalite::estTerminee()` (décision #35) — un nouveau statut ne peut plus passer inaperçu — et le message d'erreur distingue un rejet à corriger d'une attente de retour. 7 tests. Et **brouillons de création de dossier** : l'assistant peut être enregistré en cours de saisie (table dédiée `dossier_brouillons`, et non un `Dossier` en étape brouillon qui consommerait une référence notariale — décision #34). Les pièces déjà téléversées sont conservées sur le disque privé et rattachées aux parties à la finalisation ; contrepartie outillée par `ayelema:brouillons-purger` en dry-run. Enregistrement explicite, bandeau de reprise, cloisonnement par auteur. 10 tests. Et **la fiche Client devient la source de vérité de l'identité** : les sections de rôle (associé unique, gérant, vendeur…) ne resaisissent plus 18 champs, elles **désignent** une fiche ; `donnees` devient une projection dérivée, recalculée côté serveur par `ClientProjectionService` grâce à un lien auto-descriptif porté par la `Partie` (`donnees_prefixe`/`donnees_bloc`/`donnees_index`) — ce qui évite de dupliquer en PHP le schéma des questionnaires, qui vit en JS. Corriger une fiche réaligne les dossiers non clôturés et régénère leurs actes non signés, avec trace au journal. Un seul champ manquait réellement à la fiche (`representant_qualite`) : l'écart était dans le flux, pas dans le schéma. Décision #33, 10 tests dont un test de dérive PHP↔JS. Et **plafonnement des paiements** : la somme des paiements d'une facture ne peut plus dépasser son total (contrôle serveur sous verrou de ligne, plafond dur dans le modal, trop-perçu antérieur signalé en anomalie sans migration corrective — décision #32, 11 tests). Et **fiabilisation des notifications** : deux causes distinctes traitées. (1) Livraison — le canal `broadcast` était queué alors qu'aucune notification ne l'est (`onConnection('sync')`, décision #31), le scheduler n'était jamais lancé en dev (donc zéro alerte d'échéance), le hook Echo perdait les événements à chaque navigation Inertia, et un `notify()` non protégé pouvait renvoyer un 500 après une action réussie. (2) Destinataires — `NotificationService` remplace l'usage systématique de `ayantsDroit()` par un ciblage par rôle avec repli sur le pool ; 4 événements sans aucune notification sont couverts (assignation, renvoi en correction, certification validée, **passage aux formalités** — le formaliste n'était averti par rien). Ajout de `ayelema:notifications-diagnostic` et de 8 tests de routage. Corrections de fond du devbook lui-même : workflow réel (plus d'`Initialisation`, étape `Signature` unique réintroduite), rôles multiples via `role_user`, décision #28 réécrite (aucune notification n'est `ShouldQueue`), ligne « worker de queue requis » périmée.*

*Mise à jour précédente : 24/07/2026 — Module GED unifié : `documents`/`formalite_pieces`/`Partie.photo_chemin`+`pieces` remplacés par un modèle polymorphe unique (`DocumentFichier`/`DocumentVersion`) avec vrai historique de versions (restauration non destructive), upload photo/pièces de partie ajouté, convention de stockage unifiée, ancien tables supprimées après backfill vérifié. Correction d'une ligne obsolète du backlog (prévisualisation PDF déjà faite côté client). Voir décisions #30 et section « Module GED unifié » (§5).*
