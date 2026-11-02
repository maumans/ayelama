# 02 — Référentiel des écrans

Inventaire exhaustif des écrans d'Ayelema : ce que chacun affiche, et ce qu'on peut y faire.
Établi par lecture du code (routes, contrôleurs, 34 composants d'écran). Les libellés cités entre
guillemets sont ceux réellement affichés.

---

## A. La barre de navigation (présente sur tous les écrans connectés)

Barre latérale, repliable. Onze entrées, dont certaines n'apparaissent que selon les rôles :

| Entrée | Visible pour | Pastille de compteur |
|---|---|---|
| **Tableau de bord** | tous | — |
| **Dossiers** | tous | — |
| **Certifications** | Certificateur, Administrateur | nombre de certifications en attente |
| **Formalités** | Formaliste, Administrateur | nombre de formalités urgentes |
| **Facturation** | Comptable, Administrateur | nombre de dossiers impayés |
| **Modèles d'actes** | tous | — |
| **GED** | tous | — |
| **Répertoire** | tous | — |
| **Sociétés** | tous | — |
| **Anniversaires** | tous | — |
| **Courriers** | tous | — |
| **Demandes clients** | ceux qui peuvent ouvrir un dossier | — |
| **Paramètres** | Administrateur seul | — |

En haut à droite, sur chaque écran :

- un **fil d'Ariane** cliquable (accueil → section → élément) ;
- la **recherche globale** : cherche simultanément dans les **références et objets de dossiers** et
  dans les **noms des personnes au dossier**, et mène directement à la fiche dossier ;
- la **cloche de notifications** : liste déroulante, marquage « lu » individuel ou global ;
- le **menu utilisateur** : « Mon profil », « Déconnexion ».

---

## B. Connexion et compte

### B.1 Connexion (`/login`)

Email + mot de passe, case « se souvenir de moi », lien « mot de passe oublié ».

### B.2 Code à usage unique (`/verify-otp`)

Si la **double authentification par email** est activée par l'administrateur, un code à 6 chiffres
est envoyé par email après la saisie du mot de passe. Sa durée de validité est paramétrable.
L'appareil peut être marqué **appareil de confiance**, ce qui évite de redemander le code.

### B.3 Mot de passe oublié / réinitialisation (`/forgot-password`, `/reset-password`)

Envoi d'un lien par email, puis saisie du nouveau mot de passe. Les exigences de complexité
s'affichent en direct sous le champ, au fur et à mesure de la frappe.

### B.4 Mon profil (`/profile`)

Quatre blocs :

- **Informations du profil** — nom, email.
- **Modifier le mot de passe** — ancien, nouveau, confirmation, avec les exigences affichées.
- **Appareils de confiance** — liste des appareils enregistrés, avec révocation individuelle.
- **Supprimer le compte** — destructif, demande le mot de passe.

---

## C. Tableau de bord (`/dashboard`)

Écran d'accueil. Il comporte :

- un **bandeau de statistiques** ;
- le **pipeline des dossiers** : le nombre de dossiers à chaque étape (Édition, Certification,
  Signature, Formalités, Expédition, Archivés), présenté comme une chaîne ;
- la **file d'attente** : les dossiers qui attendent une action, avec un **filtre** ;
- les **alertes urgentes** : échéances proches ou dépassées, formalités urgentes ;
- l'**activité récente** : les derniers gestes posés dans l'étude, horodatés ;
- la **répartition par catégorie** d'acte ;
- un bouton d'accès direct à la création d'un dossier.

Les échéances sont exprimées en relatif (« dans 3 jours », « il y a 2 jours »).

---

## D. Dossiers

### D.1 Liste des dossiers (`/dossiers`)

**Quatre compteurs** en tête : Total, En cours, En retard, Archivés ce mois.

**Filtres** : recherche libre (référence ou objet), étape, catégorie, tri. Un bouton réinitialise
tous les filtres. La liste est paginée.

Bouton **« Nouveau dossier »**.

### D.2 Assistant de création d'un dossier (`/dossiers/create`)

Trois étapes, avec un bandeau de progression :

**Étape 1 — Catégorie.** Choix de la catégorie d'acte, puis du type d'acte précis.

**Étape 2 — Détails.** Le questionnaire du type d'acte choisi, découpé en **sections repliables**.
Certains champs n'apparaissent qu'en fonction des réponses précédentes.

> **Pour une modification ou une dissolution, la société se choisit en tout premier**, juste sous
> le type d'acte — avant les clients du dossier, avant l'objet. Ce n'est pas un détail de mise en
> page : ce choix remplit la moitié de ce qui suit. Il préremplit la dénomination, la forme, le
> capital et le siège, rattache les personnes que le registre connaît à leur rôle, fait
> apparaître le dossier constitutif et détermine les pièces exigées. Le demander plus bas
> revenait à faire saisir un formulaire avant de savoir de quelle société il parle.

On y trouve ensuite :

- les champs propres à l'acte (capital, prix, durée, objet social…) ;
- les **personnes au dossier**, avec leur rôle ; chacune peut être rattachée à une **fiche client**
  existante (recherche par autocomplétion) ou créée à la volée ;
- les champs de **lieu** en cascade Ville → Commune → Quartier, avec un bouton *Ajouter* si le lieu
  manque au référentiel ;
- l'objet du dossier (10 caractères minimum), le notaire et le certificateur assignés.

Quand une information est déjà portée par une fiche liée (client, société), le champ est masqué du
questionnaire : c'est la fiche qui fait foi.

**Le bouton « Suivant » n'est jamais grisé sans explication.** Un panneau énumère tout ce qui
manque : pour chaque champ bloquant, sa section, son libellé et la raison (« Cochez au moins une
option », « Aucun quartier au référentiel — ajoutez-le depuis le champ », « Choisissez d'abord la
ville »…). Un clic sur une ligne fait défiler jusqu'au champ concerné.

**Étape 3 — Récapitulatif.** Reprend la saisie, et **annonce les actes qui seront produits** par
la procédure choisie.

**Brouillons** : une saisie inachevée peut être enregistrée comme brouillon. Le brouillon est
propre à son auteur et **ne consomme pas de référence de dossier**. Il se reprend plus tard.

### Registre des sociétés (`/societes`)

Toutes les fiches société de l'étude, avec leur statut de cycle de vie.

**Les lacunes du registre sont cliquables.** Sous le titre, une puce par donnée manquante —
sociétés immatriculées dont le RCCM n'a jamais atteint le registre, fiches sans NIF, sans date
d'immatriculation, sans dossier constitutif. Un clic isole les fiches concernées, un second
relâche le filtre. Une lacune qui ne concerne aucune fiche n'est pas affichée.

**Sur chaque ligne** : le RCCM, le NIF, l'email et le téléphone se copient d'un clic — ce sont
eux qu'on recopie sans cesse dans les formalités et les courriers. Le nombre de dossiers mène à
la liste filtrée sur cette société, et la référence en dessous ouvre le **dossier constitutif**,
où se trouvent statuts, PV, RCCM et DNSV.

> **Une fiche société ne se corrige pas depuis le registre**, et ce n'est pas un oubli : une
> modification de société est l'objet d'un **dossier**, et elle n'est portée au registre qu'une
> fois l'acte expédié — après le procès-verbal, la certification et les formalités. Un formulaire
> d'édition ici permettrait à la fiche de dire autre chose que les actes.
>
> L'avertissement « immatriculée — RCCM absent du registre » n'a donc pas de bouton : le numéro
> arrive au retour de la formalité, ou se saisit dans le prochain dossier de la société.

> Les colonnes **Prochaine échéance** et **Liquidateur** n'apparaissent que si au moins une
> société affichée est en liquidation. Elles seraient vides le reste du temps et repousseraient
> hors de vue le siège et le capital.

**« Radier »** n'apparaît que sur une liquidation clôturée — la radiation est constatée à la main,
sur la pièce du greffe, car aucune étape de dossier ne la prouve.

---

### Fiche société (`/societes/{id}/fiche`)

Depuis le registre, cliquer sur une dénomination ouvre sa fiche.

En haut, **l'état actuel** : capital, parts, valeur nominale, durée, RCCM, NIF, siège, objet.
Puis deux onglets.

**Associés & gérants** — qui détient la société aujourd'hui, avec le nombre de parts, le
pourcentage et la date d'entrée ; la gérance, statutaire ou non ; et, plus bas, **les anciens**,
avec leurs dates de sortie et le motif de fin de mandat. Une personne qui sort n'est pas effacée :
sa ligne est datée. C'est ce qui permet de savoir qui détenait quoi à une date donnée.

> Si la répartition ne couvre pas toutes les parts du capital, la fiche le dit — et la prochaine
> opération sur cette société sera refusée tant que l'écart subsiste.

**Historique** — la suite des opérations appliquées, de la plus récente à la plus ancienne, avec
pour chacune la date d'effet (celle de l'assemblée, pas celle du traitement), le dossier qui l'a
portée, son auteur, et le détail de ce qui a changé : « Capital : 10 000 000 → 25 000 000 GNF ».

> **L'historique ne se corrige pas et ne s'efface pas.** Une opération appliquée est un fait
> daté ; pour revenir dessus, on en enregistre une nouvelle. Il survit à la suppression du
> dossier qui l'a produite.

---

### Anniversaires (`/anniversaires`)

La liste des clients dont l'anniversaire approche, avec de quoi le souhaiter sans quitter l'écran.

**Quatre filtres**, combinables, et inscrits dans l'adresse de la page — une vue filtrée se
partage donc par simple copie du lien :

- **Fenêtre** 7, 30 ou 90 jours ;
- **Statut** : tous, à souhaiter, déjà souhaités ;
- **Mois civil** : tous les anniversaires d'octobre, par exemple, **y compris ceux déjà passés**
  ce mois-ci — c'est fait pour préparer ou rattraper une série ;
- **Recherche** par nom.

**Sur chaque ligne** : l'email et le téléphone se copient d'un clic, un bouton ouvre votre
messagerie avec un message d'anniversaire déjà rédigé, un autre ouvre WhatsApp avec le même
texte, et un lien mène aux dossiers du client.

> **« Marquer souhaité »** enregistre qui a souhaité, quand, et par quel moyen. C'est **vous**
> qui souhaitez : l'application n'envoie rien — un message d'anniversaire envoyé par une machine
> se voit. Elle retient ce que vous déclarez avoir fait, pour que la personne suivante ne
> redouble pas l'appel et qu'un client ne soit pas oublié.
>
> La marque porte sur **l'anniversaire fêté**, pas sur le jour du geste : souhaiter le
> 31 décembre pour un anniversaire du 2 janvier coche bien l'année qui vient.

Seuls les clients **personnes physiques** ayant une date de naissance apparaissent.

---

### D.3 Fiche dossier (`/dossiers/{référence}`)

L'écran central de l'application. Il se compose de :

**En-tête** — référence, objet, type d'acte, badges d'étape et de statut ; boutons « Historique du
dossier » et « Modifier le dossier ».

**Bandeau des 7 étapes** — la position du dossier, les étapes franchies, celle en cours.

**Colonne de droite** — « Étape courante », l'échéance, les formalités urgentes, les notes.

**Le panneau des conditions requises** — sur l'étape courante, l'application **énumère ce qui
manque** pour avancer, ligne par ligne, plutôt que de griser le bouton.

**Huit onglets.** Ceux qui correspondent à une étape non encore atteinte sont estompés. Un point
coloré marque l'onglet de l'étape en cours ; l'application ouvre automatiquement cet onglet.

#### Onglet « Informations »

- La **fiche dossier** : l'ensemble du questionnaire, en lecture, modifiable via un modal.
- Les **personnes au dossier** : pour chacune, sa photo, son rôle, sa fiche client liée, et la
  **liste de ses pièces requises** avec l'état fourni / manquant.
  - Téléverser une pièce manquante.
  - **Reprendre une pièce** que la même personne a déjà fournie dans un autre dossier — proposée
    avec la date de dépôt, car une pièce d'identité a une durée de validité. Un bouton
    « reprendre tout » traite d'un coup toutes les pièces reprenables.
  - Ajouter une personne qui n'est pas prévue par le questionnaire ; en retirer une.
  - Changer la photo d'une personne.
- Le bouton **« Télécharger la fiche de recueil »** : le PDF à faire signer au client.
- Le **téléversement de l'accord du client** — le libellé attendu dépend du type de dossier
  (fiche de recueil signée pour une constitution, décision des associés pour une modification).

Pièces requises, par rôle de la personne :

| Rôle | Pièces exigées |
|---|---|
| Associé, associé unique, cédant, cessionnaire, souscripteur, gérant, gérant entrant (personne **physique**) | CNI / Passeport · Certificat de résidence · Deuxième photo d'identité |
| Associé personne **morale** | Statuts · Déclaration RCCM · PV de l'assemblée générale autorisant la participation · CNI/passeport du représentant légal |
| Gérant sortant, président de séance, secrétaire de séance | *aucune* — ces personnes sont mentionnées à l'acte, elles n'y apportent rien |

> **Le champ « Nature »** (personne physique / personne morale) décide de ce que l'écran demande
> et des pièces exigées. Il n'apparaît que là où il change quelque chose : associé, associé
> unique, cédant, cessionnaire, souscripteur, liquidateur. Choisir « Personne morale » remplace
> l'état civil (naissance, pièce d'identité) par la forme juridique, le RCCM et le représentant
> légal — et fait basculer la liste des pièces.
>
> **Dès qu'une fiche client est rattachée, ce champ disparaît** : la fiche dit déjà si elle
> décrit une personne ou une société. On ne le renseigne que pour une saisie sans fiche.
>
> Sur les **actionnaires d'une SA** et les **membres d'un GIE**, le champ existe pour
> information mais n'est pas obligatoire : aucune pièce ni aucun acte n'en dépend aujourd'hui.

#### Onglet « Actes & documents »

Liste des fichiers du dossier, classés par type : **Acte principal, Annexe, Procédure, Lettre,
Récépissé**. Pour chacun :

- **Prévisualiser** — aperçu Word/Excel directement dans le navigateur, sans téléchargement ;
- **Télécharger** ;
- **Historique des versions** — télécharger une version antérieure, ou **restaurer** ;
- **Régénérer depuis le modèle** — écrase la version actuelle ;
- **Supprimer** ;
- **Téléverser la version signée** ;
- statut d'édition : « À éditer » / « Édité ».

Un document marqué signé/cacheté est **verrouillé** (l'icône le signale et renvoie à l'onglet
Clôture).

On peut aussi **verser un document** au dossier manuellement, en choisissant son type.

#### Onglet « Certification » (l'étape s'appelle « Certification des actes »)

Deux vues selon le rôle :

- Depuis la fiche dossier : l'état de la certification, et le bloc **« Renvoyer en correction »**.
- L'écran de certification dédié (`/dossiers/{référence}/revision`) : voir § E.

#### Onglet « Signature »

Saisie des **deux dates de signature** : celle du client, celle du notaire. Ces dates sont
enregistrées par une action distincte, disponible uniquement à l'étape Signature — elles ne sont
donc ni modifiables ni effaçables depuis les autres étapes.

#### Onglet « Formalités »

Les formalités du dossier, avec leur organisme, leur statut, leur montant et leur échéance.
Déposer, enregistrer un retour, téléverser une pièce, supprimer une formalité.

#### Onglet « Expédition »

- **« Lettres de transmission disponibles »** — celles applicables au type d'acte.
- Les courriers déjà générés : télécharger, **marquer envoyé**, téléverser la version signée.

À l'entrée dans l'étape Expédition, **les lettres de transmission applicables sont générées
automatiquement** : toutes les données du dossier sont définitives à ce stade.

#### Onglet « Facturation » (toujours accessible, quelle que soit l'étape)

- **Note de frais / Facture** : les lignes, chacune modifiable ou supprimable ; ajout d'une ligne ;
  téléchargement du PDF.
- **Remise sur une ligne** : la fenêtre de modification propose deux champs liés — **en montant**
  ou **en pourcentage**. Saisir l'un remplit l'autre ; c'est la forme saisie qui est conservée.
  L'option n'apparaît que sur les lignes dont le barème l'autorise : par défaut les **honoraires**
  de l'étude, jamais les **débours** qu'elle reverse à un tiers. Sur une ligne non remisable,
  l'écran explique pourquoi au lieu de masquer l'option.
- **Paiements** : enregistrer un encaissement, le modifier, le supprimer, **générer le reçu**
  (téléchargeable et prévisualisable).

Un paiement ne peut pas dépasser le total facturé. Réciproquement, dès qu'un paiement existe les
lignes ne sont plus modifiables — remise comprise.

Le pied de facture n'affiche « Sous-total avant remise » et « Remises accordées » que s'il y a
effectivement une remise.

#### Onglet « Clôture »

L'**inventaire de clôture** : toutes les pièces du dossier, classées en sept rubriques, à vérifier
une par une.

| Rubrique | Ce qu'elle contient |
|---|---|
| **Actes** | Actes générés depuis les modèles et documents ajoutés au dossier |
| **Accord du client** | Fiche de recueil signée par le client |
| **Pièces des parties** | Pièces d'identité et justificatifs des personnes |
| **Pièces de la société** | Dossier constitutif de la société, versé au registre |
| **Pièces des formalités** | Justificatifs déposés et retours des organismes |
| **Courriers** | Lettres de transmission |
| **Facturation** | Reçus de paiement |

On coche pièce par pièce, ou **rubrique entière** d'un coup. On peut retirer une vérification.
La clôture n'est possible que lorsque **tout** l'inventaire est vérifié.

---

## E. Certifications

### E.1 File des certifications (`/revisions`)

Réservée aux certificateurs et administrateurs.

Compteurs : Total, En attente, En cours, En retard. Filtres : recherche, statut, tri. Pagination.

Statuts d'une certification : **En attente**, **En cours**, **Validé**, **Renvoyé en correction**.

### E.2 Écran de certification d'un dossier (`/dossiers/{référence}/revision`)

La **grille de contrôle** : un point d'évaluation **par document** du dossier. Pour chacun, le
certificateur :

- prévisualise ou télécharge le document ;
- rend un **verdict** et peut laisser un **commentaire** ;
- une barre de progression indique combien de documents ont été évalués.

Trois actions : **enregistrer** (sans se prononcer), **valider la certification**, **renvoyer en
correction** (un motif est demandé et sera transmis au rédacteur).

**Point important** : si un document est **régénéré après avoir été évalué**, son verdict est
marqué périmé et remis à zéro. Le certificateur doit se prononcer à nouveau sur la nouvelle
version. L'ancien verdict reste affiché pour mémoire.

Un renvoi en correction remet le dossier à l'étape précédente et **notifie le rédacteur**, motif
inclus. Quand le dossier revient en certification, les verdicts précédents sont effacés : le
contrôle recommence à neuf.

---

## F. Formalités (`/formalites`)

Réservé aux formalistes et administrateurs.

**Six compteurs** : Total actives · À déposer · En cours · Retour reçu · Urgentes · Frais engagés
(en GNF).

**Filtres** : recherche (organisme, dossier), statut, tri. **Export CSV** de la liste.

**Statuts d'une formalité** :

| Statut | Signification |
|---|---|
| **À déposer** | La démarche n'est pas encore partie |
| **Déposé** | Remise à l'organisme effectuée |
| **En attente de retour** | L'organisme n'a pas encore répondu |
| **Retour reçu** | ✅ état terminal — la démarche est achevée |
| **Rejeté — à corriger** | L'organisme a refusé ; il faut corriger et redéposer |

**Actions** : « Déposer » (ouvre une fenêtre de saisie), « Retour reçu », téléverser une pièce
justificative, la télécharger, modifier, supprimer.

Une formalité en retard est signalée. L'écran permet de voir **les autres retards du même
organisme**, pour grouper les relances.

Les formalités sont **générées automatiquement** à partir des barèmes paramétrés qui portent la
mention « génère une formalité ».

---

## G. Facturation (`/facturation`)

Réservé aux comptables et administrateurs.

**Quatre compteurs** : Total facturé · Total encaissé · Solde restant dû · Dossiers impayés
(tous en GNF).

**Filtres** : recherche (référence, objet, n° de facture), statut, tri. Pagination.

La liste présente les notes de frais par dossier, avec leur statut de règlement. Le détail
(lignes, paiements, reçus) se traite depuis l'onglet Facturation du dossier concerné.

---

## H. GED — Gestion électronique des documents (`/ged`)

Vue transversale de **toutes les pièces, tous dossiers confondus**, classées selon les **mêmes
sept rubriques** que l'inventaire de clôture.

- Compteurs : dossiers affichés, pièces affichées, et un compteur par rubrique.
- Recherche par nom de pièce ; filtre par rubrique.
- Pour chaque pièce : **prévisualisation en place** (aperçu Word/Excel dans le navigateur) et
  téléchargement.

---

## I. Répertoire (`/repertoire`)

L'annuaire des personnes connues de l'étude. Chaque personne y apparaît avec :

- son **statut** : **Prospect** ou **Client** ;
- les **rôles** qu'elle a tenus dans les dossiers, avec un code couleur : Acheteur, Vendeur,
  Associé, Associé unique, Gérant, Héritier, Mandant, Mandataire, Donateur, Donataire, Époux,
  Épouse, Créancier, Débiteur, Bailleur, Locataire, Actionnaire, Administrateur, Membre,
  Liquidateur.

Une personne ajoutée pendant la création d'un dossier reste **prospect** tant que le dossier n'a
pas abouti ; elle devient **client** à la clôture du dossier.

---

## I bis. Sociétés (`/societes`)

Le **registre des sociétés** : distinct du Répertoire, qui liste les personnes.

Chaque ligne porte la dénomination, le sigle, la forme juridique, le numéro RCCM, le nombre de
dossiers ouverts sur cette société, et surtout son **statut** :

| Statut | Ce qu'il signifie |
|---|---|
| **Active** | Société en activité. |
| **En liquidation** | La dissolution a été prononcée ; les opérations de liquidation sont en cours. |
| **Liquidation clôturée** | Les comptes sont approuvés ; la radiation au RCCM reste à constater. |
| **Radiée** | La société a cessé d'exister. |

Le statut change **tout seul** quand un dossier de dissolution ou de clôture arrive à
l'Expédition — c'est-à-dire quand les formalités sont revenues et que la décision est opposable.
Une seule exception : la **radiation**, qui se constate à la main par le bouton « Radier », parce
qu'elle n'est prouvée que par la pièce délivrée par le greffe. Le bouton n'apparaît que sur une
liquidation clôturée.

### La colonne « Prochaine échéance »

Une liquidation dure des mois, parfois des années. Pendant ce temps il n'y a aucun dossier ouvert
pour le rappeler : c'est l'angle mort que cette colonne couvre. Elle affiche l'échéance la plus
proche (fin du mandat du liquidateur, clôture, radiation) et la passe en rouge si elle est
dépassée. Un courriel est envoyé chaque matin sur les échéances proches ou dépassées.

> ⚠️ **« délai à vérifier »** — cette mention apparaît sous chaque échéance, et elle est
> importante. Les délais utilisés (trois ans pour la clôture, un mois pour la radiation) sont
> des **hypothèses de travail** qui n'ont pas encore été validées par le notaire. Ils
> **n'empêchent jamais** d'avancer un dossier : ce sont des rappels, pas des règles. Dès que
> l'étude aura confirmé les délais réels, ils se corrigent dans les paramètres et la mention
> disparaît.

Deux filtres : la recherche (dénomination, sigle, RCCM, NIF) et le statut.

Ce que cet écran **ne fait pas** : il n'ouvre pas de fiche détaillée et ne permet pas de modifier
une société. La correction d'une fiche se fait depuis l'assistant de création de dossier, au
moment où l'on rattache la société.

---

## J. Anniversaires (`/anniversaires`)

Liste des anniversaires des clients à venir, pour permettre à l'étude d'entretenir la relation.

---

## K. Courriers (`/courriers`)

**Compteurs** : Total · Brouillons · Envoyés · Ce mois.

**Types de courrier** : Lettre de transmission · Convocation · Relance · Divers.
**Statuts** : brouillon, envoyé.

**Actions** : créer un courrier (destinataire, adresse ou email, objet, corps, dossier rattaché
facultatif), modifier, supprimer, prévisualiser, télécharger, téléverser la version signée.

Depuis un dossier, on peut **générer un courrier depuis un modèle de courrier**.

**Filtres** : recherche (référence, objet, destinataire), type, statut, tri.

Une référence de courrier se lit `COU-2026-0007`.

---

## L. Demandes clients — le recueil à distance

C'est le mécanisme qui permet au client de saisir lui-même ses informations, avant même que le
dossier existe.

### L.1 Liste des demandes (`/demandes`)

Réservée à ceux qui peuvent ouvrir un dossier.

**Créer une demande** : on choisit le type d'acte, le rôle que tiendra le client, et on saisit son
email. L'application produit un **lien unique**.

**Statuts d'une demande** : **En attente** (lien envoyé, rien reçu) · **Soumise** (le client a
répondu) · **Traitée** (convertie en dossier) · **Expirée**.

**Actions** : copier le lien, consulter la demande, **révoquer** le lien.

### L.2 Consultation d'une demande (`/demandes/{id}`)

- L'objet de la demande tel que le client l'a décrit.
- Les **informations soumises**.
- Le **document scanné** s'il y en a un.
- Le bloc **« Client identifié »** : rechercher une fiche client existante à laquelle rattacher la
  demande.
- Le bloc **« Créer le dossier »** : convertir la demande en dossier.

### L.3 Le formulaire client (lien public, sans connexion)

L'écran que voit le client : **« Compléter vos informations »**.

- Il décrit brièvement ce qu'il souhaite faire.
- Il remplit les champs correspondant à son rôle.
- Il peut **photographier sa pièce d'identité** : la **reconnaissance de caractères** en extrait
  automatiquement les informations, qu'il n'a plus qu'à vérifier.
- Les listes de lieux lui sont proposées, mais il **ne peut rien ajouter** au référentiel de
  l'étude.

L'accès est limité en fréquence pour prévenir les abus. À la soumission, **les notaires et
l'auteur du lien sont notifiés**.

---

## M. Modèles (`/modeles`)

Deux onglets : **Actes** et **Courriers**.

Pour chaque modèle : son nom, sa catégorie, son type, sa version, son statut (actif / inactif).

**Actions** : téléverser un modèle Word, le modifier, le **dupliquer**, le supprimer.

**Filtres** : recherche, catégorie, type, statut, tri.

Un modèle d'acte est un fichier Word dans lequel les informations du dossier sont marquées par des
**balises** de la forme `${nom_de_la_balise}`. Le dictionnaire complet des balises disponibles
(office, dossier, date, questionnaire) est un document séparé du projet.

Un même modèle peut servir **plusieurs types d'actes** et **plusieurs rôles**.

---

## N. Paramètres (`/parametres`) — administrateur uniquement

**Quatre compteurs** en tête : Utilisateurs actifs · Types actifs · Barèmes configurés ·
Types sans barème.

### N.1 Utilisateurs

Créer et modifier un compte : nom complet, email, mot de passe, **rôles** (plusieurs possibles),
initiales, téléphone, actif/inactif. Filtre Tous / Actifs / Inactifs.

### N.2 Types d'actes

Créer et modifier un type d'acte : libellé, catégorie, code (généré automatiquement), délai de
traitement en jours, description, actif/inactif.

**Vue « Processus »** : pour chaque type d'acte, quels modèles produiront quels actes, et dans
quelle variante.

**Documents attendus** : la liste des pièces qu'une procédure exige. Éditable par l'étude, et
**réinitialisable** à la configuration de référence à tout moment.

### N.3 Assignations

Les valeurs proposées par défaut lors de la création d'un dossier.

### N.4 Barèmes (`/parametres/baremes`)

Le cœur du calcul automatique de la note de frais. Un barème porte :

- le **type d'acte** concerné et l'**organisme** ;
- un **libellé** ;
- soit un **taux** (pourcentage), soit un **montant fixe** ; une quantité par défaut ;
- la **base de calcul** (l'assiette) ;
- une **condition** : certains barèmes ne s'appliquent qu'à un type de modification statutaire
  précis ;
- s'il **génère une formalité**, et si un **retour est attendu**, sous quel délai ;
- les **informations à saisir au retour** : RCCM, NIF, date d'immatriculation, journal et date de
  parution, n° de dépôt au greffe, n° de quittance. C'est ce réglage qui fait apparaître les
  champs correspondants dans la fenêtre « Retour reçu », et qui alimente la fiche société ;
- si sa ligne **peut recevoir une remise** — coché d'origine sur les honoraires de l'étude,
  décoché sur les débours, qu'elle reverse intégralement à un tiers ;
- les **pièces requises** pour la démarche ;
- une dépendance éventuelle à un autre barème.

> Les **informations à saisir au retour** et le **droit de remise** sont recopiés **au moment où la formalité ou la ligne est créée**.
> Modifier un barème ne réécrit donc pas les dossiers déjà ouverts.

L'**assiette** dépend de la catégorie d'acte :

| Catégorie | Assiette retenue |
|---|---|
| Vente | le prix de vente |
| Bail | le total des loyers sur la durée |
| Société | le capital social |
| Hypothèque | le montant du crédit |

### N.5 Lieux (`/parametres/lieux`)

Le référentiel Ville → Commune → Quartier.

- Ajouter, corriger, supprimer un lieu — **la suppression n'est possible que pour un lieu que rien
  ne référence**.
- **Valider** un lieu : les lieux amorcés automatiquement sont marqués « à vérifier » tant que
  l'étude ne les a pas confirmés.

### N.6 Apparence

- Identité de l'office : nom, sous-titre.
- **Logo** : téléversement et suppression.
- **Palette de couleurs** : couleur principale, couleur d'accent, fond ; avec un **aperçu en
  direct** et quatre palettes prédéfinies (Notarial Guinée, Bordeaux Royal, Vert Forêt,
  Marine Classique).

### N.7 Sécurité

- **Double authentification par email** : activation, et **durée de validité du code** en minutes.
- **Santé de la configuration** : un diagnostic de la configuration en place.

---

## O. Les notifications

L'application notifie **par email, par la cloche, et en temps réel**. Les événements couverts :

| Événement | Qui est notifié |
|---|---|
| Un dossier vous est assigné | l'utilisateur concerné |
| Une certification vous attend | le **certificateur** seul |
| Une certification a été validée | le notaire et le rédacteur |
| Une signature client est attendue | le notaire |
| Un dossier a été renvoyé en correction | le **rédacteur**, avec le motif |
| Des formalités sont à faire | le formaliste |
| Une formalité devient urgente | le formaliste |
| L'échéance d'un dossier approche | — |
| Une nouvelle demande client est arrivée | les notaires et l'auteur du lien |
| Une demande a été convertie en dossier | — |
| Votre code de connexion | l'utilisateur qui se connecte |

Le ciblage est volontairement étroit : une certification en attente ne part **qu'au
certificateur**, pour ne pas noyer le formaliste et le notaire sous des notifications sur
lesquelles ils n'ont rien à faire. De même, celui qui renvoie un dossier n'est pas notifié de son
propre geste.
