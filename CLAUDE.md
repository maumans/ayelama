# Consignes de travail — Ayelema

Application d'étude notariale (Laravel 13 · Inertia · React 18 · Vite 8). Le devbook
([devbook.md](devbook.md)) porte l'état d'avancement et les décisions techniques numérotées.

---

## Règle 1 — Analyser en profondeur avant d'agir

**Toute demande porte sur un élément qui en touche d'autres.** Avant d'implémenter, chercher les
autres endroits où la même règle devrait valoir, les **mesurer**, et les traiter dans la même passe —
ou dire explicitement pourquoi non.

Cette règle n'est pas une précaution générale : elle vient de défauts réels, répétés.

> Trois divergences de suite sont nées de son oubli — `checkbox_group`, la cascade géo, puis
> `readonly` — chacune implémentée sur **un seul** des trois moteurs de rendu de questionnaire. Et
> le format des dates a produit cinq défauts distincts parce que la conversion avait été traitée à
> un endroit sur cinq.

### Les points de contrôle de ce dépôt

Quand une demande touche l'un de ces mécanismes, **les autres membres du groupe sont dans le
périmètre**, sans qu'il faille le demander :

| Si vous touchez… | Vérifiez aussi |
|---|---|
| un champ de questionnaire (type, contrôle, affichage) | `ChampQuestionnaire.jsx`, utilisé par `Dossiers/Create`, le modal de `Dossiers/Show` et `Intake/Show` |
| l'identité projetée dans `donnees` | `clientFields.js` **et** `ClientProjectionService.php` — miroirs assumés |
| la fiche société projetée dans `donnees` | `societeFields.js` **et** `Societe::versQuestionnaire()` / `depuisQuestionnaire()` / `completerDepuisQuestionnaire()` — **les deux sens** |
| une liste fermée (formes, régimes, situations) | la constante PHP **et** son miroir dans `questionnaires.js`, plus le test de parité |
| une conversion de format | **le sens inverse** existe presque toujours |
| une règle de validation de fiche | la même cohérence côté questionnaire, et réciproquement |
| un `match` exhaustif d'enum PHP | son équivalent JS, qui n'a **aucun** filet (`ETAPE_ORDER`, `ETAPE_TAB`, `getStepBlockers`) |

### Mesurer, pas supposer

Avant de concevoir, interroger la base réelle (`php artisan tinker`) : combien de lignes sont
concernées, sous quelle forme. Plusieurs décisions de ce projet ont changé après mesure — 33 des 39
communes sans quartier, quatre orthographes pour deux régimes, sept dates au jour et au mois
inversés.

### Distinguer référence vivante et instantané

Une **fiche** (client, société, lieu) est une référence que l'étude tient à jour : elle se corrige et
se normalise. Un **questionnaire** est un instantané qui alimente des actes déjà produits : il ne se
réécrit pas — sauf défaut de sérialisation, où la forme change sans que la valeur bouge.

### Ce qu'on ne devine pas

Le droit guinéen, les listes de quartiers, les régimes praticables. Une donnée non garantie est
marquée `a_verifier` et déclarée **à un seul endroit**, corrigeable en une ligne. Une contrainte
inventée est pire qu'une contrainte absente.

---

## Règle 2 — Rendre les défauts bruyants

Un défaut silencieux coûte plus que son équivalent visible. Préférer systématiquement :

- un `match` exhaustif sans branche par défaut, qui échoue à l'ajout d'un cas d'enum ;
- un **test de parité** quand une duplication est inévitable (PHP/JS) ;
- un garde-fou exécutable plutôt qu'une convention en commentaire — `FormatsDatesTest` interdit une
  date ISO dans `donnees`, `ListesMatrimonialesTest` interdit une constante utilisée avant sa
  déclaration ;
- un blocage **énuméré** plutôt qu'un bouton grisé (`blocantsEtape.js`).

⚠️ **Ce que la construction ne rattrape pas** : `vite build` ignore les erreurs de portée et de zone
morte temporelle. Une constante déclarée sous son usage passe la construction et rend une page
blanche. Après un changement JS structurel, charger réellement le module.

---

## Règle 3 — Écrire le pourquoi, pas le quoi

Les commentaires de ce dépôt disent ce qui a été essayé, ce qui a cassé, et ce qui est délibérément
laissé de côté. Les conserver et les **corriger quand ils deviennent faux** : deux commentaires
affirmaient que `donnees` portait des dates ISO, ce qui a propagé le défaut plutôt que de l'arrêter.

Toute décision structurelle rejoint le tableau des décisions de [devbook.md](devbook.md).

---

## Contraintes d'environnement

- **Windows** : pas de `pcntl` (donc pas de `php artisan pail`) ; `@rolldown/binding-win32-x64-msvc`
  installé à la main.
- **Ne jamais supprimer `node_modules/.vite` pendant que le serveur de développement tourne** — il ne
  recrée son cache qu'au démarrage, et tout casse en 504 jusqu'au redémarrage.
- **MySQL en développement, SQLite en test** : une migration doit satisfaire les deux, dont l'ordre
  des contraintes diffère.
- `git stash` est **proscrit** : l'arbre de travail porte en permanence du travail non commité.
- Les dates : `JJ/MM/AAAA` dans `donnees`, ISO dans les colonnes castées — le contrat est en tête de
  [resources/js/lib/dates.js](resources/js/lib/dates.js).
