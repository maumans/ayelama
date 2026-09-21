# 04 — Exemples réels : avant / après

Extraits authentiques des modèles déjà adaptés. Ce sont les conventions de l'étude : les suivre
plutôt que d'en inventer d'autres.

---

## 1. En-tête d'acte — `STATUTS.docx` → `STATUTS_SARLU_balises.docx`

### Avant (document reçu)

```
NOM DE LA SOCIETE
SOCIÉTÉ A RESPONSABILITÉ LIMITÉE UNIPERSONNELLE
CAPITAL SOCIAL : MONTANT DU CAPITAL SOCIAL DE FRANCS GUINEENS
SIÈGE SOCIAL : QUARTIER, COMMUNE, VILLE, REPUBLIQUE DE GUINEE.

STATUTS

L'AN DEUX MILLE VINGT ….. ;
LE ............... ;
Maître Ayelama BAH, Notaire soussignée, titulaire de la charge n° 21, avec résidence à
Ratoma, (R. Guinée), Nongo, 3ème Etage, Immeuble VISTA BANK, Commune de Ratoma,
A ETABLI, en la forme authentique, le présent acte contenant: STATUTS D'UNE SOCIÉTÉ À
RESPONSABILITÉ LIMITÉE UNIPERSONNELLE,
A LA REQUETE DE :
Monsieur PRENOM ET NOM, demeurant à ville, quartier ……, Commune de ……… (pays) ;
Né à …………, le …………… ;
De nationalité Guinéenne, titulaire du Passeport numéro …………, délivré le ………… à ……….
et expirant le ……………. ;
```

### Après (modèle en service)

```
${soc.denomination}
SOCIÉTÉ A RESPONSABILITÉ LIMITÉE UNIPERSONNELLE
CAPITAL SOCIAL : ${soc.capital_lettres} (${soc.capital_chiffres}) DE FRANCS GUINEENS
SIÈGE SOCIAL : ${soc.siege_quartier}, ${soc.siege_commune}, ${soc.siege_ville}, REPUBLIQUE DE GUINEE.

STATUTS

L'AN ${annee_lettres} ;
LE ${date_acte_lettres} ;
${office.notaire}, ${office.titre} soussignée, titulaire de la charge ${office.charge}, avec
résidence à ${office.residence}, (R. Guinée), ${office.adresse}, ${office.commune},
A ETABLI, en la forme authentique, le présent acte contenant: STATUTS D'UNE SOCIÉTÉ À
RESPONSABILITÉ LIMITÉE UNIPERSONNELLE,
A LA REQUETE DE :
Monsieur ${pp.prenom_nom}, demeurant à ${pp.demeurant_ville}, quartier ${pp.quartier},
Commune de ${pp.commune} (${pp.pays}) ;
Né à ${pp.ne_a}, le ${pp.date_naissance} ;
De nationalité ${pp.nationalite}, titulaire du ${pp.piece_type} numéro ${pp.piece_numero},
délivré le ${pp.piece_delivree_le} à ${pp.piece_delivree_a} et expirant le ${pp.piece_expire_le} ;
```

### Ce que cet exemple enseigne

| Observation | Règle |
|---|---|
| `SOCIÉTÉ A RESPONSABILITÉ LIMITÉE UNIPERSONNELLE` reste en dur | **Ce qui ne varie pas dans ce modèle ne se balise pas.** Un modèle est propre à une forme : inutile de rendre la forme variable. |
| `MONTANT DU CAPITAL SOCIAL` devient `${soc.capital_lettres} (${soc.capital_chiffres})` | La forme notariale « montant en lettres suivi du chiffre entre parenthèses » s'écrit avec **les deux balises**. |
| `DE FRANCS GUINEENS` reste en dur | La variante `_lettres` **ne porte jamais la devise** : c'est le modèle qui l'écrit. |
| `L'AN DEUX MILLE VINGT ….. ; LE ............` devient `${annee_lettres}` puis `${date_acte_lettres}` | Quand l'année figure déjà sur sa ligne, on emploie `${date_acte_lettres}` (jour + mois seuls), pas la version complète. |
| Le bloc « Maître Ayelama BAH… » entièrement balisé | **Toujours baliser l'office**, même si la valeur en dur est correcte aujourd'hui : elle est configurée à un seul endroit. |
| `Monsieur` reste en dur devant `${pp.prenom_nom}` | Convention actuelle de l'étude. `${pp.civilite}` existe si l'on veut le rendre variable — mais dans ce cas, **retirer le mot** et ne pas écrire « Monsieur ${pp.civilite} ». |
| `De nationalité Guinéenne` devient `De nationalité ${pp.nationalite}` | Une valeur par défaut fréquente reste une valeur : elle se balise. |
| `titulaire du Passeport numéro` devient `titulaire du ${pp.piece_type} numéro` | Le type de pièce varie (CNI CEDEAO, Passeport, Extrait de naissance, Carte Consulaire, Permis de conduire). |

---

## 2. Comment se présentent les marqueurs à remplacer

Dans les documents reçus, la variable est signalée de quatre façons. Toutes se traitent :

| Forme rencontrée | Exemple réel | Ce qu'elle signale |
|---|---|---|
| **Pointillés** | `Né à …………, le ……………` | un blanc à remplir |
| **Points** | `LE ...............` | idem |
| **MAJUSCULES descriptives** | `NOM DE LA SOCIETE`, `MONTANT DU CAPITAL SOCIAL`, `PRENOM ET NOM` | le nom de la donnée attendue |
| **Minuscules génériques** | `demeurant à ville, quartier ……, Commune de ……… (pays)` | idem, en minuscules |

⚠️ **Ne pas confondre avec la ponctuation notariale.** Le `;` de fin de ligne, les guillemets
`"L'ASSOCIE UNIQUE"`, les numéros d'article, les références à l'Acte Uniforme OHADA : tout cela
est du texte, pas des marqueurs.

---

## 3. Un document court, entièrement balisé — `ATTESTATION_SARLU_balises.docx`

Neuf balises, toutes résolvables. C'est le modèle de référence pour un document simple :

```
${office.ville}, le ${date_acte_jma}

${office.notaire}, ${office.titre} titulaire de la charge ${office.charge},
avec résidence à ${office.residence},

ATTESTE que le capital social de ${soc.capital_lettres} (${soc.capital_chiffres})
francs guinéens de la société ${soc.denomination} a été intégralement déposé.
```

---

## 4. Un bloc répétable, tel qu'il doit être écrit

Le questionnaire SARL porte un bloc `associes` de cinq champs (`nom`, `apport_chiffres`,
`nationalite`, `adresse`, `cni`). Dans le modèle Word :

```
${associes}
Monsieur ${associes.nom}, de nationalité ${associes.nationalite}, demeurant à
${associes.adresse}, titulaire de la pièce n° ${associes.cni}, apportant la somme de
${associes.apport_lettres} (${associes.apport_formate}) francs guinéens ;
${/associes}
```

Remarquez `${associes.apport_lettres}` et `${associes.apport_formate}` : le champ s'appelant
`apport_chiffres`, ses deux variantes sont disponibles **à l'intérieur du bloc aussi**.

**Contraintes de mise en page :**

- `${associes}` seul dans son paragraphe, `${/associes}` seul dans le sien ;
- pour répéter une **ligne de tableau**, placer l'ouverture dans la ligne précédente et la
  fermeture dans la ligne suivante ;
- ne jamais imbriquer un bloc dans un autre.

---

## 5. Les erreurs réellement commises, à ne pas reproduire

Relevées dans les modèles déjà en service :

| Erreur | Pourquoi elle est passée inaperçue | Correction |
|---|---|---|
| `${date_acte_lettres_sans_annee}` | Le nom décrit exactement le besoin — mais la balise réelle s'appelle autrement | `${date_acte_lettres}` |
| `${ger.nom}` + `${ger.prenom}` | Séparer nom et prénom paraît naturel | `${ger.prenom_nom}`, un seul champ |
| `${soc.nombre_parts_lettres}` | `${soc.capital_lettres}` fonctionne, donc celle-ci devrait fonctionner | ❌ non : `soc.nombre_parts` ne finit pas par `_chiffres`, il n'y a pas de variante `_lettres` |
| `${soc.rccm_numero}` | Le champ existe, mais s'appelle `soc.rccm` | `${soc.rccm}` |
| `${loc.ne_a}`, `${loc.date_naissance}` | Le bloc `pp.` les a, donc `loc.` devrait les avoir | ❌ non : le bloc locataire ne porte que 8 champs |
| `${fac.total_lettres}`, `${ligne.designation}` | Figurent au dictionnaire | ❌ aucun questionnaire ne porte de bloc facture |

**Le dénominateur commun** : toutes ces balises sont *plausibles*. Aucune n'a été vérifiée contre
la liste réelle. C'est la seule protection efficace :

```
php tools/verifier-balises.php "MON_MODELE.docx"
```
