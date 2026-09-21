# 03 — Règles métier : le workflow, les blocages, les calculs

Ce document rassemble **tout ce qui décide du comportement de l'application** : dans quel ordre les
choses se font, ce qui empêche d'avancer, et pourquoi. C'est la matière du chapitre le plus
important du manuel.

---

## 1. Le workflow en 7 étapes

```
Initialisation → Édition actes → Certification des actes ⭐ → Signature
   → Formalités → Expédition → Clôturé
```

Les étapes sont **séquentielles et bloquantes** : impossible d'atteindre la suivante sans avoir
satisfait les conditions de la précédente. Un dossier peut aussi être **renvoyé** à l'étape
précédente, avec un motif.

| Étape | Ce qu'on y fait | Ce qu'il faut pour en sortir |
|---|---|---|
| **Initialisation** | Constituer le dossier : questionnaire, personnes et leurs pièces d'identité, accord signé du client | Objet renseigné · Notaire assigné · Certificateur assigné · **Toutes** les pièces des personnes fournies · Accord du client téléversé · Règles légales de constitution satisfaites |
| **Édition actes** | Produire et corriger les actes | Au moins un acte produit |
| **Certification des actes** ⭐ | Contrôle qualité document par document | Certification **validée** par le certificateur |
| **Signature** | Constater les signatures | Les **deux** dates renseignées : client et notaire |
| **Formalités** | Démarches auprès des organismes | Toutes les formalités en **« Retour reçu »** |
| **Expédition** | Lettres de transmission | Inventaire de clôture **entièrement** vérifié |
| **Clôturé** | — | Étape terminale : le dossier est **figé** |

### Ce que l'application fait automatiquement à chaque passage d'étape

| En entrant dans… | L'application… |
|---|---|
| **Édition actes** | **produit les actes** depuis les modèles du type d'acte, et le consigne au journal |
| **Certification** | crée la fiche de certification et **notifie le certificateur** ; si le dossier revient d'un renvoi, **efface tous les verdicts précédents** |
| **Signature** | notifie le notaire et le rédacteur |
| **Formalités** | notifie le formaliste |
| **Expédition** | **génère les lettres de transmission** applicables · **met à jour la fiche de la société** au registre (nouveau siège, capital, objet ou gérant) |
| **Clôturé** | fait passer les personnes du dossier de **prospect** à **client** |

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
2. **La fiche dossier** — le panneau « conditions requises » de l'étape courante.
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
