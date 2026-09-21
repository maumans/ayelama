# Prompt à coller dans Claude (chat)

> Copiez tout ce qui suit la ligne de séparation, après avoir joint les quatre fichiers du kit.

---

Tu vas m'aider à **adapter des modèles de documents notariaux** pour l'application **Ayelema**
(office notarial Ayelema BAH, Guinée).

## Le contexte

L'étude possède des modèles Word (`.docx`) d'actes notariaux, dans lesquels les informations
variables sont signalées par des pointillés, des mentions en MAJUSCULES ou des blancs. Mon travail
consiste à remplacer chacun de ces marqueurs par une **balise** `${nom.du.champ}` que l'application
remplira automatiquement avec les données du dossier.

J'ai déjà adapté une quinzaine de documents (la SARLU est complète). **Je vais t'en fournir
d'autres, un par un, et tu produiras leur version balisée.**

## Ce que tu as reçu

- `01-MOTEUR-DE-GENERATION.md` — comment l'application remplit un modèle, la syntaxe exacte, les
  variantes automatiques, les blocs répétables, les pièges
- `02-VARIABLES-DISPONIBLES.md` — **la liste exhaustive des balises réellement disponibles**, par
  questionnaire. Fichier généré depuis le code : il fait foi.
- `03-ETAT-DES-MODELES.md` — ce qui est déjà fait, ce qui reste, et les erreurs déjà commises
- `04-EXEMPLES-REELS.md` — des extraits avant/après des modèles déjà adaptés

## Les trois règles absolues

**1. N'invente jamais une balise.**
Une balise absente de `02-VARIABLES-DISPONIBLES.md` ne sera **pas remplie** : elle laissera un
blanc dans l'acte, sans message d'erreur. Six balises inventées se sont déjà glissées dans les
modèles en service pour cette raison exacte.

Si une information du document n'a aucun champ correspondant, **ne la balise pas**. Écris à la
place, dans ton rapport : `MANQUE : "<texte du document>" — aucun champ correspondant`. Je
déciderai d'ajouter le champ au questionnaire ou de retirer la mention.

**2. Les variantes automatiques obéissent à deux règles, et deux seulement.**
- Un champ **de type date** donne `${champ_jma}` et `${champ_lettres}`.
- Un champ **dont le nom finit par `_chiffres`** donne `${champ_lettres}` et `${champ_formate}`.

Rien d'autre. `${soc.nombre_parts_lettres}` n'existe pas, parce que le champ s'appelle
`soc.nombre_parts` et non `soc.nombre_parts_chiffres`. Vérifie la colonne « Dérivées » du fichier
`02` avant d'écrire une variante.

**3. Ne balise que ce qui varie.**
Le texte juridique, les numéros d'article, les références à l'Acte Uniforme OHADA, la forme
sociale quand le modèle lui est propre, la ponctuation notariale : tout cela reste en dur.

## Comment nous allons procéder

Pour **chaque document** que je te donne :

### Étape 1 — Tu me dis ce que tu comprends, avant de produire

- de quel **type d'acte** il s'agit, et donc **quel questionnaire** de `02` s'applique ;
- la liste des **marqueurs repérés** dans le document, chacun avec la balise que tu comptes
  employer ;
- la liste des marqueurs **sans champ correspondant** (`MANQUE : …`) ;
- les endroits où un **bloc répétable** te paraît nécessaire.

Tu t'arrêtes là et tu attends ma validation. Ne produis pas le document tant que je n'ai pas
confirmé le questionnaire retenu.

### Étape 2 — Après validation, tu produis le document balisé

Rends le **texte complet** du document, balisé, dans un bloc de code, paragraphe par paragraphe,
en conservant exactement la structure d'origine (titres, articles, numérotation, ponctuation).
Je le reporterai dans Word.

Puis, séparément :

- la **liste des balises employées**, pour que je puisse la passer au vérificateur ;
- les **points d'attention** (blocs répétables à placer manuellement, mise en forme particulière) ;
- les `MANQUE :` restants.

### Étape 3 — Je te reviens avec le résultat du contrôle

Je lance `php tools/verifier-balises.php` sur le fichier et je te donne sa sortie. Tu corriges ce
qu'il signale.

## Comment je te fournirai les documents

Selon ce qui est le plus pratique : le fichier `.docx` directement, une copie du texte, ou des
captures. Si le texte que je te donne est tronqué ou illisible par endroits, **dis-le et demande**
plutôt que de combler.

## Conventions de l'étude à respecter

- Un montant s'écrit `${x_lettres} (${x_chiffres})` suivi de la devise **en dur** dans le modèle
  (« FRANCS GUINÉENS ») — la variante `_lettres` ne porte jamais la devise.
- L'en-tête notarial se balise toujours entièrement avec les `${office.*}`, même quand la valeur
  en dur est correcte.
- La formule « L'AN … ; LE … » emploie `${annee_lettres}` puis `${date_acte_lettres}` (jour et
  mois seuls, sans répéter l'année).
- La civilité reste en dur (« Monsieur ») dans les modèles existants ; si tu la rends variable
  avec `${pp.civilite}`, **retire le mot**, ne l'empile pas.
- Pas de `.doc` : si le document d'origine est en `.doc`, je le convertis en `.docx` avant de
  téléverser. Signale-le-moi si tu le remarques.

---

Je te donne le premier document dans mon prochain message. Réponds simplement « prêt » après avoir
lu les quatre fichiers, en me disant en une phrase quel questionnaire tu penses devoir utiliser si
je te dis déjà de quel acte il s'agit.
