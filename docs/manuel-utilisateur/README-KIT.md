# Kit de rédaction — Manuel utilisateur Ayelema

Ce dossier contient **tout ce qu'il faut transmettre à Claude (chat)** pour qu'il rédige un
manuel utilisateur professionnel d'Ayelema, sans rien inventer et sans rien oublier.

## Ce que vous faites, dans l'ordre

1. Ouvrez une **nouvelle conversation** Claude (pas une conversation déjà chargée d'autre chose).
2. **Joignez les 5 fichiers** de ce dossier listés ci-dessous (glisser-déposer).
3. **Collez le contenu de [`00-PROMPT-CLAUDE.md`](00-PROMPT-CLAUDE.md)** comme premier message.
4. Claude produit le manuel **chapitre par chapitre** (le prompt le lui impose — un manuel complet
   en une seule réponse serait tronqué).

## Les fichiers à joindre

| Fichier | Rôle | Indispensable ? |
|---|---|---|
| [`01-CONTEXTE-PRODUIT.md`](01-CONTEXTE-PRODUIT.md) | Qui utilise l'application, pour quoi, avec quel vocabulaire | ✅ Oui |
| [`02-REFERENTIEL-ECRANS.md`](02-REFERENTIEL-ECRANS.md) | Écran par écran : ce qui s'affiche, ce qu'on peut faire | ✅ Oui |
| [`03-REGLES-METIER.md`](03-REGLES-METIER.md) | Workflow, blocages, droits, calculs, notifications | ✅ Oui |
| [`04-PLAN-DU-MANUEL.md`](04-PLAN-DU-MANUEL.md) | Sommaire imposé + règles de rédaction | ✅ Oui |
| [`05-PERIMETRE-ET-LIMITES.md`](05-PERIMETRE-ET-LIMITES.md) | Ce qui n'existe pas encore — **à ne pas documenter** | ✅ Oui |

### Fichiers facultatifs, utiles selon le public

| Fichier du dépôt | Quand le joindre |
|---|---|
| `dictionnaire_balises.md` (47 Ko) | Si le manuel doit inclure un chapitre « créer un modèle Word » pour l'administrateur |
| `devbook.md` (300 Ko) | **Ne pas joindre tel quel** — c'est un journal de développement, il parlerait de code. Le contenu utile en a déjà été extrait dans les 5 fichiers ci-dessus |
| Captures d'écran | Fortement recommandé — voir la section « Captures » ci-dessous |

## Les captures d'écran

Le manuel sera nettement meilleur avec des captures. Claude ne peut pas les produire :
prenez-les vous-même, et joignez-les à la conversation **au moment où Claude rédige le chapitre
correspondant** (pas toutes d'un coup — cela sature le contexte).

Liste minimale, dans l'ordre du manuel :

1. Écran de connexion + écran de saisie du code à 6 chiffres
2. Tableau de bord complet
3. Liste des dossiers avec les filtres déployés
4. Assistant de création — les 3 étapes (Catégorie / Détails / Récapitulatif)
5. Fiche dossier — le bandeau d'étapes + le panneau « conditions requises »
6. Fiche dossier — chacun des 8 onglets
7. Écran de certification (grille de contrôle)
8. Liste des formalités + la fenêtre « Déposer » et la fenêtre « Retour reçu »
9. Onglet Facturation d'un dossier (lignes + paiements)
10. GED, Répertoire, Courriers, Demandes clients
11. Le formulaire client public (lien d'intake), vu depuis un téléphone
12. Paramètres — chaque onglet

## Après la rédaction

Le manuel sort en Markdown. Pour le livrer à l'étude :

- **PDF paginé** : Pandoc, ou coller dans Word puis exporter.
- **Version en ligne** : demandez à Claude de le republier en Artifact (page web consultable).
- **Fiches mémo** : demandez en plus un « aide-mémoire 1 page par rôle » — les six rôles
  d'Ayelema n'utilisent pas les mêmes écrans.

---

*Kit produit le 2026-09-21 par analyse du dépôt (routes, contrôleurs, services, énumérations,
34 écrans React, devbook). Chaque affirmation des fichiers est tirée du code ; rien n'y est supposé.*
