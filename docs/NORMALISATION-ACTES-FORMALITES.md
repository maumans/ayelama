# Normalisation des actes, formalités, modèles et barèmes — et ce qui reste avant la production

> État au 2026-11-02. Tout chiffre de ce document est **mesuré**, pas estimé : les actes en
> interrogeant `ActesGeneratorService::actesPrevus()` type par type, les tarifs en lisant la
> table, les formalités en générant sur un dossier réel de chaque variante.

La règle appliquée partout : **ce que l'étude a fourni fait foi**. Ce qu'aucun de ses documents
ne donne reste en place mais se signale ; ce qu'un de ses documents contredit est désactivé.

---

## 1. Ce que les documents de l'étude ont contredit

Les notes de frais (`Documents reçus`, un classeur `FACTURE *.xls` par forme et par type d'acte)
sont les notes réellement envoyées aux clients. Confrontées à la base, elles l'ont contredite
cinq fois :

| Ligne | La base appliquait | La note de l'étude porte |
|---|---|---|
| Honoraires de constitution | 4 500 000 GNF sur les **sept** formes | 4 500 000 en SARL et SARLU, **8 500 000** en SAS et SASU |
| Frais APIP et RCCM | 490 000 partout | **390 000** en SARL et SARLU, 490 000 en SAS et SASU |
| Timbres, rôles | un forfait de 50 000, en une ligne | **deux lignes, à la page** : 2 000 et 5 000 GNF |
| Enregistrement APIP sur une vente | 150 000 | **absent** de la note de vente |
| Mutation Conservation, vente sans titre | 2 % | **absente** — il n'y a pas de titre à muter |

Les deux derniers sont désactivés. Les autres sont corrigés.

### Une divergence que l'étude doit trancher

L'addendum v1.2 § 7.3 annonce **5 500 000 GNF** pour une création de SARL et dit remplacer
l'estimation antérieure ; la note de frais SARL de l'étude porte **4 500 000**. Deux documents
du même client se contredisent. La note l'emporte ici — c'est elle qui part au client, et son
total (5 140 000 GNF) est cohérent — mais la ligne reste marquée pour que l'écart se voie.

---

## 2. Ce qui est maintenant correct

### Les actes suivent la forme et la variante

Trois défauts corrigés, tous invisibles sans interroger la génération :

- **`SOC-MOD` sortait des actes de SARLU.** `RCCM SARLU` y servait de déclaration RCCM sur
  toutes les variantes, `DNSV SARLU` de DNSV sur une augmentation de capital. Une modification
  de SA ou de SNC produisait donc les actes d'une SARLU. Détachés.
- **Une clôture de liquidation produisait un PV de dissolution** et l'insertion de dissolution,
  parce que deux gabarits téléversés à la main portaient tous deux le rôle `acte_principal` et
  étaient rattachés à *toutes* les variantes. Le jeu semé, correctement porté par sa variante
  et plus richement balisé, était inactif. Inversé.
- **Le nom d'un acte sans gabarit venait d'une autre variante** : une clôture s'annonçait
  « Acte de dissolution et liquidation ». Faute de gabarit pour la variante en cours, c'est
  désormais le libellé générique du rôle qui s'affiche.

Résultat mesuré :

| Procédure | Actes annoncés | Dont avec gabarit |
|---|---|---|
| Dissolution | PV, insertion JAL, déclaration RCCM | **3 sur 3** |
| Clôture de liquidation | PV de clôture, déclaration de radiation | 0 sur 2 |
| Modification (10 variantes) | 1 à 4 selon la variante | 0 |
| Constitution SARLU | 5 | **5 sur 5** |
| Constitution SARL, SAS, SASU | 2 | 2 sur 2 |
| Constitution SA, SNC, GIE | **0** | — |

Le gabarit **RCCM de dissolution**, fourni balisé par l'étude mais jamais importé, l'a été.

### Les démarches existent, et vont au greffe

Mesuré avant : `SOC-MOD` → 0 formalité, `SOC-DIS` → 0 barème actif. **Aucune des deux
procédures n'ouvrait la moindre démarche**, et les neuf formalités `SOC-MOD` présentes en base
étaient des démarches **APIP** — ce que la ligne 12 du tableau signé exclut : « L'étude traite
directement avec le greffe du tribunal de commerce. L'APIP n'intervient pas dans les
modifications. »

| Procédure | Démarches ouvertes |
|---|---|
| Modification | Impôts · Journal d'annonces légales · Greffe |
| Dissolution | Impôts · Journal d'annonces légales · Greffe *(dépend de la publication)* |
| Clôture de liquidation | Greffe seul — **aucune publication** |

L'absence de publication à la clôture est une réponse, pas un oubli : la ligne 5 du tableau a
été annotée à la main pour *ajouter* la publication là où elle manquait, et la ligne 17 ne l'a
pas été.

### Le journal d'annonces légales est enfin un organisme

Il était rangé sous « Autre », qui n'existait **que** côté barème : une formalité ainsi posée
était refusée à la moindre correction manuelle et invisible dans l'écran Formalités. Elle
existait en base, personne ne la voyait. La liste des organismes vivait en cinq exemplaires
sans aucun test ; elle vient désormais d'un seul enum, et un test de parité la tient.

---

## 3. Ce qui reste — et pourquoi je ne l'ai pas fait

### Trois formes ne produisent aucun acte : SA, SNC, GIE

L'étude **a fourni** les modèles — ils ne sont pas dans le dépôt :

| Forme | Ce que l'étude a fourni | État |
|---|---|---|
| SARLU | jeu complet, **balisé** | ✅ en service |
| SARL | statuts, page de garde, attestation, déclaration, DNSV, insertion, RCCM | ⚠️ `.doc`, **non balisés** |
| SAS, SASU | mêmes sept documents | ⚠️ `.doc`, **non balisés** |
| SA | statuts, PV d'AG constitutive, PV de CA, DNSV, attestation, liste des souscripteurs, page de garde, RCCM, **et un acte de dépôt de PV** (dossier DEVKI MINING) | ⚠️ `.doc`, **non balisés** |
| SNC, GIE | aucun modèle d'acte — un questionnaire GIE seulement | ❌ rien à importer |

⚠️ **Ces fichiers ne peuvent pas être branchés tels quels.** Le générateur remplace des balises
`${...}` ; un document qui n'en contient aucune ressort **inchangé**, c'est-à-dire portant les
données de la société qui a servi de modèle. Importer `STATUTS DEVKI MINING COMPANY.doc` comme
gabarit de SA produirait des statuts au nom de DEVKI MINING pour tous les clients de l'étude.

Le travail restant est donc un **balisage**, pas un branchement : convertir chaque `.doc` en
`.docx`, remplacer les valeurs du dossier d'origine par les balises du kit
(`docs/kit-modeles/balises-resolvables.txt`), vérifier le rendu. C'est la passe qui a été faite
pour la SARLU. Quatre jeux restent : SARL, SAS, SASU, SA.

SNC et GIE n'ont aucun modèle : rien ne peut être produit tant que l'étude n'en fournit pas.

### Les actes de modification n'ont aucun gabarit

Les dix variantes annoncent leurs actes et signalent que le gabarit manque — ce qui est le
comportement voulu — mais aucun n'est produit. L'étude a fourni un **acte de dépôt de PV avec
reconnaissance d'écritures** (dossier DEVKI MINING) qui est précisément le gabarit du mode
« dépôt au rang des minutes » ; lui aussi est à baliser.

### 52 tarifs restent à confirmer

Ils concernent les formes sans note de frais (SA, SNC, GIE), l'hypothèque, le bail commercial,
la cession de fonds de commerce, la vente de véhicule, et les démarches créées à zéro faute de
grille. Chacun s'affiche marqué à l'écran Barèmes **et** sur la ligne de note de frais.

⚠️ Deux méritent un regard immédiat : la vente de véhicule et la cession de fonds de commerce
portent des « frais de mutation à la Conservation Foncière » hérités d'une boucle sur la
catégorie. Un véhicule n'a pas de titre foncier. Aucun document de l'étude ne le dit, donc la
ligne est marquée plutôt que désactivée — mais elle est à éteindre.

---

## 4. Mise en production

Les corrections se répartissent en deux migrations et deux seeders. Les seeders de règles sont
**idempotents** : les relancer ne duplique rien et restaure la référence.

```
php artisan migrate --force
php artisan db:seed --class=ReglesGestionHonorairesSeeder --force
php artisan db:seed --class=ReglesGestionDocumentsSeeder  --force
php artisan db:seed --class=ReglesGestionFormalitesSeeder --force
php artisan db:seed --class=ReglesGestionOperationsSeeder --force
```

Un fichier est à déposer sur le serveur, il n'est pas dans le dépôt Git :

```
storage/app/private/modeles/societe/rccm-modif-dissolution.docx
```

`public/build` est versionné : le déploiement doit l'emporter avec les pages.

### À vérifier dans l'application, après déploiement

La construction ne voit ni une erreur de portée, ni une structure valide mais fausse.

1. **Paramètres > Barèmes** : le compteur « À confirmer » apparaît, et chaque ligne concernée
   porte sa mention. Une ligne désactivée affiche le motif en italique.
2. **Un dossier de modification** : l'onglet Formalités ouvre trois démarches — impôts, JAL,
   greffe — et **aucune APIP**. L'onglet JAL existe dans l'écran Formalités.
3. **Un dossier de dissolution** : trois actes avec gabarit, trois démarches.
   Puis une **clôture de liquidation** : deux actes, une seule démarche, et le PV annoncé est
   bien « PV de clôture de liquidation », pas celui de dissolution.
4. **Une note de frais de SAS** : honoraires 8 500 000, frais APIP 490 000, timbres et rôles à
   zéro tant que le nombre de pages n'est pas saisi.
5. **Un dossier SOC-SA** : il n'annonce aucun acte. C'est exact et c'est le blocage connu —
   les gabarits SA existent chez l'étude mais ne sont pas balisés.
