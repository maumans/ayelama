# Convertir les `.doc` de l'étude, et les baliser

> Établi le 2026-11-02. Lève un blocage que le dépôt tenait pour définitif.

## Le blocage levé

`ModeleActeSeeder` porte cette note depuis septembre 2026 :

> ⚠️ **Reconstruit**, pas converti : la source « MY_INSERTION.doc » est un binaire OLE2 que
> PhpWord ne sait pas ouvrir, et **aucun convertisseur n'était disponible**.

Le convertisseur était là : **Word est installé sur le poste**. Une automatisation COM ouvre
chaque `.doc` et le réenregistre en `.docx`, fidèlement — mise en page, styles et tableaux
compris, là où la reconstruction manuelle ne rendait qu'un texte approché.

⚠️ **Une instance de Word par fichier.** Word s'interrompt sur certains `.doc` anciens et
emporte le serveur COM avec lui : une seule instance pour les quinze fichiers s'est arrêtée au
deuxième. Relancer à chaque fichier isole l'incident et coûte quelques secondes.

```powershell
foreach ($f in Get-ChildItem "$src\*.doc") {
  $cible = Join-Path $out ($f.BaseName + ".docx")
  if (Test-Path $cible) { continue }
  $word = $null
  try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false; $word.DisplayAlerts = 0
    $word.AutomationSecurity = 3          # macros désactivées : ce sont des fichiers reçus
    $doc = $word.Documents.Open($f.FullName, $false, $true)   # $true = lecture seule
    $doc.SaveAs2($cible, 12)              # 12 = wdFormatXMLDocument
    $doc.Close($false)
  } catch { "FAIL $($f.Name)" }
  finally { if ($word) { try { $word.Quit() } catch {} } }
}
```

⚠️ `SaveAs2($cible, 12)` et **non** `SaveAs([ref]$cible, [ref]16)` : la forme avec `[ref]` est
refusée par l'appel COM tardif de PowerShell (« La valeur n'est pas comprise dans la plage
attendue »).

## Où sont les fichiers convertis

`storage/app/private/modeles/_a_baliser/` — quinze documents, les cinq actes manquants pour
chacune des formes SARL, SAS et SASU :

```
<FORME>__STATUTS.docx   __PAGE_DE_GARDE.docx   __DECLARATION_SUR_LHONNEUR_-.docx
__INSERTION.docx        __RCCM_1.docx
```

Ce dossier n'est **pas** un dossier de gabarits : aucun n'est rattaché à un type d'acte, et
aucun ne porte encore de balise. Il est exclu du dépôt comme le reste de
`storage/app/private`.

## Ce qu'il reste à faire sur eux : le balisage

Bonne nouvelle mesurée : **ce sont des formulaires vierges**, pas des dossiers réels. Ils
portent « NOM DE LA SOCIETE », « PRENOM ET NOM », « MONTANT DU CAPITAL SOCIAL » et des
pointillés — jamais les données d'un client. Le risque d'émettre l'acte d'autrui, réel pour le
jeu SA (dossier DEVKI MINING), **n'existe pas ici**.

Le travail est donc une substitution, en deux temps.

### 1. Les marque-places nommés — mécanique

| Dans le document | Balise |
|---|---|
| `NOM DE LA SOCIETE` | `${soc.denomination}` |
| `MONTANT DU CAPITAL SOCIAL` | `${soc.capital_lettres} (${soc.capital_chiffres})` |
| `MONTANT DU CAPITAL EN LETTRE` / `EN CHIFFRE` | `${soc.capital_lettres}` / `${soc.capital_chiffres}` |
| `QUARTIER, COMMUNE, VILLE` | `${soc.siege_quartier}, ${soc.siege_commune}, ${soc.siege_ville}` |
| `L'AN DEUX MILLE VINGT ……..` | `L'AN ${annee_lettres}` |
| `LE …………….` | `LE ${date_acte_lettres}` |

⚠️ `date_acte_lettres` rend **déjà** le jour et le mois sans l'année. Le nom
`date_acte_lettres_sans_annee`, employé par trois gabarits, ne correspondait à rien et sortait
littéralement dans l'acte ; il a été corrigé.

La substitution se fait par Find/Replace de Word, qui traverse les découpages de runs — une
recherche dans `word/document.xml` échoue dès que Word a coupé un mot en deux.

### 2. Les personnes multiples — le vrai travail

Les statuts SARL, SAS et SASU énumèrent **deux associés en dur** (« 1°) Monsieur … 2°) Madame
… »), repris en trois endroits : la comparution, les apports, puis la répartition des parts.
Les gérants de même. Chacun de ces trois passages doit devenir un bloc répétable :

```
${associes}
${associes.civilite} ${associes.nom}, demeurant à ${associes.demeurant_ville}, …
${/associes}
```

Règles du bloc, rappelées par [01-MOTEUR-DE-GENERATION.md](01-MOTEUR-DE-GENERATION.md) :
ouverture et fermeture **seules sur leur paragraphe**, champs écrits `${bloc.champ}`, et un
bloc vide disparaît proprement.

C'est là que se joue la justesse de l'acte : une phrase conçue pour deux associés (« Lesquels
ont, par ces présentes… ») doit être relue pour valoir au pluriel quel qu'en soit le nombre.
**Ce passage demande une relecture de l'étude**, pas une substitution.

## Ce qui n'est pas dans ce dossier

| Manque | Source chez l'étude | Obstacle |
|---|---|---|
| Statuts et actes **SA** | `fwdmodlessaavecca` (dossier DEVKI MINING) | ⚠️ **Dossier réel**, pas un formulaire vierge : à baliser avec un soin particulier, sous peine d'émettre les statuts de DEVKI MINING pour un autre client |
| Acte de **dépôt de PV** (mode « dépôt au rang des minutes ») | `fwdmodlessaavecca` | Même remarque |
| Actes de **modification** (PV, statuts mis à jour, acte de cession) | aucune source | L'étude n'en a fourni aucun |
| **PV de clôture** et déclaration de **radiation** | aucune source | L'étude n'en a fourni aucun |
| Tout acte **SNC** et **GIE** | aucune source | Seul un questionnaire GIE existe |

## Le garde-fou

`BalisesGabaritsActifsTest` confronte **tout `.docx` du magasin de modèles** à
`balises-resolvables.txt` et aux balises que pose `FactureGeneratorService`. Une balise
inconnue fait échouer le test, parce qu'elle sortirait telle quelle dans l'acte : le
générateur ne touche pas à ce qu'il ne sait pas remplir.

Il a trouvé **dix balises mortes dans quatre gabarits en service** le jour où il a été écrit,
dont les statuts de SARLU — la forme de douze des quinze sociétés du registre. Trois subsistent
et sont énumérées dans `MANQUES_CONNUS`, avec leur raison ; la liste ne peut que rétrécir.
