# 05 — Périmètre : ce qui n'existe pas, et ne doit pas être documenté

Ce document existe pour une seule raison : **empêcher le manuel de décrire des fonctions absentes**.
Un manuel qui promet ce que l'application ne fait pas coûte plus cher qu'un manuel incomplet — il
envoie l'étude chercher un bouton qui n'existe pas.

---

## 1. Fonctions absentes — ne pas en parler

| Fonction | État réel |
|---|---|
| **Signature électronique** | N'existe pas. Les actes se signent sur papier ; l'application enregistre seulement les **dates** de signature et accepte le téléversement du **scan** de l'acte signé. |
| **Export PDF d'un dossier complet** | N'existe pas. On télécharge les pièces une par une. Sont disponibles en PDF : la **fiche de recueil**, la **note de frais**, les **reçus** de paiement. |
| **Conversion serveur des actes en PDF** | N'existe pas et n'est pas prévue. L'aperçu Word/Excel se fait directement dans le navigateur ; le téléchargement rend le fichier Word d'origine. |
| **Grilles de certification personnalisées par type d'acte** | N'existe pas en tant qu'écran de configuration. La grille est aujourd'hui construite **à partir des documents du dossier** : un point de contrôle par document. Ne pas décrire d'écran « Paramètres > Grilles ». |
| **Versionnage des modèles** | Les **documents d'un dossier** sont versionnés (historique, restauration) — cela existe et se documente. Les **modèles** eux-mêmes ne le sont pas : remplacer un modèle n'en conserve pas l'historique. |
| **Bordereaux de paiement** | N'existe pas. |
| **Écran d'édition d'une fiche client depuis le répertoire** | Le répertoire est en **consultation**. La correction d'une fiche client se fait depuis le dossier, via le modal de la personne. |
| **Registre des sociétés comme écran autonome** | Le registre existe, mais il n'est atteignable que **depuis l'assistant de création de dossier** (recherche d'une société) et depuis la fiche d'une société. Il n'y a pas d'entrée « Sociétés » dans la navigation. |
| **Fiches de biens immobiliers** | N'existe pas. Les informations d'un bien vivent dans le questionnaire du dossier, pas dans une fiche réutilisable. |
| **Fiches de banques** | Le modèle existe en base mais **aucun écran ne le gère**. |
| **Tableaux répétables dans les modèles Word** | Les modèles remplacent des balises simples et savent inclure ou exclure des blocs, mais **ne savent pas encore dupliquer les lignes d'un tableau** (lignes de facture, titres fonciers multiples). |
| **Formulaires de saisie pour les courriers et les factures détaillées** | Les blocs de champs correspondants ne sont pas dans les questionnaires. |

---

## 2. Points à vérifier avant de rédiger le chapitre concerné

Ces fonctions **existent en partie** ; leur état exact doit être confirmé sur l'application réelle
avant d'écrire. Le rédacteur doit poser la question plutôt que de supposer.

| Point | Question à poser |
|---|---|
| **Paramètres > Apparence** | L'écran est-il pleinement opérationnel (logo, palette, aperçu) ? |
| **Paramètres > Assignations** | Que contient exactement cet onglet ? |
| **Anniversaires** | Y a-t-il une action possible (envoyer un message), ou seulement une liste ? |
| **Modèles de courriers** | Comment un modèle se rattache-t-il à un type d'acte ? |
| **Vue « Processus »** des types d'actes | Que montre-t-elle précisément, écran à l'appui ? |
| **Statuts de la note de frais** | Quelle est la liste exacte (payée, partielle, impayée…) ? |
| **Verdicts de certification** | Quels sont les libellés exacts des verdicts proposés au certificateur ? |
| **Formulaire client public** | Sur quelles pièces la lecture automatique fonctionne-t-elle réellement, et avec quelle fiabilité ? |

---

## 3. Le référentiel des lieux : un état de fait à expliquer, pas à cacher

**33 des 39 communes du référentiel n'ont aucun quartier.** Ce n'est pas un défaut : aucune liste
de quartiers n'était fiable au moment de l'amorçage, et une donnée inventée aurait été pire qu'une
donnée absente.

Conséquence pratique, à documenter clairement : **l'étude remplit le référentiel par l'usage**. Le
message « Aucun quartier au référentiel — ajoutez-le depuis le champ » n'est pas une erreur, c'est
une invitation. Le lieu ajouté est marqué **à vérifier** jusqu'à ce qu'un administrateur le valide
depuis Paramètres > Lieux.

Le manuel doit consacrer un encadré à ce point, sans quoi chaque utilisateur croira à un bug.

---

## 4. Le socle des modèles Word

Sur les modèles reçus par l'étude, **un seul est aujourd'hui entièrement balisé et sert de
référence** (`STATUTS_SARLU_balises.docx`). Les autres modèles bruts n'ont pas encore été convertis
au format à balises.

Le chapitre 17 doit donc être écrit comme un **mode d'emploi pour convertir un modèle**, pas comme
la description d'une bibliothèque déjà complète.

---

## 5. Règle de conduite pour le rédacteur

Si une fonction n'apparaît ni dans `02-REFERENTIEL-ECRANS.md` ni dans `03-REGLES-METIER.md`, elle
**n'existe pas**. Ne pas la déduire, ne pas l'annoncer comme « à venir », ne pas écrire « cette
fonctionnalité sera bientôt disponible ». Un manuel ne fait pas de promesses.
