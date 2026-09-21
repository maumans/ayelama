# 01 — Contexte produit : à qui s'adresse Ayelema, et pour quoi

## 1. Ce qu'est l'application

**Ayelema** est une application web de gestion des actes notariaux, conçue pour l'office notarial
Ayelema (Guinée). Elle couvre le **cycle de vie complet d'un dossier d'acte**, de l'ouverture à la
clôture : recueil des informations du client, production des actes depuis des modèles Word,
contrôle qualité obligatoire avant signature, suivi des démarches administratives, facturation,
archivage.

Elle s'utilise depuis un navigateur, sur ordinateur comme sur téléphone (l'interface a été
spécifiquement adaptée aux petits écrans).

### Les cinq objectifs que l'application poursuit

1. **Piloter** l'ensemble des dossiers en cours, avec une file de travail propre à chaque rôle.
2. **Standardiser** la production des actes : chaque procédure génère automatiquement les actes
   prévus, depuis des modèles Word paramétrés par l'étude.
3. **Rendre la certification obligatoire** : aucun acte ne peut être signé sans avoir été contrôlé
   pièce par pièce par un certificateur, qui peut le renvoyer en correction.
4. **Suivre les formalités** auprès des organismes guinéens (APIP, Impôts, Conservation foncière,
   CNSS) avec leurs délais, leurs montants et leurs retours.
5. **Tracer** : chaque geste sur un dossier est horodaté et attribué dans un journal d'activité.

## 2. Les six rôles

Un même utilisateur **peut porter plusieurs rôles** (un notaire est fréquemment aussi
certificateur). Les rôles se cumulent, ils ne s'excluent pas.

| Rôle | Libellé exact dans l'application | Ce qu'il fait |
|---|---|---|
| Clerc | **Clerc / Rédacteur** | Ouvre les dossiers, remplit les questionnaires, produit et corrige les actes |
| Certificateur | **Certificateur / Responsable** | Contrôle les actes un par un ; valide ou renvoie en correction |
| Notaire | **Notaire (Maître)** | Signe les actes après certification ; supervise ; peut aussi ouvrir des dossiers |
| Formaliste | **Formaliste** | Dépose les démarches auprès des organismes, enregistre les retours |
| Comptable | **Comptable** | Note de frais et encaissements, sur tous les dossiers |
| Administrateur | **Administrateur** | Comptes utilisateurs, types d'actes, modèles, barèmes, lieux, apparence, sécurité |

### Qui peut faire quoi

| Action | Rôles autorisés |
|---|---|
| Ouvrir un dossier | Clerc, Notaire, Administrateur |
| Certifier un dossier | Certificateur, Notaire, Administrateur |
| Signer | Notaire, Administrateur |
| Gérer les formalités | Formaliste, Notaire, Administrateur |
| Gérer la facturation | Comptable, Notaire, Administrateur |
| Accéder aux Paramètres | Administrateur uniquement |

Le **comptable est transversal** : il n'est pas assigné à un dossier, il voit la facturation de
tous. Le notaire et le certificateur, eux, sont **assignés nommément** à chaque dossier — et cette
assignation est une condition pour sortir de l'Initialisation.

## 3. Le vocabulaire — à respecter mot pour mot

Ces termes sont ceux affichés à l'écran. Le manuel ne doit pas en employer d'autres.

| Terme de l'application | Ce qu'il désigne | Ne pas dire |
|---|---|---|
| **Dossier** | L'unité de travail : un acte à produire pour un ou plusieurs clients | « affaire », « projet » |
| **Étape** | L'une des 7 positions du dossier dans le workflow | « statut », « phase » |
| **Certification des actes** | Le contrôle qualité bloquant avant signature | « révision », « relecture » |
| **Certificateur** | Celui qui exerce ce contrôle | « réviseur » |
| **Questionnaire** | La saisie structurée des informations du dossier | « formulaire » |
| **Partie** / **Personne au dossier** | Un intervenant à l'acte (associé, vendeur, gérant…) | « client » — voir ci-dessous |
| **Fiche client** | La fiche d'identité durable d'une personne, au répertoire | — |
| **Pièces** | Les justificatifs d'identité des personnes | « documents » — réservé aux actes |
| **Actes & documents** | Les fichiers produits ou versés au dossier | — |
| **Accord du client** | La pièce signée qui autorise à produire les actes | « bon pour accord » |
| **Fiche de recueil** | Le PDF récapitulatif du questionnaire, à faire signer au client | — |
| **Formalité** | Une démarche auprès d'un organisme | « démarche » seul |
| **Note de frais** | Le document chiffré remis au client | « facture » (le terme existe, mais désigne l'objet en base) |
| **Reçu** | Le justificatif d'un paiement encaissé | « quittance » |
| **Courrier** | Une lettre produite par l'étude (transmission, convocation, relance) | — |
| **Modèle d'acte** | Le gabarit Word à trous qui produit un acte | « template » |
| **GED** | La vue transversale de toutes les pièces, tous dossiers confondus | « archives » |
| **Répertoire** | L'annuaire des personnes connues de l'étude | « contacts », « CRM » |
| **Demande client** | Un lien envoyé à un client pour qu'il saisisse lui-même ses informations | « formulaire externe » |
| **Inventaire de clôture** | La liste des pièces à cocher une par une avant de clôturer | « checklist finale » |

### Distinction essentielle à faire comprendre au lecteur

- Une **fiche** (client, société, lieu) est une **référence vivante** : l'étude la corrige et la
  tient à jour. Corriger une fiche client répercute la correction sur les dossiers non clôturés
  qui s'en servent.
- Un **questionnaire** est un **instantané** : il a servi à produire des actes déjà signés, il ne
  se réécrit pas.

C'est le point que les utilisateurs comprennent le moins spontanément. Le manuel doit l'expliquer
tôt, et y revenir.

## 4. Les catégories d'actes couvertes

Dix catégories, chacune avec son préfixe de référence :

| Catégorie | Préfixe | Types d'actes disponibles |
|---|---|---|
| **Société** | SOC | Constitution SARLU, SARL, SA, SAS, SASU, SNC, GIE ; Dissolution ; **Modification de société** |
| **Vente d'immeubles** | VTE | Vente immobilière avec titre foncier ; sans titre foncier ; Cession de fonds de commerce ; Vente de véhicule |
| **Contrat d'hypothèque** | HYP | Constitution d'hypothèque ; Mainlevée |
| **Bail** | BAI | Bail à construction ; Bail commercial ; Bail d'habitation |
| **Donation** | DON | Donation simple ; Donation-partage |
| **Succession** | SUC | Déclaration de succession ; Partage successoral |
| **Contrat de mariage** | MAR | Contrat de mariage ; Convention de divorce |
| **Testament** | TES | — |
| **Procuration** | PRO | Procuration générale ; Procuration spéciale |
| **Prise en charge** | PEC | Mineur ; Adulte ; Engagement financier ; Scolaire |

Chaque type d'acte porte un **délai de traitement indicatif en jours** (de 2 jours pour une
procuration spéciale à 60 jours pour une déclaration de succession). Ce délai sert à calculer
l'échéance du dossier et à signaler les retards.

Une **référence de dossier** se lit `SOC-2026-0042` : préfixe de catégorie, année, numéro d'ordre.

## 5. Le cas particulier de la modification de société

C'est la procédure la plus riche de l'application, et celle qui mérite son propre chapitre :

- L'étude tient un **registre des sociétés**. Une modification s'ouvre soit sur une société déjà
  au registre (les informations se préremplissent), soit sur une société extérieure — et il faut
  alors verser son **dossier constitutif** (statuts en vigueur, RCCM) au registre.
- Un même dossier peut porter **plusieurs modifications simultanées** : changement de gérance,
  transfert de siège, augmentation ou diminution de capital, changement d'objet, cession de parts.
  Certaines combinaisons sont incompatibles et l'application les refuse.
- Les actes produits **dépendent des modifications retenues** : ce ne sont pas les mêmes documents
  pour un transfert de siège et pour une cession de parts.
- Le questionnaire compte **73 champs répartis en 15 sections**.
- Quand le dossier atteint l'Expédition, la **fiche de la société au registre est mise à jour**
  avec le nouveau siège, le nouveau capital, le nouvel objet ou le nouveau gérant — parce que la
  modification est alors enregistrée au RCCM, donc opposable.

## 6. Contraintes de saisie transverses

- **Dates** : saisie et affichage au format **JJ/MM/AAAA**. L'application contrôle la cohérence
  (une pièce d'identité ne peut pas être délivrée avant la naissance, ni expirer avant d'être
  délivrée ; une date d'acte ne peut pas être future).
- **Montants** : en **GNF**.
- **Lieux** : une cascade **Ville → Commune → Quartier**, alimentée par un référentiel que
  l'étude enrichit elle-même. Si un quartier manque, on l'ajoute depuis le champ. 33 des
  39 communes du référentiel n'ont encore aucun quartier : c'est normal et prévu.
- **Régime matrimonial et situation familiale** : listes fermées, pas de texte libre.
