# Notes de frais reçues le 2026-11-20 — confrontation aux barèmes

*Analyse avant toute modification. Sources : `docs/ACTES/FACTURE.xls` (KOUMI INVESTMENTS LTD,
SARLU, n° 325) et `docs/ACTES/SARL/FACTURE.xls` (NEXIUM, SARL, n° 324), lues avec leurs formules
et leurs lignes masquées ; comparées aux barèmes actifs `SOC-SARLU` / `SOC-SARL` et aux
dix autres classeurs `FACTURE*.xls` de `Documents reçus/`.*

---

## 1. Les deux notes, ligne par ligne

| # | Désignation (étude) | KOUMI SARLU | NEXIUM SARL | Calcul dans le classeur |
|---|---|---|---|---|
| 1 | Honoraires Forfaitaires | 4 500 000 | 4 500 000 | saisi |
| 2 | Droits d'enregistrement des Statuts et de la DNSV *(SARL : « de DNSV »)* | **0** | **0** | saisi |
| 3 | Frais APIP et RCCM | 390 000 | 390 000 | saisi |
| 4 | Insertion dans un Journal d'Annonces Légales | 250 000 | 250 000 | saisi |
| 5 | Timbres fiscaux | 52 × 2 000 = 104 000 | 52 × 2 000 = 104 000 | `=C×2000` |
| 6 | Rôles | 52 × 5 000 = 260 000 | 52 × 5 000 = 260 000 | `=C×5000` |
| 7 | **Débours** | **500 000** | **1 000 000** | saisi |
| | **Total** | **6 004 000** | **6 504 000** | somme |

Deux lignes **masquées** (« calendrier spiralé », « porte-carte simili cuir », dont une en
`#REF!`) sont des restes d'un ancien classeur : hors total, sans objet.

En-tête : « **NOTE DE FRAIS ET ÉMOLUMENTS** », n° **325/MAB/2026** et **324/MAB / 26**,
« N° de compte : 011406700100 ORABANK GUINEE SA », objet suivi de la parenthèse récapitulative.

## 2. Ce que l'application facture pour le même dossier

Barèmes actifs (identiques en SARLU et en SARL), dernière facture SARLU réelle (025/MAB/26) :

| Ligne application | Montant | Sur la note de l'étude ? |
|---|---|---|
| Honoraires forfaitaires | 4 500 000 | ✅ ligne 1 |
| Enregistrement fiscal acte | 70 000 | ❌ **absente** — barème `a_confirmer` |
| Droits d'enregistrement des Statuts et de la DNSV | 0 | ✅ ligne 2 — mais **non imprimée** (le générateur saute une ligne à 0) |
| Frais APIP et RCCM | 390 000 | ✅ ligne 3 |
| Insertion JAL | 250 000 | ✅ ligne 4 |
| Enregistrement au Tribunal de Commerce (RCCM) | 180 000 | ❌ **absente** — barème `a_confirmer` |
| Timbres fiscaux (à la page) | 2 000 × **0** | ✅ ligne 5 — quantité à saisir, 0 par défaut |
| Rôles (à la page) | 5 000 × **0** | ✅ ligne 6 — idem |
| — | — | ❌ ligne 7 **Débours : aucun barème** |

| Total | KOUMI | NEXIUM |
|---|---|---|
| Étude | **6 004 000** | **6 504 000** |
| Application, pages non saisies | 5 390 000 | 5 390 000 |
| Application, 52 pages saisies | 5 754 000 | 5 754 000 |
| **Écart** (pages saisies) | **− 250 000** | **− 750 000** |

L'écart vient de deux sens opposés : l'application **ajoute** 250 000 (70 000 + 180 000) que
l'étude ne facture pas, et **omet** le débours de 500 000 / 1 000 000.

## 3. Ce que montrent les autres classeurs

- La ligne **« Débours » est nouvelle** : les modèles vierges `FACTURE SARL.xls` et
  `FACTURE SARLU.xls` (total 5 140 000) ne l'ont pas. Son montant varie d'un dossier à l'autre.
- Les ventes et le bail à construction portent une ligne voisine, **« Frais de virement »
  500 000**, déjà en barème (4 barèmes actifs).
- Le titre varie : « NOTE DE FRAIS ET ÉMOLUMENTS » (sociétés, ventes), « NOTE DE FRAIS ET
  HONORAIRE » (baux). L'application imprime « FACTURE ».
- Le n° de compte ORABANK figure aussi sur la note KANAME SAS de 2025 : c'est le compte de
  l'étude, pas celui du client. Les 25 factures de l'application ont la case vide (« — »).

## 4. Numérotation

L'étude en est au **n° 325** en novembre 2026 ; l'application à **025/MAB/26**. Deux registres
coexistent donc. Pour la comptabilité, il ne peut y en avoir qu'**un**, sans trou ni doublon.

## 5. Nature de chaque ligne — ce que la comptabilité devra savoir

| Ligne | Nature proposée | Certitude |
|---|---|---|
| Honoraires forfaitaires | **honoraire** (produit de l'étude) | sûre |
| Droits d'enregistrement | **débours** (Impôts) | sûre |
| Frais APIP et RCCM | **débours** (APIP / greffe) | sûre |
| Insertion JAL | **débours** (journal) | sûre |
| Timbres fiscaux | **débours** (Impôts) | sûre |
| **Rôles** | ⚠️ **débours ou émolument ?** | en pratique notariale, le « rôle » est la page d'expédition : il peut rémunérer l'étude ou être un droit reversé |
| **Débours** (forfait) | ⚠️ **débours justifié, ou provision forfaitaire ?** | si l'étude n'en reverse qu'une partie, le reste est un produit |
| Frais de virement | ⚠️ frais bancaire réel, ou forfait de l'étude ? | à confirmer |

## 6. Questions à trancher avec l'étude

| # | Question | Pourquoi c'est bloquant |
|---|---|---|
| F1 | Que couvre la ligne **« Débours »** ? Comment fixe-t-on 500 000 ou 1 000 000 (capital : 10 M → 500 000, 100 M → 1 000 000 ?) | barème impossible à poser sans règle ; et sa nature décide si c'est un produit |
| F2 | Les barèmes **« Enregistrement fiscal acte » (70 000)** et **« Enregistrement au Tribunal de Commerce » (180 000)** doivent-ils disparaître ? Le débours les couvre-t-il ? | l'application facture 250 000 que l'étude ne facture pas |
| F3 | Pourquoi les **droits d'enregistrement à 0** ? Exonération, ou montant connu seulement au retour des Impôts ? Doit-on imprimer la ligne à 0 comme l'étude ? | le moteur la supprime ; la formalité « Impôts » part aussi à 0 |
| F4 | Les **52 pages** : comment sont-elles comptées (minute + expéditions de tous les actes ?) ; identiques sur les deux dossiers alors que les statuts font 8 et 9 pages | aujourd'hui la quantité est à 0 et rien n'oblige à la saisir |
| F5 | **Rôles** : reversés à l'État ou conservés par l'étude ? | nature comptable |
| F6 | Quel **titre** : « Note de frais et émoluments » partout ? | document remis au client |
| F7 | Le **compte ORABANK** est-il le seul compte à imprimer ? (paramètre de l'office, et non saisie facture par facture) | 25 factures sur 25 sans compte |
| F8 | **Numérotation** : l'application devient-elle le registre unique, en reprenant à 326 ? | continuité exigée par la comptabilité |
| F9 | Libellés : reprendre ceux de l'étude (« Insertion dans un Journal d'Annonces Légales », « Timbres fiscaux ») ? | cohérence client / étude |

## 6 bis. Réponses de l'étude (2026-11-20) et ce qu'elles ouvrent

| # | Réponse | Ce qui reste à préciser (mesuré) |
|---|---|---|
| F1 | Le débours dépend du capital : 10 M → 500 000, 100 M → 1 000 000 | ✅ **Fait** sur **toutes les formes** (décision #129) : moins de 100 M → 500 000, dès 100 M → 1 000 000. ⚠️ Cette coupure est une **lecture** des deux seuls points connus : la ligne porte `a_confirmer` tant que l'étude ne donne pas sa grille (capitaux en base : 15 M, 30 M, 50 M ×8, 60 M, 140 M, 150 M, 200 M, 900 M, 1 Md) |
| F2 | Retirer « Enregistrement fiscal acte » (70 000) et « Enregistrement au Tribunal de Commerce » (180 000), toutes formes | ✅ **Fait** sur les 7 constitutions (migration `2026_11_20_110000`, `CONTREDITS` du seeder). Le 70 000 générait une formalité Impôts qui **doublait** celle des droits d'enregistrement. Constitution SARLU/SARL désormais : 5 140 000 avant débours et pages, comme le modèle vierge de l'étude. Le 2026-11-23, retiré aussi de `SOC-MOD`, où il ouvrait la démarche de greffe (décision #129) |
| F3 | Droits d'enregistrement connus au **retour des Impôts**, 0 par défaut → **facture complémentaire** | ✅ **Fait** (décision #130) : le montant se saisit au retour de la formalité Impôts et établit une facture complémentaire proforma, rattachée à la principale |
| F4 | Timbres et rôles = pages des **statuts + DNSV**, sans page de garde | ✅ **Fait** (décision #128) : un champ « Nombre de pages » sur la facture alimente les deux lignes ; émission refusée sans lui. La règle confirmée par l'étude donne 11 pages pour KOUMI et 12 pour NEXIUM — les deux notes Excel en portaient 52, valeur que l'étude n'a pas retenue |
| F5 | Rôles reversés à l'État | → **débours** |
| F6 | Garder « Facture » | — |
| F7 | Liste manuscrite des banques ; on choisit la banque sur chaque facture | ✅ **Fait** (décision #128) : 8 comptes dans Paramètres → Comptes bancaires, choix dans l'en-tête de facture. ACCESS BANK et BSIC restent **à vérifier** sur un RIB |
| F8 | L'application devient le registre unique, reprise à 326 | ✅ **Fait** : les 25 factures d'essai sont renommées `TEST-001` à `TEST-025/MAB/26`, hors séquence ; la prochaine facture émise sera la **326/MAB/26** (décision #126) |
| F9 | Libellés des documents fournis ; ligne 2 = « Droits d'enregistrement des Statuts et de la DNSV » | ✅ **Fait** (décision #129) : « Honoraires Forfaitaires », « Insertion dans un Journal d'Annonces Légales », « Timbres fiscaux », « Rôles » — en base et dans les seeders |

## 7. Ce que je propose une fois les réponses connues

1. Ajouter à chaque barème sa **nature** (honoraire / débours, voire « frais ») — elle sera
   copiée sur la ligne de facture et servira directement à la comptabilité (lot 1 du
   `PLAN-COMPTABILITE.md`).
2. Créer le barème **Débours** selon la règle F1 (fixe, par tranche de capital, ou saisi à la
   main avec plafond).
3. Désactiver — sans supprimer — les barèmes que F2 écarte ; lever `a_confirmer` sur ceux qu'elle
   confirme.
4. Imprimer ou non les lignes à 0 selon F3 ; rendre la **quantité de pages obligatoire avant
   l'émission** (blocage énuméré) selon F4.
5. Titre, compte et libellés selon F6, F7, F9 ; numérotation selon F8.
