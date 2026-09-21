# Prompt à coller dans Claude (chat)

> Copiez tout ce qui suit la ligne de séparation, après avoir joint les 5 fichiers du kit.

---

Tu vas rédiger le **manuel utilisateur complet et professionnel** d'**Ayelema**, une application
web de gestion des actes notariaux utilisée par une étude notariale en Guinée.

## Ce que tu as reçu

Cinq documents de référence, extraits du code source de l'application :

- `01-CONTEXTE-PRODUIT.md` — les utilisateurs, leurs rôles, le vocabulaire métier
- `02-REFERENTIEL-ECRANS.md` — chaque écran, ce qu'il affiche, les actions disponibles
- `03-REGLES-METIER.md` — le workflow, les conditions bloquantes, les droits, les calculs
- `04-PLAN-DU-MANUEL.md` — le sommaire à respecter et les règles de rédaction
- `05-PERIMETRE-ET-LIMITES.md` — les fonctions absentes de l'application

## Règles absolues

1. **N'invente rien.** Si un détail manque pour rédiger un passage (un libellé exact, l'ordre de
   deux boutons, le comportement d'un cas limite), écris `⟦À VÉRIFIER : …⟧` en ligne et continue.
   Ne comble jamais un trou par une supposition plausible. Ces marqueurs seront relevés et
   corrigés ; une phrase fausse, elle, ne se verrait pas.
2. **Ne documente rien de ce que liste `05-PERIMETRE-ET-LIMITES.md`.** Ces fonctions n'existent
   pas. Les décrire ferait perdre son temps à l'étude.
3. **Respecte le sommaire** de `04-PLAN-DU-MANUEL.md`. Si tu penses qu'un chapitre manque,
   propose-le avant de commencer, ne l'ajoute pas de ta propre initiative.
4. **Vocabulaire de l'application, exclusivement.** L'écran dit « Certification des actes » :
   n'écris jamais « révision ». Il dit « Note de frais » : n'écris pas « facture » quand c'est
   la note de frais. Le glossaire de `01-CONTEXTE-PRODUIT.md` fait foi.
5. **Français de Guinée, registre professionnel notarial.** Vouvoiement. Montants en GNF.
   Dates au format JJ/MM/AAAA.

## Comment tu procèdes

Tu rédiges **un chapitre par réponse**, dans l'ordre du sommaire. À la fin de chaque chapitre,
tu t'arrêtes et tu attends que je te dise « suivant ». Un manuel complet en une réponse serait
tronqué et bâclé — c'est le seul motif de ce découpage.

**Avant le chapitre 1**, produis d'abord, en une réponse courte :

- le sommaire détaillé que tu vas suivre (titres de niveau 1, 2 et 3) ;
- la liste des points sur lesquels les documents fournis te paraissent insuffisants, pour que je
  te les complète avant que tu écrives.

## Style attendu

- **Orienté tâche**, pas orienté écran. Un chapitre répond à « comment faire X », pas à « voici
  le bouton Y ». Les descriptions d'écran servent la tâche, elles ne la remplacent pas.
- **Procédures numérotées** dès qu'il y a plus de deux gestes à enchaîner.
- **Dis pourquoi, pas seulement comment**, quand la règle n'est pas évidente : l'étude comprendra
  mieux qu'on ne peut pas certifier un acte non produit si on lui explique que la certification
  porte sur des documents.
- **Encadrés** récurrents, avec ces trois libellés uniquement :
  - **Bon à savoir** — précision utile, non bloquante
  - **Attention** — risque d'erreur ou de perte de temps
  - **Bloquant** — l'application refusera, et voici ce qu'il faut faire
- **Tableaux** pour toute énumération de plus de quatre lignes (statuts, rôles, pièces).
- **Pas de jargon technique.** Jamais « Inertia », « React », « migration », « enum »,
  « endpoint ». Le lecteur est clerc, formaliste ou notaire, pas développeur.
- **Emplacements des captures** : marque `⟦CAPTURE : description précise de ce qu'il faut
  photographier⟧` là où une image s'impose. Je les fournirai ensuite.

## Format de sortie

Markdown. Titres hiérarchisés proprement (un seul `#` par chapitre). Pas de bloc de code, sauf
pour montrer une référence de dossier ou une balise de modèle Word.

---

Commence par le sommaire détaillé et la liste de tes questions. N'écris pas encore le chapitre 1.
