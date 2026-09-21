# Kit d'adaptation des modèles d'actes

Ce dossier contient **tout ce qu'il faut transmettre à Claude (chat)** pour qu'il continue le
balisage des modèles Word de l'étude, sans inventer de balises.

## Ce que vous faites

1. Ouvrez une **nouvelle conversation** Claude.
2. **Joignez les quatre fichiers** ci-dessous.
3. **Collez [`00-PROMPT-CLAUDE.md`](00-PROMPT-CLAUDE.md)** comme premier message.
4. Donnez vos documents **un par un**. Claude annonce d'abord ce qu'il a compris, vous validez,
   il produit ensuite le document balisé.
5. Après chaque document : `php tools/verifier-balises.php "MON_MODELE.docx"` et renvoyez-lui la
   sortie s'il y a des erreurs.

## Les fichiers à joindre

| Fichier | Rôle |
|---|---|
| [`01-MOTEUR-DE-GENERATION.md`](01-MOTEUR-DE-GENERATION.md) | Comment l'application remplit un modèle : syntaxe, variantes automatiques, blocs répétables, rattachements, pièges Word |
| [`02-VARIABLES-DISPONIBLES.md`](02-VARIABLES-DISPONIBLES.md) | **Le document central** — les 488 balises réellement disponibles, questionnaire par questionnaire. Généré depuis le code. |
| [`03-ETAT-DES-MODELES.md`](03-ETAT-DES-MODELES.md) | L'inventaire mesuré : 17 modèles balisés, ce qui reste, et les 6 balises fausses déjà en service |
| [`04-EXEMPLES-REELS.md`](04-EXEMPLES-REELS.md) | Extraits avant/après des modèles adaptés — les conventions de l'étude |

**Ne joignez pas `dictionnaire_balises.md`.** Il cite 293 balises, dont **83 sans champ réel**.
C'est précisément ce document qui a produit les erreurs relevées dans `03`. Il reste utile comme
mémoire de conception, mais il ne doit pas servir de référence de balisage.

## Les outils

```
node tools/generer-balises-resolvables.mjs      # régénère la liste de référence
php tools/verifier-balises.php <modele.docx>    # contrôle un modèle
```

Le second **refuse de tourner** si la liste de référence est plus vieille que
`resources/js/data/questionnaires.js` : une liste périmée validerait des balises devenues fausses.

Après toute modification des questionnaires, régénérez la liste **et** le fichier `02` :

```
node tools/generer-balises-resolvables.mjs
```

> ⚠️ `02-VARIABLES-DISPONIBLES.md` a été généré par un script ponctuel. Si les questionnaires
> changent, il faut le régénérer — sinon Claude travaillera sur une photographie périmée.

## Ce qu'il faut corriger en priorité

Avant d'adapter de nouveaux documents, six balises fausses sont déjà **en service** et produisent
des blancs dans les actes (détail et correctifs dans [`03-ETAT-DES-MODELES.md`](03-ETAT-DES-MODELES.md) §3) :

- `${date_acte_lettres_sans_annee}` → `${date_acte_lettres}` — Statuts SARLU, Bail d'habitation, Bail à construction
- `${ger.nom}` + `${ger.prenom}` → `${ger.prenom_nom}` — RCCM SARLU
- `${soc.date_debut_activite_jma}` — aucun champ : à arbitrer
- `${loc.ne_a}` + `${loc.date_naissance}` — aucun champ : à arbitrer

## L'ordre de travail recommandé

1. **Corriger les six balises fausses** — travail de minutes, effet immédiat sur des actes produits.
2. **Terminer les sociétés** : SARL, SAS, SASU ont déjà leur attestation et leur DNSV ; il reste
   statuts, page de garde, déclaration, insertion, RCCM. Le questionnaire existe pour toutes.
3. **Les cinq documents de modification de société** — le questionnaire (72 champs + 4 blocs) est
   prêt et aucun document n'est balisé : c'est le plus gros gain.
4. **Les contrats de vente** (avec et sans titre foncier) — questionnaires prêts, 53 et 47 champs.
5. **SA, SNC, GIE** — questionnaires prêts.
6. **Les courriers** — ⚠️ à ne pas entreprendre avant d'avoir décidé d'où viennent les données
   `cr.*` : aucun questionnaire ne les porte aujourd'hui.

---

*Kit établi le 21/09/2026 par analyse du code (`ActesGeneratorService`, `ModeleActe`,
`questionnaires.js`) et ouverture de chaque `.docx` réellement en service.*
