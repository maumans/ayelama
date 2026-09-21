# 01 — Comment Ayelema remplit un modèle Word

Tout ce qu'il faut savoir sur le moteur avant de baliser un document. Établi par lecture de
`ActesGeneratorService` (973 lignes), du modèle `ModeleActe` et de ses rattachements.

---

## 1. Le principe

Un **modèle** est un fichier `.docx` dans lequel chaque information variable est remplacée par une
**balise** de la forme `${nom.du.champ}`. Au moment où le dossier entre en étape *Édition actes*,
l'application ouvre le modèle, remplace chaque balise par la valeur correspondante du dossier, et
enregistre le résultat comme un nouvel acte du dossier.

Le moteur est **PhpWord / TemplateProcessor**. Cela commande trois choses :

- **seul le `.docx` fonctionne** — pas de `.doc`, pas de `.rtf`, pas de PDF ; il faut donc
  ré-enregistrer les `.doc` reçus au format *Word 2007-365 (.docx)* ;
- la substitution est **purement textuelle** — il n'y a ni condition, ni boucle, ni calcul dans le
  document ; la seule structure disponible est le bloc répétable décrit au §5 ;
- **la mise en forme est préservée** : la balise hérite du style du texte qu'elle remplace. Une
  balise en gras produit une valeur en gras.

---

## 2. La règle d'or : une balise inconnue est effacée en silence

Si une balise n'a aucune valeur correspondante :

1. le document est **quand même produit** — la génération n'échoue jamais pour ça ;
2. le passage est laissé **vide** ;
3. une ligne est écrite dans l'historique du dossier :
   *« Document « X » généré avec N champ(s) resté(s) vide(s) faute de donnée : … »*.

**Conséquence directe pour le baliseur** : une balise mal orthographiée ne produit pas d'erreur
visible. Elle produit un acte avec un trou. C'est le mode de défaillance à redouter, et la raison
pour laquelle le fichier `02-VARIABLES-DISPONIBLES.md` doit être suivi à la lettre.

---

## 3. Les balises toujours disponibles, quel que soit l'acte

### Constantes de l'office (jamais saisies, injectées partout)

| Balise | Valeur |
|---|---|
| `${office.notaire}` | Maître Ayelama BAH |
| `${office.titre}` | Notaire |
| `${office.charge}` | n°21 |
| `${office.residence}` | Ratoma |
| `${office.adresse}` | Nongo, 3ᵉ étage, Immeuble VISTA BANK |
| `${office.bp}` | BP 2668/2868 |
| `${office.commune}` | Commune de Ratoma/Lambanyi |
| `${office.ville}` | Conakry |
| `${office.telephones}` | 622 49 69 44 / 664 20 96 07 / 655 61 38 38 |
| `${office.email}` | ayelama.bah@notaire-guinee.com |

### Dossier et acte

| Balise | Contenu |
|---|---|
| `${dossier.reference}` | ex. `SOC-2026-0042` |
| `${dossier.objet}` | l'objet saisi à la création |
| `${acte.numero}` · `${acte.reference}` | la référence de l'acte, forme `NB/MK/SOC-2026-0042/01` (initiales du notaire / initiales du rédacteur / référence du dossier / numéro d'ordre) |
| `${acte.nb_pages}` · `${acte.nb_pages_lettres}` | ⚠️ **toujours vides** — le nombre de pages n'est connu qu'après production. Les laisser dans le modèle est sans danger, mais ils ne seront jamais remplis. |

### Date de l'acte

| Balise | Rend | Usage |
|---|---|---|
| `${date_acte_jma}` | `21/09/2026` | une date en chiffres |
| `${annee_lettres}` | `DEUX MILLE VINGT-SIX` | l'année seule |
| `${date_acte_lettres}` | `VINGT-ET-UN SEPTEMBRE` | jour + mois, **sans l'année** — pour la formule notariale « L'AN ${annee_lettres} ; LE ${date_acte_lettres} » |
| `${date_acte_lettres_complete}` | `VINGT-ET-UN SEPTEMBRE DEUX MILLE VINGT-SIX` | date complète en lettres |

Le premier du mois se dit **PREMIER**, pas « UN ». Les mois sont en majuscules accentuées
(`FÉVRIER`, `AOÛT`, `DÉCEMBRE`).

---

## 4. Les variantes automatiques — ne jamais les inventer

Le moteur fabrique certaines balises à partir d'autres. **Il ne les fabrique que dans ces trois
cas précis** :

| Vous avez un champ… | Vous gagnez automatiquement | Exemple |
|---|---|---|
| **de type date** (`nom`) | `${nom_jma}` et `${nom_lettres}` | `${pp.date_naissance_lettres}` → `QUINZE MARS MILLE NEUF CENT QUATRE-VINGT-DIX` |
| **dont le nom finit par `_chiffres`** | `${…_lettres}` et `${…_formate}` | `${soc.capital_chiffres}` → `50000000` · `${soc.capital_lettres}` → `CINQUANTE MILLIONS` · `${soc.capital_formate}` → `50 000 000` |
| — | — | — |

**Attention, c'est la source d'erreur la plus fréquente :**

- `_lettres` n'existe **que** pour un champ dont le nom se termine littéralement par `_chiffres`,
  ou pour un champ de type date. Un champ nommé `soc.nombre_parts` (sans `_chiffres`) **n'a pas**
  de `${soc.nombre_parts_lettres}`.
- `_formate` (séparateurs de milliers) n'existe **que** pour les `_chiffres`.
- La variante `_lettres` d'un montant est rendue **sans devise** : le modèle doit écrire lui-même
  « FRANCS GUINÉENS » après la balise.

### Deux commodités supplémentaires

- **Adresse recomposée** : pour les préfixes `pp.`, `ger.`, `acq.`, `loc.` et `liquidateur.`, la
  balise `${préfixe.adresse}` est fabriquée en assemblant *quartier, commune, ville* si le
  questionnaire ne porte pas déjà un champ adresse.
- **Fin de bail** : `${bail.date_fin}` est calculée depuis `bail.date_prise_effet` +
  `bail.duree_chiffres`. Elle ne se saisit pas.

### Une dérivation propre à la SARLU et à la SASU

Quand l'associé unique est aussi le gérant (cas le plus fréquent, case décochée), tous les champs
`pp.*` sont recopiés vers `ger.*` — et pour la SASU vers `soc.president_*`. Un modèle de statuts
SARLU peut donc écrire `${ger.prenom_nom}` en toute sécurité : la valeur arrivera même si le
clerc n'a rien saisi dans la section gérant.

---

## 5. Les blocs répétables

Pour une liste de longueur variable (les associés, les administrateurs, les cédants…), on encadre
le passage à répéter :

```
${associes}
Monsieur ${associes.nom}, de nationalité ${associes.nationalite}, demeurant
${associes.adresse}, titulaire de la pièce n° ${associes.cni}, apportant la somme de
${associes.apport_chiffres} francs guinéens.
${/associes}
```

PhpWord duplique le bloc autant de fois qu'il y a d'éléments.

**Les neuf blocs disponibles** (leurs champs sont détaillés dans `02-VARIABLES-DISPONIBLES.md`) :

| Bloc | Employé par |
|---|---|
| `associes` | SARL, SAS, SNC |
| `gerants` | SARL, SNC |
| `actionnaires` | SA |
| `administrateurs` | SA, GIE |
| `membres` | GIE |
| `modif.cedants` | Modification — cession de parts |
| `modif.cessionnaires` | Modification — cession de parts |
| `modif.souscripteurs` | Modification — augmentation de capital |
| `modif.repartition_apres` | Modification — répartition après cession |

**Règles du bloc :**

- le marqueur d'ouverture `${bloc}` et de fermeture `${/bloc}` doivent être **chacun dans leur
  propre paragraphe**, seuls sur leur ligne ;
- à l'intérieur, les champs s'écrivent `${bloc.champ}` — jamais `${champ}` seul ;
- un bloc peut encadrer une **ligne de tableau** (le marqueur d'ouverture dans la ligne
  précédente, la fermeture dans la ligne suivante) ;
- un bloc **vide** (aucun associé saisi) disparaît proprement.

⚠️ **Ce que le moteur ne sait pas encore faire** : dupliquer les lignes d'un tableau par
`cloneRowAndSetValues`. Les tableaux à nombre de lignes variable qui ne passent pas par un bloc
répétable ci-dessus (lignes de note de frais, titres fonciers multiples) ne sont donc pas
automatisables aujourd'hui.

### Choix multiples

Un champ de type *cases à cocher multiples* (`modif.types` par exemple) est rendu comme une
**énumération séparée par ` · `**. Ce n'est pas un bloc répétable : on écrit simplement
`${modif.types}`.

---

## 6. Comment un modèle est rattaché à une procédure

Dans **Modèles d'actes**, un modèle porte :

- un **nom** — c'est lui qui identifie l'acte au dossier. **Ne le changez jamais après coup** :
  la régénération et le « ne pas écraser un acte existant » se font par correspondance de nom ;
- un **type de document** (son rôle), parmi :

| Slug | Libellé |
|---|---|
| `acte_principal` | Acte principal |
| `page_garde` | Page de garde |
| `attestation` | Attestation |
| `declaration` | Déclaration |
| `dnsv` | DNSV |
| `insertion` | Insertion au JORG |
| `rccm` | RCCM |
| `acte_cession` | Acte de cession de parts |
| `pv_modification` | Procès-verbal de l'assemblée |
| `statuts_maj` | Statuts mis à jour |
| `declaration_rccm` | Déclaration de modification RCCM |
| `note_frais` | Note de frais |
| `bordereau` | Bordereau / Tableau |
| `annexe` | Annexe |
| `procedure` | Procédure |
| `lettre` | Lettre / Transmission |
| `recepisse` | Récépissé |

- une **version** (texte libre, ex. `1.0`) ;
- un état **actif / inactif** — seuls les modèles actifs produisent des actes ;
- un ou plusieurs **rattachements** : *ce gabarit sert ce type d'acte*, éventuellement restreint à
  une **variante**. La variante `null` signifie « toutes ». C'est ce qui permet de dire qu'un acte
  de cession ne sert qu'aux cessions de parts, tandis qu'un procès-verbal sert les neuf résolutions.

Un même fichier peut donc servir plusieurs procédures : les statuts, la DNSV et le RCCM d'une
SARLU servent aussi à sa modification. Un modèle peut aussi remplir **plusieurs rôles** (un
gabarit de statuts coché à la fois `acte_principal` et `statuts_maj`).

---

## 7. Le cas particulier : le gabarit hérité

Quand la société modifiée **n'a pas été constituée par l'étude**, le document « Statuts mis à
jour » est produit à partir des **statuts déposés au registre**, et non d'un modèle Ayelema.

**Ne balisez pas les statuts d'un confrère.** Ils ne portent pas nos `${...}`, et c'est voulu : le
document produit est alors une copie fidèle des statuts d'origine, à reprendre article par
article — bien préférable à un gabarit Ayelema dont la numérotation d'articles ne correspondrait à
rien. L'historique du dossier signale le cas (« aucune balise détectée »).

Symétriquement, pour une société que l'étude **a** constituée, les statuts mis à jour repartent des
statuts en vigueur de cette société, cherchés dans cet ordre : pièce déposée au registre, puis
statuts mis à jour de la dernière modification devenue effective, puis statuts du dossier de
constitution.

---

## 8. La procédure complète, du `.doc` reçu au modèle en service

1. **Ouvrir** le document reçu dans Word.
2. **Enregistrer sous** → *Document Word (.docx)* si l'original est en `.doc`.
3. **Repérer chaque marqueur** : pointillés `……………`, mentions en MAJUSCULES
   (`PRENOM ET NOM`, `MONTANT DU CAPITAL EN LETTRE`), instructions entre parenthèses, blancs.
4. **Remplacer** chaque marqueur par la balise exacte du fichier `02-VARIABLES-DISPONIBLES.md`,
   pour le questionnaire du type d'acte visé.
5. **Encadrer les listes** par un bloc répétable si le nombre d'éléments varie.
6. **Vérifier** qu'aucune balise inventée ne subsiste (voir §9).
7. **Enregistrer** en `.docx`.
8. **Téléverser** dans *Modèles d'actes*, renseigner le nom, le type de document, la version, et
   le ou les rattachements.
9. **Tester** : créer un dossier du type correspondant, le faire passer en Édition, ouvrir l'acte
   produit et lire l'**historique du dossier** — il énumère les balises restées vides.

### Un piège Word à connaître

Word découpe parfois un mot en plusieurs fragments internes (correction orthographique, changement
de langue, suivi des modifications). Une balise ainsi coupée **n'est plus reconnue**. Pour
l'éviter : taper la balise d'un seul tenant, sans copier-coller depuis une source formatée, et
sans laisser de marque de révision à l'intérieur. En cas de doute, sélectionner la balise, la
supprimer entièrement, et la retaper.

---

## 9. Vérifier un modèle avant de le livrer

Un script est fourni : [`tools/verifier-balises.php`](../../tools/verifier-balises.php).

```
php tools/verifier-balises.php "chemin/vers/MON_MODELE.docx"
```

Il liste les balises du document et signale celles qui **ne correspondent à aucun champ réel**.
C'est le contrôle qui manquait : trois modèles déjà en service contiennent des balises qui ne se
rempliront jamais (voir `03-ETAT-DES-MODELES.md`).
