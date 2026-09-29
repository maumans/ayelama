# 03 — Règles métier : le workflow, les blocages, les calculs

Ce document rassemble **tout ce qui décide du comportement de l'application** : dans quel ordre les
choses se font, ce qui empêche d'avancer, et pourquoi. C'est la matière du chapitre le plus
important du manuel.

---

## 1. Le workflow en 7 étapes

```
Initialisation → Édition actes → Certification des actes ⭐ → Signature
   → Formalités → Expédition → Archivé
```

Les étapes sont **séquentielles et bloquantes** : impossible d'atteindre la suivante sans avoir
satisfait les conditions de la précédente. Un dossier peut aussi être **renvoyé** à l'étape
précédente, avec un motif.

| Étape | Ce qu'on y fait | Ce qu'il faut pour en sortir |
|---|---|---|
| **Initialisation** | Constituer le dossier : questionnaire, personnes et leurs pièces d'identité, accord du client | Objet renseigné · Notaire assigné · Certificateur assigné · **Toutes** les pièces des personnes fournies · Pièce d'accord téléversée **si le type d'acte l'exige** (voir §2 bis) · Règles légales satisfaites |
| **Édition actes** | Produire et corriger les actes | Au moins un acte produit |
| **Certification des actes** ⭐ | Contrôle qualité document par document | Certification **validée** par le certificateur |
| **Signature** | Constater les signatures | Les **deux** dates renseignées : client et notaire |
| **Formalités** | Démarches auprès des organismes | Toutes les formalités en **« Retour reçu »** |
| **Expédition** | Lettres de transmission | Inventaire de clôture **entièrement** vérifié |
| **Archivé** | — | Étape terminale : le dossier est **figé** |

### Ce que l'application fait automatiquement à chaque passage d'étape

| En entrant dans… | L'application… |
|---|---|
| **Édition actes** | **produit les actes** depuis les modèles du type d'acte, et le consigne au journal |
| **Certification** | crée la fiche de certification et **notifie le certificateur** ; si le dossier revient d'un renvoi, **efface tous les verdicts précédents** |
| **Signature** | notifie le notaire et le rédacteur |
| **Formalités** | notifie le formaliste |
| **Expédition** | **génère les lettres de transmission** applicables · **met à jour la fiche de la société** au registre (nouveau siège, capital, objet ou gérant) |
| **Archivé** | fait passer les personnes du dossier de **prospect** à **client** |

**Point à expliquer au lecteur** : les actes sont produits à l'entrée en Édition, **pas à la
création du dossier**. Raison : ils doivent refléter un questionnaire que le client a validé par
son accord signé. Et la génération **n'écrase jamais un acte déjà présent** — un renvoi en
correction repasse par là, et les corrections manuelles doivent survivre.

---

## 2. Les blocages, énumérés et non devinés

C'est un parti pris constant de l'application, et un argument de vente à mettre en avant dans le
manuel : **quand quelque chose est impossible, l'application dit quoi et pourquoi**, elle ne se
contente pas de griser un bouton.

Cela vaut à trois endroits :

1. **L'assistant de création** — le panneau qui liste, pour chaque champ manquant, sa section, son
   libellé et la raison.
2. **La fiche dossier** — le panneau « conditions requises » de l'étape courante. Depuis le
   28/09/2026, il affiche **aussi les règles de droit** (capital minimum, commissaire aux
   comptes, cohérence d'une dissolution avec le registre des sociétés) : elles étaient
   auparavant invisibles jusqu'au clic sur « Étape suivante ».
3. **Les messages de refus** — identiques à ce qu'affiche l'écran.

### Messages de blocage, mot pour mot

| Situation | Message |
|---|---|
| Objet vide | « L'objet du dossier doit être renseigné. » |
| Notaire non assigné | « Un notaire doit être assigné au dossier. » |
| Certificateur non assigné | « Un certificateur doit être assigné au dossier. » |
| Pièces manquantes | « Toutes les pièces justificatives des personnes au dossier doivent être fournies. » |
| Accord absent | « *[nom de la pièce attendue]* doit être téléversé. » |
| Aucun acte produit | « Au moins un acte doit avoir été produit avant de passer à la certification. » |
| Certification renvoyée | « La certification a été renvoyée en correction. Corrigez les points signalés puis soumettez à nouveau. » |
| Certification en attente | « La certification est en attente. Elle doit être évaluée et validée par le certificateur. » |
| Certification en cours | « La certification est en cours. Elle doit être validée avant de continuer. » |
| Date de signature client absente | « La date de signature du client doit être renseignée avant de passer aux formalités. » |
| Date de signature notaire absente | « La date de signature du notaire doit être renseignée avant de passer aux formalités. » |
| Formalités non achevées | « Toutes les formalités doivent avoir reçu leur retour avant de passer à l'expédition. » — suivi de deux listes distinctes : **« À corriger et redéposer : … »** (les rejets, qui demandent une action du formaliste) et **« En attente de retour : … »** (qui ne dépendent que de l'organisme) |

Dans l'assistant de création, les raisons par type de champ :

| Type de champ vide | Raison affichée |
|---|---|
| Groupe de cases à cocher | « Cochez au moins une option » |
| Confirmation obligatoire | « Cette confirmation est obligatoire » |
| Liste déroulante | « Choisissez une valeur » |
| Date | « Renseignez une date » |
| Commune, parent non choisi | « Choisissez d'abord la ville » |
| Quartier, parent non choisi | « Choisissez d'abord la commune » |
| Lieu sans option au référentiel | « Aucune commune au référentiel — ajoutez-la depuis le champ » (idem pour un quartier) |
| Autre | « Champ obligatoire » |

L'objet du dossier exige **au moins 10 caractères**.

---

## 2 bis. La pièce d'accord de l'Initialisation

Avant de rédiger un acte authentique, l'étude veut une trace écrite de ce qui l'engage. Mais
**cette trace n'est pas la même selon le type d'acte**, et depuis le 28/09/2026 l'application le
dit explicitement.

| Type de dossier | Pièce attendue | Exigence |
|---|---|---|
| Constitution de société, et tous les autres types | **Accord client — questionnaire signé** : l'étude imprime la fiche du dossier, la fait signer, la téléverse | Obligatoire |
| Modification de statuts | **Décision d'assemblée des associés**, apportée par le client — convocation, projet de résolution ou procès-verbal | Obligatoire |
| **Dissolution et liquidation** | **Décision de dissolution**, si le client en apporte une | **Recommandée** — n'empêche pas d'avancer |

**Pourquoi la dissolution ne bloque pas.** Ce qui engage une dissolution est la décision des
associés ; le procès-verbal, lui, c'est l'étude qui le rédige — il n'arrive donc qu'à l'Édition,
trop tard pour conditionner l'Initialisation. Surtout, l'application **contrôle déjà la
substance** de cette décision de façon bloquante : la date de l'assemblée et le nom du
liquidateur sont obligatoires (voir la section Dissolution). Exiger en plus la pièce écrite
n'ajouterait pas de sécurité juridique, seulement une preuve.

> ⚠️ **Ce réglage est provisoire.** Aucun document de référence versé au projet ne dit ce qu'une
> dissolution requiert à cette étape. La carte du dossier affiche la mention « à confirmer avec
> l'étude », et elle disparaîtra dès que le notaire aura tranché.

### Trois niveaux, modifiables sans développement

Dans **Paramètres → Types d'actes**, la colonne « Accord initial » permet de choisir, pour
chaque type :

- **Obligatoire** — le dossier ne quitte pas l'Initialisation sans la pièce ;
- **Recommandée** — la pièce est annoncée et signalée manquante, mais le dossier avance ;
- **Sans objet** — aucune pièce n'est demandée.

Laisser « Référence » suit ce que l'application déclare par défaut. Un réglage différent affiche
un badge « écart à la référence » : ce n'est pas une faute, mais cela doit se voir, puisque
c'est cette ligne qui décide si un dossier avance.

> ⚠️ **Durcir une exigence bloque rétroactivement.** Passer un type d'« Recommandée » à
> « Obligatoire » immobilise aussitôt les dossiers de ce type déjà en cours qui n'ont pas la
> pièce. L'écran affiche leur nombre avant l'enregistrement.

Une pièce déjà déposée reste toujours consultable et téléchargeable, quel que soit le réglage :
« Sans objet » supprime la *demande*, jamais un document classé.

---

## 3. Les règles de droit appliquées automatiquement

Issues du corpus de règles de gestion de l'étude. Elles **bloquent la sortie d'Initialisation** au
même titre que l'accord du client : produire les statuts d'une SA sous-capitalisée exposerait
l'office.

### Constitution de société

| Règle | Comportement |
|---|---|
| **Capital minimum** | Une **SA** (et une SAU) exige un capital minimum — paramétrable, par défaut 140 000 000 GNF. SARL, SARLU, SAS, SASU, SNC, SCS : pas de minimum. Le **GIE** peut être créé sans capital. Message : « Capital insuffisant : une *forme* exige au moins *X* GNF (capital saisi : *Y* GNF). » |
| **Associé unique** | Les formes unipersonnelles sont **SASU, SARLU et SAU**. Une forme unipersonnelle avec plusieurs associés est refusée ; une forme pluripersonnelle avec un seul associé aussi. |
| **Commissaire aux comptes** | Obligatoire en **SA** : « Un commissaire aux comptes titulaire est obligatoire pour une SA. » |
| **Dénomination** | Contrôle d'unicité contre le registre des sociétés, après normalisation. |
| **Mineurs** | Un mineur **ne peut pas** être associé d'une forme à responsabilité illimitée et solidaire. Un associé mineur doit avoir un **représentant légal** renseigné, avec sa qualité, sur la fiche client. |

### Modification de société

| Règle | Comportement |
|---|---|
| **Type obligatoire** | « Le type de modification doit être précisé : il détermine les actes à produire, les formalités à engager et les droits à percevoir. » |
| **Modifications incompatibles** | Certaines paires ne peuvent pas être décidées par la même assemblée. L'assistant grise les cases exclues, et la règle est **aussi** appliquée côté serveur. Message : « *A* et *B* ne peuvent pas être décidées par la même assemblée. *motif* » |
| **Société désignée** | « La société à modifier doit être désignée : choisissez-la dans le registre, ou renseignez au moins sa dénomination. » |
| **Pièces constitutives** | Pour une société hors registre, son dossier constitutif (statuts en vigueur, RCCM) doit être versé. |
| **Diminution de capital** | Le montant de la réduction doit être renseigné. |

Les **neuf types de modification** disponibles :

| Type | Touche les statuts ? |
|---|---|
| Modification de dénomination | oui |
| Modification de forme juridique | oui |
| Changement de gérant statutaire | oui |
| **Changement de gérant non statutaire** | **non** — seul le RCCM enregistre le changement |
| Transfert du siège social | oui |
| Augmentation de capital | oui |
| Diminution de capital | oui |
| Cession de parts sociales | oui |
| Modification de l'objet social | oui |

Un dossier peut en porter **plusieurs à la fois** : une même assemblée décide couramment une
cession de parts, un nouveau gérant et un transfert de siège. Les documents produits sont l'union
de leurs exigences, et **un seul procès-verbal** est établi.

Les actes d'un dossier de modification apparaissent dans l'ordre où l'étude les rédige :
**acte de cession → procès-verbal → DNSV → statuts mis à jour → déclaration RCCM**.

### Dissolution et liquidation

Une dissolution-liquidation se traite en **deux dossiers successifs**, et non en un seul :

1. **Dissolution anticipée** — l'assemblée prononce la dissolution et nomme le liquidateur.
2. **Clôture de la liquidation** — souvent des mois ou des années plus tard, une seconde
   assemblée approuve les comptes du liquidateur et lui donne quitus.

Ce sont deux prestations distinctes, facturées séparément. Le champ **« Phase de la procédure »**
dit lequel des deux on ouvre : il commande les actes produits et le statut porté à la fiche
société. Les champs de l'autre phase restent masqués.

| Règle | Comportement |
|---|---|
| **Phase obligatoire** | « La phase doit être précisée : dissolution anticipée ou clôture de la liquidation. » |
| **Société désignée** | Comme pour une modification : choisie dans le registre, ou dénomination saisie. |
| **Cohérence avec le registre** | On ne clôture pas la liquidation d'une société active, et on ne dissout pas deux fois. Le message dit quoi faire — par exemple : « La société X est déjà en liquidation depuis le 15/03/2026. Pour en clôturer la liquidation, ouvrez un dossier en phase Clôture de la liquidation. » |
| **Date de l'assemblée** | Obligatoire, propre à chaque phase. C'est elle qui date l'acte et fait courir le suivi au registre. |
| **Liquidateur** | Obligatoire en phase 1 seulement. En phase 2 il est déjà en fonction et figure au registre. |

Le **liquidateur** se saisit comme un gérant : civilité, état civil complet et références de sa
pièce d'identité. C'est lui qui représente la société pendant toute la liquidation et qui signe
les actes.

> ⚠️ **Aucune pièce justificative ne lui est demandée**, contrairement à un associé ou à un
> gérant : son rôle est classé parmi ceux dont la désignation par l'assemblée fait foi. Ses
> références d'identité sont donc **saisies**, pas **téléversées**, et rien ne bloque le dossier
> si l'étude ne détient pas sa CNI. Point à reconfirmer avec l'étude — le placeholder du champ
> « qualité » dit « Associé / Tiers désigné », donc le cas courant n'est pas un professionnel
> agréé.

> ⚠️ **Aucun délai n'est bloquant.** La durée du mandat du liquidateur, la clôture sous trois
> ans, la radiation sous un mois : ces délais circulent mais n'ont pas encore été confirmés par
> le notaire. L'application les **signale** sur l'écran Sociétés et par courriel, toujours
> accompagnés de la mention « délai à vérifier », et **n'empêche jamais** un dossier d'avancer.
> Une contrainte inventée coûterait plus cher qu'une contrainte absente.

**Ce que l'application ne sait pas encore faire**, et qui attend l'étude :

- les **modèles Word** des quatre actes (acte de dissolution, insertion au journal, PV de
  clôture, déclaration de radiation) n'ont pas été fournis. Les dossiers les annoncent comme
  « attendus — gabarit manquant » plutôt que de rester muets ;
- la **grille tarifaire** d'une dissolution n'est pas connue. Un dossier de dissolution a donc
  une facture presque vide et aucune formalité automatique. Les tarifs de constitution qui s'y
  appliquaient par erreur — dont une « Immatriculation RCCM » facturée sur une société qu'on
  radie — ont été retirés.

---

## 4. Les pièces d'identité : une propriété de la personne, pas du dossier

Quand une personne rattachée à une **fiche client** a déjà fourni une pièce dans un autre dossier,
l'application la **propose à la reprise** plutôt que de la redemander, avec la **date du dépôt
d'origine** — une pièce d'identité a une durée de validité, et reprendre un scan de trois ans sans
le regarder serait pire que de le redemander.

Le rapprochement se fait **exclusivement sur la fiche client**. Deux homonymes ne sont pas la même
personne, et une personne sans fiche client ne se voit rien proposer.

---

## 5. La certification : ce qui la rend sérieuse

- Un **point de contrôle par document**. Le certificateur se prononce sur chacun.
- **Un document régénéré perd son verdict.** Si le questionnaire change et que l'acte est reproduit
  après avoir été validé, le verdict devient périmé : le certificateur doit se prononcer à nouveau
  sur la nouvelle version. L'ancien verdict reste visible pour mémoire.
- **Un renvoi efface tout.** Quand le dossier revient en certification après correction, les
  verdicts précédents sont remis à zéro. Le contrôle recommence à neuf.
- Le **motif du renvoi** est obligatoire et transmis au rédacteur par notification.

---

## 6. La facturation

La note de frais est calculée depuis les **barèmes** paramétrés par l'administrateur. Chaque barème
applique soit un **taux** sur une assiette, soit un **montant fixe**, éventuellement multiplié par
une quantité.

L'**assiette** est déduite du questionnaire selon la catégorie :

| Catégorie | Assiette |
|---|---|
| Vente | prix de vente |
| Bail | total des loyers sur la durée (loyer mensuel × 12 × durée en années) |
| Société | capital social |
| Hypothèque | montant du crédit |

Un barème peut être **conditionné à un type de modification statutaire** : la DNSV ne se facture
pas sur un transfert de siège, l'enregistrement de statuts mis à jour ne se facture pas sur une
constitution.

**Règle de sécurité** : le total des paiements enregistrés **ne peut pas dépasser** le total
facturé.

Chaque paiement peut donner lieu à un **reçu** téléchargeable. La note de frais elle-même se
télécharge en PDF.

---

## 6 bis. Les remises sur facture

Une ligne de facture peut recevoir une remise, **si sa nature le permet**.

### Toutes les lignes ne se remisent pas

C'est la règle de fond, et elle n'est pas technique. Une note d'étude mélange deux choses :

- les **honoraires** — ce que l'étude facture pour son travail. Une remise y réduit sa marge,
  c'est un geste commercial qu'elle décide ;
- les **débours** — impôts, greffe, APIP, conservation foncière, journal d'annonces légales.
  L'étude **avance** cet argent pour le client et le reverse intégralement. Remiser un débours
  ne réduit aucune marge : cela fait perdre de l'argent réel.

L'application distingue donc les deux. Dans **Paramètres → Barèmes**, chaque ligne porte une
case « Cette ligne peut recevoir une remise ». Elle est cochée d'origine sur les honoraires de
l'étude, décochée sur tout le reste. Vous pouvez l'ouvrir au cas par cas.

Sur une ligne non remisable, le formulaire l'explique au lieu de masquer l'option — et le
serveur refuse aussi, pas seulement l'écran.

### Deux façons de saisir la même remise

Dans la fiche dossier, onglet Facturation, le bouton ✏️ d'une ligne ouvre sa modification. Le
bloc « Remise » propose **deux champs liés** :

| Vous tapez | L'autre se remplit |
|---|---|
| **450 000** en montant | **10 %** apparaît à côté |
| **10** en pourcentage | **450 000 GNF** apparaît à côté |

Le total de la ligne, celui de la facture et le solde restant se recalculent aussitôt. La
remise porte sur le **montant brut**, quantité comprise : 10 % sur trois exemplaires à 100 000
font 30 000, pas 10 000.

> **Ce qui est retenu, c'est la forme que vous avez choisie.** Si le tarif du barème change
> plus tard, une remise exprimée en **pourcentage** suit le nouveau montant — 10 % restent
> 10 % — tandis qu'une remise en **montant** reste à sa valeur. Les deux comportements sont
> légitimes, c'est à vous de choisir lequel s'applique.

### Les garde-fous

- Une remise ne peut dépasser ni 100 %, ni le montant de la ligne.
- Une facture sur laquelle un **paiement** a déjà été encaissé n'accepte plus de modification
  de ligne, remise comprise — sinon le total pourrait passer sous ce qui a déjà été reçu.
- Si un tarif baisse après coup et rend une remise en montant plus grande que sa ligne, elle
  est **plafonnée** pour que le total ne devienne jamais négatif, et l'écart est **signalé**.
  L'application ne corrige pas seule une décision commerciale.
- Chaque remise est **journalisée** avec ses deux formes et son auteur.

### Sur la facture et sur le PDF

La remise apparaît **sur la ligne concernée** : le montant d'origine barré, la remise, puis le
net. Le client voit ainsi sur quelle prestation le geste a été fait, pas seulement qu'il y en a
eu un. Le pied de facture ajoute deux lignes — « Sous-total avant remise » et « Remises
accordées » — **uniquement** s'il y a une remise : une facture sans remise garde exactement
l'allure qu'elle avait.

### Régénérer une facture ne perd pas les remises

L'application recalcule parfois une facture entière — quand l'assiette change, par exemple. Les
remises accordées sont **retrouvées et réappliquées**, ligne par ligne.

---

## 7. Les formalités

Elles sont **générées automatiquement** depuis les barèmes qui portent la mention « génère une
formalité ». Chacune connaît son organisme, son montant, les pièces requises, et le délai sous
lequel un retour est attendu.

Cycle : **À déposer → Déposé → En attente de retour → Retour reçu**, avec une branche
**Rejeté — à corriger** qui ramène au dépôt.

« Retour reçu » est l'**état terminal** : recevoir le retour de l'organisme *est* l'aboutissement
de la démarche. Le dossier ne peut pas passer en Expédition tant qu'une formalité n'y est pas.

Une formalité dont le délai est dépassé est signalée comme **urgente**, et le formaliste est
notifié. L'écran permet de voir **les autres retards du même organisme**, pour grouper les
relances.

---

## 7 bis. Le retour d'une formalité : les documents **et** les informations

Quand une démarche revient de l'organisme, le formaliste enregistre le retour. Depuis le
29/09/2026, ce formulaire fait trois choses au lieu d'une.

**Il dit ce qu'on attend.** En tête, « Ce que l'organisme doit rendre : Extrait RCCM définitif
et attestation NIF ». Cette information existait en base depuis juin, sans être affichée nulle
part.

**Il capte les informations, pas seulement les fichiers.** L'APIP délivre un numéro RCCM, un
NIF, une date d'immatriculation ; le greffe un numéro de dépôt ; les Impôts une quittance. Ces
valeurs se saisissent au retour et sont **aussitôt portées à la fiche société**.

> **Pourquoi c'est important.** Avant, ces numéros se perdaient : ils figuraient sur le
> document reçu, on classait le document, et personne ne les enregistrait. Résultat mesuré
> avant la correction — **12 numéros sur 14 n'atteignaient jamais le registre**. Le dossier
> suivant de la même société (une modification, une dissolution) obligeait donc à les retaper,
> alors que l'étude les détenait depuis des semaines. Maintenant, le champ « Numéro RCCM » d'un
> dossier de modification est **prérempli et masqué** : il n'y a plus rien à saisir.

**Aucune information n'est obligatoire.** L'APIP peut rendre un extrait sans le NIF : on saisit
ce qui est là, le reste est signalé mais n'empêche jamais d'avancer.

### Trois cas au moment d'enregistrer

| Situation | Ce que fait l'application |
|---|---|
| La fiche est vide | Elle est complétée, et le journal du dossier le dit |
| La fiche porte déjà la même valeur | Rien — réenregistrer un retour ne double jamais rien |
| La fiche porte une valeur **différente** | ⚠️ Elle **n'est pas modifiée**. L'écart est signalé, et vous arbitrez depuis le registre des sociétés |

Le troisième cas est délibéré : deux vérités contradictoires sur l'identité légale d'une
société ne se résolvent pas toutes seules.

### Le retour rejeté

Le formulaire permet de nouveau d'enregistrer un **rejet**, avec son motif. La démarche repasse
alors « à corriger et redéposer », l'étape Formalités reste bloquée, et les démarches qui en
dépendent le restent aussi. Le motif est obligatoire — sans lui, « à corriger » ne dit pas quoi
corriger.

Les pièces ne sont pas exigées sur un rejet : par définition, l'organisme ne les a pas rendues.

### Ce que cela ne fait pas

Les actes déjà produits ne changent pas. Ils sortent à l'**Édition**, la formalité revient à
l'étape **Formalités** — deux étapes plus tard : une insertion au journal produite en Édition
ne peut pas porter un numéro RCCM qui n'existait pas encore. Le journal du dossier vous prévient
que des actes sont à régénérer si l'un d'eux cite ces informations. L'application ne les
régénère **pas** toute seule : cela écraserait vos corrections manuelles.

### Les sociétés déjà immatriculées sans leur numéro

L'écran **Sociétés** signale, par un badge ambre, les fiches dont un dossier a dépassé les
formalités mais dont le RCCM manque au registre — cinq aujourd'hui. Aucun rattrapage
automatique n'était possible (les références enregistrées à l'époque sont des valeurs de test) :
une saisie sur la fiche suffit, et le mécanisme empêche que cela se reproduise.

---

## 8. La clôture

Il n'y a **pas de configuration « documents obligatoires par type d'acte »**. À la place, un
**contrôle humain explicite sur l'inventaire réel** : toutes les pièces effectivement présentes au
dossier, classées en sept rubriques, à cocher une par une (ou rubrique entière).

Le raisonnement à transmettre au lecteur : le workflow produit lui-même toutes les pièces, mais
elles viennent d'origines hétérogènes — actes, CNI, retours d'organismes, courriers, reçus —
qu'aucune règle automatique ne peut déclarer complètes.

La rubrique d'une pièce **se déduit de son origine** dans le workflow ; elle ne se déclare pas.

**Un dossier clôturé est figé.**

---

## 9. Les identités : fiche vivante, questionnaire figé

- Corriger une **fiche client** répercute l'identité corrigée sur **les dossiers non clôturés** qui
  la référencent.
- Un **questionnaire** de dossier clôturé ne bouge pas : il a servi à produire des actes déjà
  signés.
- Il en va de même pour la **fiche société** : elle est mise à jour à l'Expédition d'une
  modification, quand celle-ci devient opposable, pas avant — sinon le prochain dossier se
  préremplirait avec un état qui n'est pas encore acquis.

---

## 10. Contrôles de cohérence sur les dates

Appliqués à la saisie, dans l'assistant comme sur la fiche :

- une pièce d'identité ne peut pas être **délivrée avant la naissance** ;
- elle ne peut pas **expirer avant d'avoir été délivrée** ;
- certaines dates ne peuvent pas être **dans le futur**.

Quelques dates d'acte sont volontairement **laissées libres** (date d'assemblée de dissolution,
date d'acte d'hypothèque, date de cession, date de cessation de fonctions du gérant sortant) :
la règle de droit applicable n'est pas encore arrêtée, et une contrainte inventée serait pire
qu'une contrainte absente.

---

## 11. Le journal d'activité

Chaque dossier porte un journal horodaté et nominatif. Y sont consignés, entre autres :

- les changements d'étape (« Dossier avancé de *X* à *Y* ») ;
- les renvois, **avec leur motif** ;
- la génération des actes (« *N* acte(s) généré(s) depuis les modèles du type d'acte ») ;
- la génération automatique des lettres de transmission.

Il s'ouvre depuis le bouton « Historique du dossier » de la fiche.
